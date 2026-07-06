<x-filament-panels::page>
    {{--
        No custom Filament theme is compiled for this app, so Tailwind utility
        classes are NOT available in custom views — style everything with the
        scoped classes below (same approach as the Budget Planner overrides).
    --}}
    <style>
        .sm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
            gap: 1rem;
        }
        .sm-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem;
            border-radius: .75rem;
            border: 1px solid rgba(0, 0, 0, .08);
            background: #fff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .05);
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .sm-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .10);
        }
        .dark .sm-card {
            border-color: rgba(255, 255, 255, .10);
            background: rgba(255, 255, 255, .03);
        }
        .sm-card-head {
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .sm-card-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: .5rem;
            border: 1px solid rgba(0, 0, 0, .08);
            background: rgba(0, 0, 0, .04);
        }
        .dark .sm-card-icon {
            border-color: rgba(255, 255, 255, .10);
            background: rgba(255, 255, 255, .06);
        }
        .sm-card-icon svg { width: 1.25rem; height: 1.25rem; }
        .sm-card-title {
            font-weight: 600;
            font-size: .875rem;
            line-height: 1.25rem;
        }
        .sm-card-desc {
            margin-top: .125rem;
            font-size: .8125rem;
            line-height: 1.125rem;
            opacity: .6;
        }
        .sm-card-url {
            display: block;
            padding: .375rem .625rem;
            border-radius: .5rem;
            border: 1px solid rgba(0, 0, 0, .06);
            background: rgba(0, 0, 0, .03);
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: .75rem;
            opacity: .7;
            word-break: break-all;
        }
        .dark .sm-card-url {
            border-color: rgba(255, 255, 255, .08);
            background: rgba(255, 255, 255, .04);
        }
        .sm-table-wrap {
            overflow-x: auto;
            border-radius: .5rem;
            border: 1px solid rgba(0, 0, 0, .08);
        }
        .dark .sm-table-wrap { border-color: rgba(255, 255, 255, .10); }
        .sm-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .875rem;
            text-align: start;
        }
        .sm-table th {
            padding: .625rem 1rem;
            font-size: .75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .03em;
            text-align: start;
            opacity: .55;
            background: rgba(0, 0, 0, .03);
        }
        .dark .sm-table th { background: rgba(255, 255, 255, .04); }
        .sm-table td {
            padding: .625rem 1rem;
            border-top: 1px solid rgba(0, 0, 0, .06);
        }
        .dark .sm-table td { border-top-color: rgba(255, 255, 255, .06); }
        .sm-table tbody tr:hover { background: rgba(0, 0, 0, .025); }
        .dark .sm-table tbody tr:hover { background: rgba(255, 255, 255, .03); }
        .sm-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        .sm-dim { opacity: .6; }
        .sm-right { text-align: end; }
    </style>

    <x-filament::section>
        <x-slot name="heading">Public front-end sites</x-slot>
        <x-slot name="description">
            Quick links to the public-facing pages. Handy when the front-end navigation links aren't shown.
        </x-slot>

        <div class="sm-grid">
            @foreach ($this->getLinks() as $link)
                <div class="sm-card">
                    <div>
                        <div class="sm-card-head">
                            <div class="sm-card-icon">
                                <x-filament::icon :icon="$link['icon']" />
                            </div>

                            <div style="min-width: 0;">
                                <div class="sm-card-title">{{ $link['label'] }}</div>

                                @if (! empty($link['description']))
                                    <div class="sm-card-desc">{{ $link['description'] }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="sm-card-url" style="margin-top: .75rem;">{{ $link['url'] }}</div>
                    </div>

                    <div>
                        <x-filament::button
                            tag="a"
                            href="{{ $link['url'] }}"
                            target="_blank"
                            rel="noopener"
                            icon="heroicon-o-arrow-top-right-on-square"
                            size="sm"
                            color="gray"
                            outlined
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

        <div class="sm-table-wrap">
            <table class="sm-table">
                <thead>
                    <tr>
                        <th>URI</th>
                        <th>Name</th>
                        <th>Access</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->getAllGetRoutes() as $route)
                        <tr>
                            <td class="sm-mono">{{ $route['uri'] }}</td>
                            <td class="sm-dim">{{ $route['name'] ?? '—' }}</td>
                            <td>
                                @if ($route['access'] === 'auth')
                                    <x-filament::badge color="warning" size="sm">Requires login</x-filament::badge>
                                @elseif ($route['access'] === 'guest')
                                    <x-filament::badge color="info" size="sm">Guest only</x-filament::badge>
                                @else
                                    <x-filament::badge color="success" size="sm">Public</x-filament::badge>
                                @endif
                            </td>
                            <td class="sm-right">
                                <x-filament::link
                                    tag="a"
                                    href="{{ url($route['uri']) }}"
                                    target="_blank"
                                    rel="noopener"
                                    icon="heroicon-m-arrow-top-right-on-square"
                                    size="sm"
                                >
                                    Open
                                </x-filament::link>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
