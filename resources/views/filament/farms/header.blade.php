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

<style>
    .farm-cover {
        position: relative;
        height: 220px;
        overflow: hidden;
        border-radius: 0.75rem;
        background: #166534;
    }

    .farm-cover img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .farm-cover-shade {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 4rem;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.45), transparent);
        pointer-events: none;
    }

    .farm-cover-edit {
        position: absolute;
        right: 12px;
        bottom: 12px;
    }

    .farm-identity {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 16px;
        padding: 0 4px;
    }

    .farm-logo-wrap {
        position: relative;
        flex: 0 0 auto;
        margin-top: -60px;
    }

    .farm-logo {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 120px;
        height: 120px;
        overflow: hidden;
        border-radius: 999px;
        background: #166534;
        color: #fff;
        font-size: 2rem;
        font-weight: 700;
        box-shadow: 0 0 0 4px #f8fafc;
    }

    html.dark .farm-logo {
        box-shadow: 0 0 0 4px #111827;
    }

    .farm-logo img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .farm-logo-edit {
        position: absolute;
        right: 0;
        bottom: 0;
    }

    .farm-identity-copy {
        min-width: 0;
        flex: 1 1 0;
        padding: 12px 0 4px;
    }

    .farm-name-edit {
        display: flex;
        align-items: flex-start;
        gap: 4px;
        min-width: 0;
    }

    .farm-name-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }

    .farm-name {
        margin: 0;
        color: #030712;
        font-size: 1.5rem;
        line-height: 2rem;
        font-weight: 700;
    }

    html.dark .farm-name {
        color: #f9fafb;
    }

    .farm-place {
        display: flex;
        align-items: center;
        gap: 4px;
        margin: 4px 0 0;
        color: #4b5563;
        font-size: 0.875rem;
    }

    html.dark .farm-place {
        color: #d1d5db;
    }
</style>

<div>
    <div class="farm-cover">
        @if ($cover)
            <img src="{{ $cover }}" alt="">
        @endif
        <div class="farm-cover-shade"></div>
        <div class="farm-cover-edit">
            <x-filament::icon-button
                color="gray"
                size="sm"
                :icon="Heroicon::OutlinedCamera"
                label="Edit cover"
                wire:click="mountAction('editCover')"
            />
        </div>
    </div>

    <div class="farm-identity">
        <div class="farm-logo-wrap">
            <div class="farm-logo">
                @if ($logo)
                    <img src="{{ $logo }}" alt="">
                @else
                    {{ \Illuminate\Support\Str::of($farm->name)->substr(0, 1)->upper() }}
                @endif
            </div>
            <div class="farm-logo-edit">
                <x-filament::icon-button
                    color="gray"
                    size="sm"
                    :icon="Heroicon::OutlinedCamera"
                    label="Edit logo"
                    wire:click="mountAction('editLogo')"
                />
            </div>
        </div>

        <div class="farm-identity-copy">
            <div class="farm-name-row">
                <div class="farm-name-edit">
                    <h2 class="farm-name">{{ $farm->name }}</h2>
                    <x-filament::icon-button
                        color="gray"
                        size="sm"
                        :icon="Heroicon::OutlinedPencil"
                        label="Edit name"
                        wire:click="mountAction('editName')"
                    />
                </div>
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
                <p class="farm-place">
                    <x-filament::icon :icon="Heroicon::OutlinedMapPin" class="h-4 w-4" />
                    <span>{{ $place }}</span>
                </p>
            @endif
        </div>
    </div>
</div>
