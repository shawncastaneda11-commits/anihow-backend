<x-filament-panels::page>
    @php
        $account = $this->openRecord();
        $canEdit = $this->canEditOpenAccount();
    @endphp

    <div @class(['anihow-dock', 'anihow-dock-open' => $account !== null])>
        {{-- The list --}}
        <div class="anihow-dock-main">
            <div class="anihow-dock-head">
                <div>
                    <h1 class="anihow-split-heading anihow-dock-title">{{ $this->getTitle() }}</h1>
                    <p class="anihow-muted">{{ $this->intro() }}</p>
                </div>

                <x-filament::actions :actions="$this->getCachedHeaderActions()" />
            </div>

            <div class="anihow-dock-tools">
                <label class="anihow-search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
                    <span class="sr-only">Search accounts</span>
                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="Name, email or phone">
                </label>

                @foreach ($this->getCachedTabs() as $key => $tab)
                    <button
                        type="button"
                        wire:click="$set('activeTab', @js((string) $key))"
                        aria-pressed="{{ $activeTab === (string) $key ? 'true' : 'false' }}"
                        @class(['anihow-chip', 'anihow-chip-on' => $activeTab === (string) $key])
                    >
                        {{ $tab->getLabel() }}
                        @if (filled($badge = $tab->getBadge()))
                            <span class="anihow-chip-count">{{ $badge }}</span>
                        @endif
                    </button>
                @endforeach

                @if ($farms = $this->farmOptions())
                    <label class="anihow-dock-farm">
                        Farm
                        <select wire:model.live="farm" class="anihow-input">
                            <option value="">All farms</option>
                            @foreach ($farms as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            </div>

            {{ $this->content }}
        </div>

        {{-- The open account --}}
        @if ($account)
            <aside class="anihow-dock-panel" aria-label="Account" wire:key="account-panel-{{ $account->getKey() }}">
                <header class="anihow-dock-panel-head">
                    <div class="anihow-dock-who">
                        <span class="anihow-avatar" aria-hidden="true">{{ $this->initials($account) }}</span>
                        <div>
                            <h2 class="anihow-split-name">{{ $account->name }}</h2>
                            <p class="anihow-muted">
                                {{ collect([\App\Enums\Role::tryFrom((string) $account->getRoleNames()->first())?->label(), $account->created_at ? 'joined '.$account->created_at->format('M j') : null])->filter()->implode(' · ') }}
                            </p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeAccount" class="anihow-dock-close" aria-label="Close">×</button>
                </header>

                @if (! $account->isActive())
                    <p class="anihow-dock-note anihow-dock-note-no">Suspended: {{ $account->suspension_reason ?: 'no reason given' }}</p>
                @elseif ($account->must_change_password)
                    <p class="anihow-dock-note anihow-dock-note-wait">Still on a temporary password.</p>
                @endif

                <form wire:submit="saveAccount" class="anihow-dock-form">
                    {{ $this->accountForm }}

                    @if ($canEdit)
                        <div class="anihow-dock-rows">
                            <div class="anihow-dock-row">
                                <div>
                                    <span class="anihow-dock-row-title">Temporary password</span>
                                    <span class="anihow-dock-row-hint">They must change it at the next sign-in</span>
                                </div>
                                {{ $this->setTemporaryPasswordAction }}
                            </div>
                            <div class="anihow-dock-row">
                                <div>
                                    <span class="anihow-dock-row-title">Sign out everywhere</span>
                                    <span class="anihow-dock-row-hint">For a lost or shared phone</span>
                                </div>
                                {{ $this->signOutEverywhereAction }}
                            </div>
                            @if ($account->isActive())
                                @if ($this->suspendOpenAccountAction->isVisible())
                                    <div class="anihow-dock-row anihow-dock-row-danger">
                                        <div>
                                            <span class="anihow-dock-row-title">Suspend account</span>
                                            <span class="anihow-dock-row-hint">They can't use AniHow until you lift it</span>
                                        </div>
                                        {{ $this->suspendOpenAccountAction }}
                                    </div>
                                @endif
                            @elseif ($this->liftSuspensionAction->isVisible())
                                <div class="anihow-dock-row">
                                    <div>
                                        <span class="anihow-dock-row-title">Lift the suspension</span>
                                        <span class="anihow-dock-row-hint">They can sign in again</span>
                                    </div>
                                    {{ $this->liftSuspensionAction }}
                                </div>
                            @endif
                        </div>
                    @endif

                    <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl(auth()->user()?->can('update', $account) ? 'edit' : 'view', ['record' => $account]) }}" class="anihow-dock-more">
                        All details{{ $account->isFarmerSeller() ? ', storefront and crops' : '' }}
                    </a>

                    <div class="anihow-dock-foot">
                        <button type="button" wire:click="closeAccount" class="anihow-btn anihow-btn-quiet">{{ $canEdit ? 'Cancel' : 'Close' }}</button>
                        @if ($canEdit)
                            <button type="submit" wire:loading.attr="disabled" class="anihow-btn anihow-btn-primary">Save changes</button>
                        @endif
                    </div>
                </form>
            </aside>
        @endif
    </div>
</x-filament-panels::page>
