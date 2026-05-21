<x-filament-panels::page>
    {{ $this->form }}

    @if($experiment)
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4 mt-6">
            <x-filament::card>
                <div class="text-sm text-gray-500 dark:text-gray-400">Assignments</div>
                <div class="text-2xl font-semibold">{{ number_format($results['totals']['assignments'] ?? 0) }}</div>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $this->moduleLabel() }} preset</div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-sm text-gray-500 dark:text-gray-400">Purchases</div>
                <div class="text-2xl font-semibold">{{ number_format($results['totals']['purchases'] ?? 0) }}</div>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Goal: {{ $experiment->goal_event_name }}</div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-sm text-gray-500 dark:text-gray-400">Tracked Revenue</div>
                <div class="text-2xl font-semibold">{{ $this->formatMoney((int) ($results['totals']['revenue_minor'] ?? 0)) }}</div>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Refunds: {{ number_format($results['totals']['refunds'] ?? 0) }}</div>
            </x-filament::card>

            <x-filament::card>
                <div class="text-sm text-gray-500 dark:text-gray-400">Winner Metric</div>
                <div class="text-2xl font-semibold">{{ $winnerMetricLabel }}</div>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Property: {{ $experiment->trackedProperty?->name ?? 'Tracked Property' }}</div>
            </x-filament::card>
        </div>

        <x-filament::section class="mt-6">
            <x-slot name="heading">Winner Summary</x-slot>

            @if($winnerSummary)
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                    <div class="rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-800 dark:bg-success-950/20 lg:col-span-2">
                        <div class="text-sm text-success-700 dark:text-success-300">Current winner</div>
                        <div class="mt-2 text-2xl font-semibold text-success-900 dark:text-success-100">
                            {{ $winnerSummary['name'] }}
                            @if($winnerSummary['code'])
                                <span class="text-sm font-medium text-success-700 dark:text-success-300">({{ $winnerSummary['code'] }})</span>
                            @endif
                        </div>
                        <div class="mt-2 text-sm text-success-700 dark:text-success-300">
                            Leading on {{ $winnerSummary['winner_metric_label'] }} with {{ $winnerSummary['winner_metric_value'] }}.
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Revenue</div>
                        <div class="mt-2 text-xl font-semibold">{{ $winnerSummary['revenue'] }}</div>
                    </div>

                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Conversion Rate</div>
                        <div class="mt-2 text-xl font-semibold">{{ $winnerSummary['conversion_rate'] }}</div>
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $winnerSummary['purchases'] }} purchase(s)</div>
                    </div>
                </div>
            @else
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    No winner yet — this experiment needs assignments and qualifying events before a leader can be declared.
                </div>
            @endif
        </x-filament::section>

        <x-filament::section class="mt-6">
            <x-slot name="heading">Per-Variant {{ $chartMetricLabel }} Chart</x-slot>

            <div class="space-y-4">
                @forelse($variantComparison as $variant)
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-4">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $variant['name'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Code: {{ $variant['code'] }}</div>
                            </div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $variant['value_label'] }}</div>
                        </div>

                        <div class="h-3 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <div
                                class="h-3 rounded-full {{ $variant['color_class'] }}"
                                style="width: {{ $variant['percent'] }}%"
                            ></div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        No variant data has been recorded yet.
                    </div>
                @endforelse
            </div>
        </x-filament::section>

        <x-filament::section class="mt-6">
            <x-slot name="heading">Variant Breakdown</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="px-4 py-3 text-left">Variant</th>
                            <th class="px-4 py-3 text-right">Assignments</th>
                            <th class="px-4 py-3 text-right">Checkout Starts</th>
                            <th class="px-4 py-3 text-right">Purchases</th>
                            <th class="px-4 py-3 text-right">Revenue</th>
                            <th class="px-4 py-3 text-right">Conversion Rate</th>
                            <th class="px-4 py-3 text-right">Revenue / Visitor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results['variants'] ?? [] as $variant)
                            @php($isWinner = ($results['winner_variant_id'] ?? null) === ($variant['variant_id'] ?? null))
                            <tr class="border-b border-gray-100 dark:border-gray-900 {{ $isWinner ? 'bg-success-50/60 dark:bg-success-950/20' : '' }}">
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $variant['name'] }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $variant['code'] }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format((int) ($variant['assignments'] ?? 0)) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((int) ($variant['checkout_starts'] ?? 0)) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((int) ($variant['purchases'] ?? 0)) }}</td>
                                <td class="px-4 py-3 text-right">{{ $this->formatMoney((int) ($variant['revenue_minor'] ?? 0)) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format(((float) ($variant['conversion_rate'] ?? 0)) * 100, 2) }}%</td>
                                <td class="px-4 py-3 text-right">{{ $this->formatMoney((int) round((float) ($variant['revenue_per_visitor'] ?? 0))) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @else
        <x-filament::section class="mt-6">
            <x-slot name="heading">No Experiment Selected</x-slot>

            <div class="text-sm text-gray-500 dark:text-gray-400">
                Create an experiment first, or select one above to see winner summaries and variant performance.
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>