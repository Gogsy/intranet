<?php

namespace App\Support\InvoiceTracker;

class Months
{
    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];
    }

    public static function name(int $month): string
    {
        return self::options()[$month] ?? (string) $month;
    }

    public static function shortName(int $month): string
    {
        return substr(self::name($month), 0, 3);
    }
}
