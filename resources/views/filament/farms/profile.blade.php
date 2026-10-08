@php
    use App\Enums\Permission;
    use App\Models\Farm;
    use App\Policies\FarmPolicy;

    /** @var Farm $farm */
    $place = collect([$farm->barangay, $farm->municipality])
        ->filter(fn (?string $part): bool => filled($part))
        ->implode(', ');
    $viewer = auth()->user();
    $canOrganic = $viewer !== null && app(FarmPolicy::class)->manageOrganicCertification($viewer, $farm);
    $canStatus = $viewer?->can(Permission::ManageFarms->value) ?? false;
    $cover = $farm->coverPhotoUrl();
    $logo = $farm->logoUrl();
@endphp

<div style="display:grid;gap:20px">
    <div style="position:relative;margin-bottom:28px">
        <div style="height:220px;border-radius:16px;overflow:hidden;background:#166534">
            @if ($cover)
                <img src="{{ $cover }}" alt="" style="width:100%;height:100%;object-fit:cover">
            @endif
        </div>
        <div style="position:absolute;left:24px;bottom:-28px;width:96px;height:96px;border-radius:999px;border:4px solid white;overflow:hidden;background:#166534;display:flex;align-items:center;justify-content:center;color:white;font-weight:700">
            @if ($logo)
                <img src="{{ $logo }}" alt="" style="width:100%;height:100%;object-fit:cover">
            @else
                {{ \Illuminate\Support\Str::of($farm->name)->substr(0, 1)->upper() }}
            @endif
        </div>
        <div style="padding:16px 16px 0 136px;display:grid;gap:8px">
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <h2 style="margin:0;font-size:28px;font-weight:800">{{ $farm->name }}</h2>
                @if ($farm->isOrganicCertified())
                    <span style="border-radius:999px;background:#dcfce7;color:#166534;padding:2px 10px;font-size:12px;font-weight:700">Organic certified</span>
                @endif
                <span style="border-radius:999px;background:{{ $farm->is_active ? '#dcfce7' : '#f3f4f6' }};color:{{ $farm->is_active ? '#166534' : '#374151' }};padding:2px 10px;font-size:12px;font-weight:700">
                    {{ $farm->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
            @if ($place !== '')
                <p style="margin:0;color:#4b5563">{{ $place }}</p>
            @endif
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button type="button" wire:click="mountAction('editCover')">Edit cover</button>
                <button type="button" wire:click="mountAction('editLogo')">Edit logo</button>
                <button type="button" wire:click="mountAction('editName')">Edit name</button>
            </div>
        </div>
    </div>

    <div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">
        <section style="border:1px solid #e5e7eb;border-radius:16px;padding:16px">
            <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
                <h3 style="margin:0">About</h3>
                <button type="button" wire:click="mountAction('editAbout')" aria-label="Edit about">Edit</button>
            </div>
            <p style="white-space:pre-wrap">{{ filled($farm->description) ? $farm->description : 'No description yet.' }}</p>
        </section>

        <section style="border:1px solid #e5e7eb;border-radius:16px;padding:16px">
            <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
                <h3 style="margin:0">Contact & pickup</h3>
                <button type="button" wire:click="mountAction('editContact')" aria-label="Edit contact">Edit</button>
            </div>
            <p style="margin:8px 0">{{ $farm->contact_person ?: 'No contact person yet.' }}</p>
            <p style="margin:8px 0">{{ $farm->contact_number ?: 'No contact number yet.' }}</p>
            <p style="margin:8px 0">{{ $farm->pickup_point ?: 'No pickup point yet.' }}</p>
            <p style="margin:8px 0">{{ collect([$farm->address, $farm->barangay, $farm->municipality])->filter(fn (?string $part): bool => filled($part))->implode(', ') ?: 'No address yet.' }}</p>
        </section>

        <section style="border:1px solid #e5e7eb;border-radius:16px;padding:16px">
            <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
                <h3 style="margin:0">Location</h3>
                <button type="button" wire:click="mountAction('editLocation')" aria-label="Edit location">Edit location</button>
            </div>
            @if ($farm->hasPin())
                <div
                    wire:ignore
                    x-data="farmLocationMap(@js([
                        'latitude' => (float) $farm->latitude,
                        'longitude' => (float) $farm->longitude,
                        'interactive' => false,
                        'latitudePath' => '',
                        'longitudePath' => '',
                    ]))"
                    x-init="bootMap()"
                    style="margin-top:12px"
                >
                    <div wire:ignore x-ref="canvas" style="height:220px;border-radius:12px;overflow:hidden"></div>
                    <p x-text="label" style="margin:8px 0 0;font-size:12px;color:#4b5563"></p>
                </div>
            @else
                <p>No location set yet</p>
            @endif
        </section>

        <section style="border:1px solid #e5e7eb;border-radius:16px;padding:16px">
            <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
                <h3 style="margin:0">Farm features</h3>
                <button type="button" wire:click="mountAction('editFeatures')" aria-label="Edit features">Edit</button>
            </div>
            @foreach (Farm::featureSwitches() as $column => $switch)
                <p style="margin:8px 0">{{ $switch['label'] }}: {{ $farm->{$column} ? 'On' : 'Off' }}</p>
            @endforeach
        </section>

        <section style="border:1px solid #e5e7eb;border-radius:16px;padding:16px">
            <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
                <h3 style="margin:0">Organic certification</h3>
                @if ($canOrganic)
                    <button type="button" wire:click="mountAction('editOrganic')" aria-label="Edit organic certification">Edit</button>
                @endif
            </div>
            @if ($farm->isOrganicCertified())
                <p style="margin:8px 0">{{ $farm->organic_certifier }}</p>
                <p style="margin:8px 0">{{ $farm->organic_certificate_no }}</p>
                <p style="margin:8px 0">Valid until {{ $farm->organic_certified_until?->toFormattedDateString() }}</p>
            @else
                <p>Not certified</p>
                @if (! $canOrganic && (filled($farm->organic_certifier) || filled($farm->organic_certificate_no) || $farm->organic_certified_until))
                    <p style="margin:8px 0">{{ $farm->organic_certifier }}</p>
                    <p style="margin:8px 0">{{ $farm->organic_certificate_no }}</p>
                    <p style="margin:8px 0">{{ $farm->organic_certified_until?->toFormattedDateString() }}</p>
                @endif
            @endif
        </section>

        <section style="border:1px solid #e5e7eb;border-radius:16px;padding:16px">
            <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
                <h3 style="margin:0">Status</h3>
                @if ($canStatus)
                    <button type="button" wire:click="mountAction('editStatus')" aria-label="Edit status">Edit</button>
                @endif
            </div>
            <p>{{ $farm->is_active ? 'Active' : 'Inactive' }}</p>
        </section>
    </div>
</div>
