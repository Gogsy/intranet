@php
    $money = fn (float $value): string => '€' . number_format($value, 2, ',', '.');

    $deltaStyle = fn (float $delta): string => $delta >= 0
        ? 'color: rgb(22,163,74); font-weight: 600;'
        : 'color: rgb(220,38,38); font-weight: 700;';

    $usedBadge = function (?float $pct): array {
        if ($pct === null) {
            return ['no budget', 'background: rgba(128,128,128,.15); color: inherit; opacity: .7;'];
        }

        $style = match (true) {
            $pct > 100 => 'background: rgba(239,68,68,.18); color: rgb(185,28,28);',
            $pct >= 90 => 'background: rgba(245,158,11,.22); color: rgb(180,83,9);',
            default => 'background: rgba(34,197,94,.16); color: rgb(21,128,61);',
        };

        return [number_format($pct, 0) . '%', $style];
    };
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Budget vs. actual — {{ $year }}
        </x-slot>

        <x-slot name="afterHeader">
            <x-filament::button
                tag="a"
                href="{{ route('exports.budget-vs-actual', ['year' => $year]) }}"
                icon="heroicon-o-arrow-down-tray"
                color="gray"
                size="sm"
            >
                Export Excel
            </x-filament::button>
        </x-slot>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem; white-space: nowrap;">
                <thead>
                    <tr style="border-bottom: 2px solid rgba(128,128,128,.35);">
                        <th style="text-align: left; padding: 6px 8px;">Supplier / category</th>
                        <th style="text-align: right; padding: 6px 8px;">Budget YTD</th>
                        <th style="text-align: right; padding: 6px 8px;">Spent</th>
                        <th style="text-align: right; padding: 6px 8px;">Δ YTD</th>
                        <th style="text-align: right; padding: 6px 8px;">Budget (year)</th>
                        <th style="text-align: right; padding: 6px 8px;">Used</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr style="border-top: 1px solid rgba(128,128,128,.3);">
                            <td style="padding: 6px 8px; font-weight: 700;">{{ $row['supplier'] }}</td>
                            <td style="text-align: right; padding: 6px 8px; font-weight: 600;">{{ $money($row['budget_ytd']) }}</td>
                            <td style="text-align: right; padding: 6px 8px; font-weight: 600;">{{ $money($row['spent']) }}</td>
                            <td style="text-align: right; padding: 6px 8px; {{ $deltaStyle($row['delta_ytd']) }}">{{ $money($row['delta_ytd']) }}</td>
                            <td style="text-align: right; padding: 6px 8px; font-weight: 600;">{{ $money($row['budget_year']) }}</td>
                            @php [$label, $style] = $usedBadge($row['used_pct']); @endphp
                            <td style="text-align: right; padding: 6px 8px;">
                                <span style="display: inline-block; padding: 1px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; {{ $style }}">{{ $label }}</span>
                            </td>
                        </tr>
                        @foreach ($row['categories'] as $category)
                            <tr style="font-size: 0.75rem;">
                                <td style="padding: 3px 8px 3px 24px; opacity: .85;">{{ $category['label'] }}</td>
                                <td style="text-align: right; padding: 3px 8px; opacity: .85;">{{ $money($category['budget_ytd']) }}</td>
                                <td style="text-align: right; padding: 3px 8px; opacity: .85;">{{ $money($category['spent']) }}</td>
                                <td style="text-align: right; padding: 3px 8px; {{ $deltaStyle($category['delta_ytd']) }}">{{ $money($category['delta_ytd']) }}</td>
                                <td style="text-align: right; padding: 3px 8px; opacity: .85;">{{ $money($category['budget_year']) }}</td>
                                @php [$label, $style] = $usedBadge($category['used_pct']); @endphp
                                <td style="text-align: right; padding: 3px 8px;">
                                    <span style="display: inline-block; padding: 0 8px; border-radius: 9999px; font-size: 0.6875rem; font-weight: 600; {{ $style }}">{{ $label }}</span>
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 12px 8px; text-align: center; opacity: .7;">No suppliers yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
