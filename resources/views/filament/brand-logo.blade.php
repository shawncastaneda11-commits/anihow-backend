{{-- Sidebar uses the leaf mark only. Login keeps the full lockup. --}}
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
    <div class="anihow-brand-panel">
        <img
            class="anihow-mark"
            src="/images/anihow-mark.png?v=12"
            alt="AniHow"
        >
    </div>
@endif
