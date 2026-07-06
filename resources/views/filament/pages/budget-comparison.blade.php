<x-filament-panels::page>
    {{ $this->form }}

    @php $rows = $this->getRows(); @endphp

    <div class="mt-6 fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-x-auto">
        @if ($rows->isEmpty())
            <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                Select an old and a new budget to compare.
            </div>
        @else
            <table class="fi-ta-table w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="px-4 py-2 font-medium">Kind</th>
                        <th class="px-4 py-2 font-medium">Description</th>
                        <th class="px-4 py-2 font-medium">Category</th>
                        <th class="px-4 py-2 font-medium">Vendor</th>
                        <th class="px-4 py-2 font-medium">Account</th>
                        <th class="px-4 py-2 font-medium">Month</th>
                        <th class="px-4 py-2 font-medium text-right">Old value</th>
                        <th class="px-4 py-2 font-medium text-right">New value</th>
                        <th class="px-4 py-2 font-medium text-right">Difference</th>
                        <th class="px-4 py-2 font-medium text-right">%</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-gray-100 dark:border-white/5">
                            <td class="px-4 py-2">{{ $row['kind'] === 'investment' ? 'Investment' : 'Expense' }}</td>
                            <td class="px-4 py-2">{{ $row['label'] }}</td>
                            <td class="px-4 py-2">{{ $row['category'] }}</td>
                            <td class="px-4 py-2">{{ $row['vendor'] }}</td>
                            <td class="px-4 py-2">{{ $row['account_code'] }}</td>
                            <td class="px-4 py-2">{{ $row['month'] }}</td>
                            <td class="px-4 py-2 text-right">{{ \Illuminate\Support\Number::currency($row['old_total'], 'EUR') }}</td>
                            <td class="px-4 py-2 text-right">{{ \Illuminate\Support\Number::currency($row['new_total'], 'EUR') }}</td>
                            <td class="px-4 py-2 text-right">{{ \Illuminate\Support\Number::currency($row['difference'], 'EUR') }}</td>
                            <td class="px-4 py-2 text-right">{{ $row['percentage_difference'] === null ? '—' : $row['percentage_difference'] . '%' }}</td>
                            <td class="px-4 py-2">
                                <x-filament::badge :color="\App\Filament\Pages\BudgetComparison::statusColor($row['status'])">
                                    {{ \App\Filament\Pages\BudgetComparison::statusLabel($row['status']) }}
                                </x-filament::badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-filament-panels::page>
