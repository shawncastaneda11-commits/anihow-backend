{{-- Sidebar and top bar: the leaf mark, "AniHow" and the office. Login keeps the full lockup. --}}
@if (request()->is('admin/login'))
    <div class="anihow-brand-login">
        <img
            class="anihow-mark"
            src="/images/anihow-mark.png?v=12"
            alt="AniHow"
        >
        <img
            class="anihow-wordmark"
            src="/images/anihow-wordmark-name.png?v=12"
            alt=""
        >
        <p class="anihow-tagline">FROM FARM TO MARKET</p>
    </div>
@else
    @php($office = auth()->user()?->officeLabel())
    <div class="anihow-brand-panel">
        <img
            class="anihow-mark"
            src="/images/anihow-mark.png?v=12"
            alt=""
        >
        <span class="anihow-brand-text">
            <span class="anihow-brand-name">AniHow</span>
            @if ($office)
                <span class="anihow-brand-office">{{ $office }}</span>
            @endif
        </span>
    </div>
@endif
