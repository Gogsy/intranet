<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use Throwable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneNumber extends Model
{
    use LogsModelActivity;

    protected $fillable = [
        'number', 'sim_card', 'notes',
        'operator_id', 'number_type_id', 'employee_id', 'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Normalise phone numbers to international grouped format on save, e.g.
     * "+385957412358" -> "+385 95 741 2358". Numbers without a leading "+" are
     * parsed as Croatian (region HR). Runs for every save, including CSV import
     * (which goes through PhoneNumber::create()). On any parse failure we fall
     * back to a lightly cleaned value so a save never crashes.
     */
    public function setNumberAttribute($value): void
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            $this->attributes['number'] = $raw;
            return;
        }

        try {
            $util = PhoneNumberUtil::getInstance();
            // If it already starts with +, region is ignored; otherwise default HR.
            $proto = $util->parse($raw, 'HR');

            if ($util->isValidNumber($proto)) {
                $this->attributes['number'] = $util->format(
                    $proto,
                    PhoneNumberFormat::INTERNATIONAL
                );
                return;
            }
        } catch (Throwable $e) {
            // fall through to the cleaned fallback below
        }

        // Fallback: keep digits and a leading +, collapse other junk.
        $cleaned = preg_replace('/[^\d+]/', '', $raw);
        $this->attributes['number'] = $cleaned !== '' ? $cleaned : $raw;
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function numberType(): BelongsTo
    {
        return $this->belongsTo(NumberType::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** "Free" numbers = not assigned to any employee. */
    public function scopeFree(Builder $q): Builder
    {
        return $q->whereNull('employee_id');
    }

    public function scopeAssigned(Builder $q): Builder
    {
        return $q->whereNotNull('employee_id');
    }

    /** Public numbers = visible to anonymous visitors. */
    public function scopePublic(Builder $q): Builder
    {
        return $q->where('is_public', true);
    }

    public function getIsFreeAttribute(): bool
    {
        return $this->employee_id === null;
    }
}
