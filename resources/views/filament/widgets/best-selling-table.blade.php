@php
    $filters = $this->getFilters();
@endphp

<x-filament-widgets::widget class="fi-wi-table">
    <x-filament::section heading="Best-selling produce">
        @if ($filters)
            <x-slot name="afterHeader">
                <x-filament::input.wrapper
                    inline-prefix
                    wire:target="filter"
                    class="fi-wi-chart-filter"
                >
                    <x-filament::input.select
                        :aria-label="__('filament-widgets::chart.filter.label')"
                        inline-prefix
                        wire:model.live="filter"
                    >
                        @foreach ($filters as $value => $label)
                            <option value="{{ $value }}">
                                {{ $label }}
                            </option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </x-slot>
        @endif

        {{ $this->table }}
    </x-filament::section>
</x-filament-widgets::widget>
