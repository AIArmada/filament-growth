<x-filament-widgets::widget>
    <x-slot name="heading">
        Recent Winners
    </x-slot>

    @php($experiments = $this->getExperimentSnapshots())

    @if($experiments === [])
        <div class="p-4 text-sm text-gray-500 dark:text-gray-400">
            No experiments have produced results yet.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="px-4 py-3 text-left">Experiment</th>
                        <th class="px-4 py-3 text-left">Preset</th>
                        <th class="px-4 py-3 text-left">Winner</th>
                        <th class="px-4 py-3 text-right">Metric</th>
                        <th class="px-4 py-3 text-right">Revenue</th>
                        <th class="px-4 py-3 text-right">Assignments</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($experiments as $experiment)
                        <tr class="border-b border-gray-100 dark:border-gray-900">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $experiment['name'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $experiment['status'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $experiment['module_type'] }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                <a href="{{ $experiment['results_url'] }}" class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                    {{ $experiment['winner_name'] }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $experiment['winner_metric_label'] }}</div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $experiment['winner_metric_value'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-gray-100">{{ $experiment['revenue'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-200">{{ $experiment['assignments'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-widgets::widget>