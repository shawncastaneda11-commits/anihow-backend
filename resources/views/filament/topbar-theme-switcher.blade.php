{{-- Visible top-bar theme controls (no dropdown close() dependency). --}}
<div
    x-data="{
        theme: localStorage.getItem('theme') || @js(filament()->getDefaultThemeMode()->value),
        setTheme(value) {
            this.theme = value
            localStorage.setItem('theme', value)
            $dispatch('theme-changed', value)
        }
    }"
    role="group"
    aria-label="{{ __('filament-panels::layout.actions.theme_switcher.label') }}"
    class="anihow-topbar-theme fi-theme-switcher"
>
    <button
        type="button"
        class="fi-theme-switcher-btn"
        x-bind:class="{ 'fi-active': theme === 'light' }"
        x-bind:aria-pressed="theme === 'light' ? 'true' : 'false'"
        aria-label="{{ __('filament-panels::layout.actions.theme_switcher.light.label') }}"
        x-on:click="setTheme('light')"
    >
        <x-filament::icon icon="heroicon-m-sun" class="h-5 w-5" />
    </button>

    <button
        type="button"
        class="fi-theme-switcher-btn"
        x-bind:class="{ 'fi-active': theme === 'dark' }"
        x-bind:aria-pressed="theme === 'dark' ? 'true' : 'false'"
        aria-label="{{ __('filament-panels::layout.actions.theme_switcher.dark.label') }}"
        x-on:click="setTheme('dark')"
    >
        <x-filament::icon icon="heroicon-m-moon" class="h-5 w-5" />
    </button>

    <button
        type="button"
        class="fi-theme-switcher-btn"
        x-bind:class="{ 'fi-active': theme === 'system' }"
        x-bind:aria-pressed="theme === 'system' ? 'true' : 'false'"
        aria-label="{{ __('filament-panels::layout.actions.theme_switcher.system.label') }}"
        x-on:click="setTheme('system')"
    >
        <x-filament::icon icon="heroicon-m-computer-desktop" class="h-5 w-5" />
    </button>
</div>
