@php
    $money = fn (float $value): string => '€' . number_format($value, 2, ',', '.');
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Supplier + category detail — {{ $year }}
        </x-slot>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem; white-space: nowrap;">
                <thead>
                    <tr style="border-bottom: 2px solid rgba(128,128,128,.35);">
                        <th style="text-align: left; padding: 6px 8px;">Supplier</th>
                        <th style="text-align: left; padding: 6px 8px;">Category</th>
                        <th style="text-align: right; padding: 6px 8px;">Spent</th>
                        <th style="text-align: right; padding: 6px 8px;">Share</th>
                        <th style="text-align: right; padding: 6px 8px;">Avg / month</th>
                        <th style="text-align: right; padding: 6px 8px;">Budget (year)</th>
                        <th style="text-align: right; padding: 6px 8px;">Budget Δ</th>
                        <th style="text-align: right; padding: 6px 8px;">Vs. {{ $year - 1 }} (same period)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr style="border-top: 1px solid rgba(128,128,128,.2);">
                            <td style="padding: 6px 8px; font-weight: 600;">{{ $row['supplier'] }}</td>
                            <td style="padding: 6px 8px;">{{ $row['category'] }}</td>
                            <td style="text-align: right; padding: 6px 8px;">{{ $money($row['spent']) }}</td>
                            <td style="text-align: right; padding: 6px 8px; opacity: .8;">
                                {{ $grandTotal > 0 ? number_format($row['spent'] / $grandTotal * 100, 1) . '%' : '—' }}
                            </td>
                            <td style="text-align: right; padding: 6px 8px;">
                                {{ $row['active_months'] > 0 ? $money($row['spent'] / $row['active_months']) : '—' }}
                            </td>
                            <td style="text-align: right; padding: 6px 8px;">{{ $money($row['budget_year']) }}</td>
                            <td style="text-align: right; padding: 6px 8px; {{ $row['delta'] >= 0 ? 'color: rgb(22,163,74);' : 'color: rgb(220,38,38); font-weight: 700;' }}">
                                {{ $money($row['delta']) }}
                            </td>
                            <td style="text-align: right; padding: 6px 8px;">
                                @if ($row['yoy_pct'] === null)
                                    <span style="opacity: .5;">—</span>
                                @else
                                    <span style="display: inline-block; padding: 0 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; {{ $row['yoy_pct'] > 0 ? 'background: rgba(245,158,11,.22); color: rgb(180,83,9);' : 'background: rgba(34,197,94,.16); color: rgb(21,128,61);' }}">
                                        {{ ($row['yoy_pct'] >= 0 ? '+' : '') . number_format($row['yoy_pct'], 1) }}%
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="padding: 12px 8px; text-align: center; opacity: .7;">No entries yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
