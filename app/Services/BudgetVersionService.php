<?php

namespace App\Services;

use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\ExpenseItem;
use App\Models\ExpenseMonthValue;
use App\Models\InvestmentItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Multi-step/audited budget version operations. Kept separate from the model
 * so Filament actions and Pest tests call the exact same code path.
 */
class BudgetVersionService
{
    /**
     * Creates a new budget version, empty or copied from any existing version
     * (the "template" — any prior Plan/FC1/FC2, from any budget year). Copied
     * rows keep their origin_id lineage so comparisons can match them across
     * versions. If the template belongs to a DIFFERENT budget year than the
     * new version, investment realization fields (decision_status, purchased,
     * realization_comment) reset — realization tracking restarts each budget
     * year, but carries forward within the same year across Plan→FC1→FC2.
     */
    public static function createFromTemplate(BudgetYear $year, string $type, ?BudgetVersion $template = null, ?array $window = null): BudgetVersion
    {
        // The type suggests a default month window, but the caller (the
        // budget form) may pass an explicit manual override.
        $window ??= BudgetVersion::editableWindowFor($type);

        $version = BudgetVersion::create([
            'budget_year_id' => $year->id,
            'type' => $type,
            'name' => self::labelForType($type) . ' ' . $year->year,
            'baseline_version_id' => $template?->id,
            'editable_from_month' => $window['from'],
            'editable_to_month' => $window['to'],
            'status' => 'DRAFT',
        ]);

        if (! $template) {
            return $version;
        }

        $isNewBudgetYear = $template->budget_year_id !== $year->id;

        DB::transaction(function () use ($version, $template, $isNewBudgetYear) {
            InvestmentItem::withoutLockGuard(function () use ($version, $template, $isNewBudgetYear) {
                foreach ($template->investmentItems as $item) {
                    $version->investmentItems()->create([
                        'origin_id' => $item->origin_id,
                        'month' => $item->month,
                        'entered_by_id' => $item->entered_by_id,
                        'investment_type_id' => $item->investment_type_id,
                        'description' => $item->description,
                        'proposal_comment' => $item->proposal_comment,
                        'quantity' => $item->quantity,
                        'unit_net_price' => $item->unit_net_price,
                        'classification' => $item->classification,
                        'link_or_description' => $item->link_or_description,
                        'decision_status' => $isNewBudgetYear ? 'Proposed' : $item->decision_status,
                        'purchased' => $isNewBudgetYear ? false : $item->purchased,
                        'realization_comment' => $isNewBudgetYear ? '' : $item->realization_comment,
                    ]);
                }
            });

            ExpenseItem::withoutLockGuard(function () use ($version, $template) {
                ExpenseMonthValue::withoutLockGuard(function () use ($version, $template) {
                    foreach ($template->expenseItems as $expense) {
                        $newExpense = $version->expenseItems()->create([
                            'origin_id' => $expense->origin_id,
                            'name' => $expense->name,
                            'account_code' => $expense->account_code,
                            'vendor' => $expense->vendor,
                            'description' => $expense->description,
                            'comment' => $expense->comment,
                            'expense_type' => $expense->expense_type,
                        ]);

                        foreach ($expense->monthValues as $value) {
                            $newExpense->monthValues()->create([
                                'month' => $value->month,
                                'amount' => $value->amount,
                            ]);
                        }
                    }
                });
            });
        });

        return $version->fresh(['investmentItems', 'expenseItems.monthValues']);
    }

    private static function labelForType(string $type): string
    {
        return $type === 'PLAN' ? 'Plan' : $type;
    }

    public static function lock(BudgetVersion $version): BudgetVersion
    {
        if (! in_array($version->status, ['DRAFT', 'TEMPORARILY_UNLOCKED'], true)) {
            throw new RuntimeException('Only a draft or temporarily unlocked version can be locked.');
        }

        $version->update(['status' => 'LOCKED', 'locked_at' => now()]);

        return $version->fresh();
    }

    public static function unlock(BudgetVersion $version, string $reason, User $user): BudgetVersion
    {
        if ($version->status !== 'LOCKED') {
            throw new RuntimeException('Only a locked version can be unlocked.');
        }

        if (trim($reason) === '') {
            throw new RuntimeException('Unlock reason is required.');
        }

        $version->update(['status' => 'TEMPORARILY_UNLOCKED', 'unlocked_at' => now()]);

        $version->unlockEvents()->create([
            'unlocked_by_id' => $user->id,
            'reason' => $reason,
        ]);

        // Custom semantic event (not just an attribute diff) so the reason —
        // which isn't a BudgetVersion column — is captured in the Activity Log.
        activity('budget_planner')
            ->causedBy($user)
            ->performedOn($version)
            ->withProperties(['reason' => $reason])
            ->event('unlocked')
            ->log("Unlocked budget version \"{$version->name}\": {$reason}");

        return $version->fresh();
    }
}
