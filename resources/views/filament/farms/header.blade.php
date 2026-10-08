@php
    use App\Enums\Permission;
    use App\Models\Farm;
    use Filament\Support\Icons\Heroicon;

    /** @var Farm $farm */
    $place = collect([$farm->barangay, $farm->municipality])
        ->filter(fn (?string $part): bool => filled($part))
        ->implode(', ');
    $viewer = auth()->user();
    $canStatus = $viewer?->can(Permission::ManageFarms->value) ?? false;
    $cover = $farm->coverPhotoUrl();
    $logo = $farm->logoUrl();
@endphp

<div class="mb-2">
    <div class="relative h-[220px] overflow-hidden rounded-xl bg-primary-600">
        @if ($cover)
            <img src="{{ $cover }}" alt="" class="h-full w-full object-cover">
        @endif
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-black/45 to-transparent"></div>
        <div class="absolute bottom-3 right-3">
            <x-filament::icon-button
                color="gray"
                size="sm"
                :icon="Heroicon::OutlinedCamera"
                label="Edit cover"
                wire:click="mountAction('editCover')"
            />
        </div>
    </div>

    <div class="flex flex-wrap items-end gap-4 px-1 sm:px-2">
        <div class="relative -mt-[60px] shrink-0">
            <div class="flex size-[120px] items-center justify-center overflow-hidden rounded-full bg-primary-600 text-3xl font-bold text-white ring-4 ring-gray-50 dark:ring-gray-950">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" class="h-full w-full object-cover">
                @else
                    {{ \Illuminate\Support\Str::of($farm->name)->substr(0, 1)->upper() }}
                @endif
            </div>
            <div class="absolute bottom-0 right-0">
                <x-filament::icon-button
                    color="gray"
                    size="sm"
                    :icon="Heroicon::OutlinedCamera"
                    label="Edit logo"
                    wire:click="mountAction('editLogo')"
                />
            </div>
        </div>

        <div class="min-w-0 flex-1 pb-1 pt-3">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-2xl font-bold text-gray-950 dark:text-white">{{ $farm->name }}</h2>
                <x-filament::icon-button
                    color="gray"
                    size="sm"
                    :icon="Heroicon::OutlinedPencil"
                    label="Edit name"
                    wire:click="mountAction('editName')"
                />
                <x-filament::badge :color="$farm->is_active ? 'success' : 'gray'" size="sm">
                    {{ $farm->is_active ? 'Active' : 'Inactive' }}
                </x-filament::badge>
                @if ($farm->isOrganicCertified())
                    <x-filament::badge color="success" size="sm">
                        Organic certified
                    </x-filament::badge>
                @endif
                @if ($canStatus)
                    <x-filament::button
                        size="sm"
                        color="gray"
                        outlined
                        wire:click="mountAction('editStatus')"
                    >
                        {{ $farm->is_active ? 'Set inactive' : 'Set active' }}
                    </x-filament::button>
                @endif
            </div>
            @if ($place !== '')
                <p class="mt-1 flex items-center gap-1 text-sm text-gray-600 dark:text-gray-300">
                    <x-filament::icon :icon="Heroicon::OutlinedMapPin" class="h-4 w-4" />
                    <span>{{ $place }}</span>
                </p>
            @endif
        </div>
    </div>
</div>
