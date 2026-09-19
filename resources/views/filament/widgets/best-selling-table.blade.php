<x-filament-widgets::widget>
    <x-filament::section heading="Best-selling produce">
        <div class="grid gap-6 md:grid-cols-2">
            @foreach (['week' => 'This week', 'month' => 'This month'] as $key => $label)
                <div>
                    <h3 class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ $label }}
                    </h3>

                    @if ($$key->isEmpty())
                        <p class="text-sm text-gray-400">No completed orders yet.</p>
                    @else
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 dark:text-gray-400">
                                    <th class="pb-2 font-medium">Crop</th>
                                    <th class="pb-2 text-right font-medium">Units</th>
                                    <th class="pb-2 text-right font-medium">Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($$key as $row)
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="py-2">{{ $row->crop }}</td>
                                        <td class="py-2 text-right">
                                            {{ number_format((float) $row->units, 2) }} {{ $row->unit }}
                                        </td>
                                        <td class="py-2 text-right">
                                            PHP {{ number_format((float) $row->revenue, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
