<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Public front-end sites</x-slot>
        <x-slot name="description">
            Quick links to the public-facing pages. Handy when the front-end navigation links aren't shown.
        </x-slot>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->getLinks() as $link)
                <div class="flex flex-col justify-between rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <div>
                        <div class="font-semibold text-gray-950 dark:text-white">
                            {{ $link['label'] }}
                        </div>

                        @if (! empty($link['description']))
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ $link['description'] }}
                            </p>
                        @endif

                        <p class="mt-2 break-all text-xs text-gray-400 dark:text-gray-500">
                            {{ $link['url'] }}
                        </p>
                    </div>

                    <div class="mt-4">
                        <x-filament::button
                            tag="a"
                            href="{{ $link['url'] }}"
                            target="_blank"
                            rel="noopener"
                            icon="heroicon-o-arrow-top-right-on-square"
                            size="sm"
                        >
                            Open
                        </x-filament::button>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section collapsible collapsed>
        <x-slot name="heading">All routes</x-slot>
        <x-slot name="description">
            Every registered public GET route (admin and internal routes excluded).
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">URI</th>
                        <th class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Name</th>
                        <th class="py-2 font-medium text-gray-500 dark:text-gray-400">Open</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->getAllGetRoutes() as $route)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 pr-4 font-mono text-gray-950 dark:text-white">{{ $route['uri'] }}</td>
                            <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ $route['name'] ?? '—' }}</td>
                            <td class="py-2">
                                <a
                                    href="{{ url($route['uri']) }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-primary-600 hover:underline dark:text-primary-400"
                                >
                                    Open
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
