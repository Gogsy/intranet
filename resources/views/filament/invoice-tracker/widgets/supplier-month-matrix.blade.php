@php
    $cellStyle = function (array $cell): string {
        if ($cell['missing'] ?? false) {
            return 'background: rgba(239,68,68,.18);';
        }

        if ($cell['over']) {
            return 'background: rgba(245,158,11,.25); font-weight: 700;';
        }

        if ($cell['under']) {
            return 'background: rgba(34,197,94,.14);';
        }

        return '';
    };
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Suppliers × months — {{ $year }} (EUR)
        </x-slot>

        <x-slot name="description">
            @if ($planSource)
                Planned amounts from budget: <strong>{{ $planSource }}</strong>
            @else
                No budget version linked for {{ $year }} — planned amounts are manual only.
            @endif
        </x-slot>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem; white-space: nowrap;">
                <thead>
                    <tr style="border-bottom: 2px solid rgba(128,128,128,.35);">
                        <th style="text-align: left; padding: 6px 8px;">Supplier / category</th>
                        @foreach (\App\Support\InvoiceTracker\Months::options() as $m => $name)
                            <th style="text-align: right; padding: 6px 8px;">{{ substr($name, 0, 3) }}</th>
                        @endforeach
                        <th style="text-align: right; padding: 6px 8px; font-weight: 700;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr style="border-top: 1px solid rgba(128,128,128,.3);">
                            <td style="padding: 6px 8px; font-weight: 700;">
                                {{ $row['supplier']->name }}
                                @unless ($row['supplier']->is_active)
                                    <span style="opacity: .6;">(inactive)</span>
                                @endunless
                            </td>
                            @foreach ($row['cells'] as $cell)
                                <td style="text-align: right; padding: 6px 8px; font-weight: 600; {{ $cellStyle($cell) }}">
                                    {{ $cell['amount'] !== null ? number_format($cell['amount'], 0, ',', '.') : '—' }}
                                </td>
                            @endforeach
                            <td style="text-align: right; padding: 6px 8px; font-weight: 700;">
                                {{ number_format($row['total'], 0, ',', '.') }}
                            </td>
                        </tr>
                        @foreach ($row['categories'] as $category)
                            <tr style="font-size: 0.75rem;">
                                <td style="padding: 3px 8px 3px 24px; opacity: .85;">
                                    {{ $category['label'] }}
                                </td>
                                @foreach ($category['cells'] as $cell)
                                    <td style="text-align: right; padding: 3px 8px; opacity: .95; {{ $cellStyle($cell) }}">
                                        {{ $cell['amount'] !== null ? number_format($cell['amount'], 0, ',', '.') : '—' }}
                                    </td>
                                @endforeach
                                <td style="text-align: right; padding: 3px 8px; opacity: .85;">
                                    {{ number_format($category['total'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="14" style="padding: 12px 8px; text-align: center; opacity: .7;">
                                No suppliers yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (count($rows))
                    <tfoot>
                        <tr style="border-top: 2px solid rgba(128,128,128,.35); font-weight: 700;">
                            <td style="padding: 6px 8px;">Total</td>
                            @foreach ($columnTotals as $total)
                                <td style="text-align: right; padding: 6px 8px;">{{ number_format($total, 0, ',', '.') }}</td>
                            @endforeach
                            <td style="text-align: right; padding: 6px 8px;">{{ number_format($grandTotal, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <div style="margin-top: 8px; font-size: 0.75rem; opacity: .8; display: flex; gap: 16px; flex-wrap: wrap;">
            <span><span style="display: inline-block; width: 10px; height: 10px; background: rgba(239,68,68,.5); border-radius: 2px;"></span> missing entry (finished month, expected-monthly supplier)</span>
            <span><span style="display: inline-block; width: 10px; height: 10px; background: rgba(245,158,11,.6); border-radius: 2px;"></span> over monthly budget</span>
            <span><span style="display: inline-block; width: 10px; height: 10px; background: rgba(34,197,94,.4); border-radius: 2px;"></span> under monthly budget</span>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
