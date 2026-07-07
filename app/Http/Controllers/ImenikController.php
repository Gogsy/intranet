<?php

namespace App\Http\Controllers;

use App\Models\PhoneNumber;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImenikController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        // Search is performed live on the client (see imenik/index.blade.php), so
        // load the full set the viewer is allowed to see and let JS filter it.
        // $q is only used to pre-fill the search box (e.g. arriving from a link).
        $numbers = $this->baseQuery('')->orderBy('number')->get();

        return view('imenik.index', [
            'numbers' => $numbers,
            'q' => $q,
            'canSeeHidden' => $this->canSeeHidden(),
            'canExport' => $this->canExport(),
        ]);
    }

    /** Finance (and admins) can download the full list. */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($this->canExport(), 403);

        // Finance export is the full inventory — includes free/unassigned numbers.
        $rows = $this->baseQuery(trim((string) $request->query('q', '')), includeFree: true)->orderBy('number')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders Croatian characters (č ć ž š đ) correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Number', 'Type', 'Operator', 'SIM', 'Employee', 'Department', 'Center', 'Public', 'Notes']);
            foreach ($rows as $n) {
                fputcsv($out, [
                    $n->number,
                    $n->numberType?->name,
                    $n->operator?->name,
                    $n->sim_card,
                    $n->employee?->full_name,
                    $n->employee?->department?->name,
                    $n->employee?->center?->name,
                    $n->is_public ? 'yes' : 'no',
                    $n->notes,
                ]);
            }
            fclose($out);
        }, 'imenik-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    private function baseQuery(string $q, bool $includeFree = false)
    {
        $query = PhoneNumber::query()
            ->with(['operator', 'numberType', 'employee.department', 'employee.center']);

        // Free (unassigned) numbers are not shown on the public directory.
        // They are only included in the finance export (full inventory).
        if (! $includeFree) {
            $query->whereNotNull('employee_id');
        }

        // Anonymous visitors only see public numbers; privileged roles see all.
        if (! $this->canSeeHidden()) {
            $query->where('is_public', true)
                // Hide the WHOLE department when it is marked non-public: drop any
                // number whose employee belongs to a hidden department. Numbers with
                // no department (or no employee) are unaffected.
                ->whereDoesntHave('employee.department', fn ($d) => $d->where('is_public', false))
                // Same idea for the whole number type (e.g. hide all "Data" numbers).
                // Numbers with no type are unaffected.
                ->whereDoesntHave('numberType', fn ($t) => $t->where('is_public', false));
        }

        if ($q !== '') {
            // Numbers are stored grouped ("+385 95 741 2358"), so also match on a
            // space/“+”-stripped form when the term contains digits — otherwise a
            // search for "957412358" would miss the formatted value.
            $digits = preg_replace('/\D+/', '', $q);

            $query->where(function ($w) use ($q, $digits) {
                $w->where('number', 'like', "%{$q}%");
                if ($digits !== '') {
                    $w->orWhereRaw("REPLACE(REPLACE(`number`, ' ', ''), '+', '') LIKE ?", ["%{$digits}%"]);
                }
                $w->orWhereHas('employee', fn ($e) => $e->where('full_name', 'like', "%{$q}%"))
                    ->orWhereHas('employee.department', fn ($d) => $d->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('employee.center', fn ($c) => $c->where('name', 'like', "%{$q}%"));
            });
        }

        return $query;
    }

    private function canSeeHidden(): bool
    {
        $u = auth()->user();

        // manage implies view; super_admin passes via Shield's Gate::before.
        return $u && ($u->can('view_phone_book') || $u->can('manage_phone_book'));
    }

    private function canExport(): bool
    {
        return auth()->user()?->can('export_phone_book') ?? false;
    }
}
