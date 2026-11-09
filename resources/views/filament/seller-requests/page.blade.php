<x-filament-panels::page>
    @if ($this->decides())
        {{-- COSEL: the queue and the decision --}}
        @php
            $items = $this->items();
            $request = $this->selected();
        @endphp

        <div class="anihow-split">
            <section class="anihow-split-list" aria-label="Seller requests">
                <div class="anihow-split-intro">
                    <h1 class="anihow-split-heading">{{ $this->getTitle() }}</h1>
                    <p class="anihow-muted">{{ $this->intro() }}</p>
                </div>

                <div class="anihow-split-tabs" role="tablist" aria-label="Waiting or decided">
                    <button type="button" role="tab" wire:click="showTab('waiting')" aria-selected="{{ $tab === 'waiting' ? 'true' : 'false' }}" @class(['anihow-chip', 'anihow-chip-on' => $tab === 'waiting'])>
                        Waiting <span class="anihow-chip-count">{{ $this->waitingCount() }}</span>
                    </button>
                    <button type="button" role="tab" wire:click="showTab('decided')" aria-selected="{{ $tab === 'decided' ? 'true' : 'false' }}" @class(['anihow-chip', 'anihow-chip-on' => $tab === 'decided'])>
                        Decided
                    </button>
                </div>

                @forelse ($items as $item)
                    <button
                        type="button"
                        wire:key="seller-request-{{ $item->id }}"
                        wire:click="select({{ $item->id }})"
                        @class(['anihow-split-item', 'anihow-split-item-on' => $request?->is($item)])
                        aria-current="{{ $request?->is($item) ? 'true' : 'false' }}"
                    >
                        <span class="anihow-split-item-title">{{ $item->name }}</span>
                        <span class="anihow-split-item-meta">
                            {{ $item->farm?->name ?? 'No farm' }} ·
                            {{ $tab === 'decided' ? $item->status->label().' '.$item->decided_at?->diffForHumans() : $item->created_at?->diffForHumans() }}
                        </span>
                    </button>
                @empty
                    <p class="anihow-muted anihow-split-empty">{{ $tab === 'decided' ? 'Nothing decided yet.' : 'No requests waiting. You are all caught up.' }}</p>
                @endforelse
            </section>

            <section class="anihow-split-panel" aria-label="Decision">
                @if ($created)
                    <div class="anihow-card anihow-created" role="status">
                        <h2 class="anihow-split-name">Account created for {{ $created['name'] }}</h2>
                        <p class="anihow-muted">Farmer-seller at {{ $created['farm'] }}. Hand them this temporary password in person. It is shown only now; they change it at their first sign-in.</p>
                        <div class="anihow-password" x-data>
                            <code>{{ $created['password'] }}</code>
                            <button type="button" class="anihow-btn anihow-btn-quiet" x-on:click="navigator.clipboard.writeText(@js($created['password'])); $el.textContent = 'Copied'">Copy</button>
                        </div>
                        <p class="anihow-muted">{{ $created['email'] }}. The Content Editor is told the account was added, without the password.</p>
                        <div class="anihow-id-actions">
                            <button type="button" wire:click="finishCreated" class="anihow-btn anihow-btn-primary">Done, open the next request</button>
                        </div>
                    </div>
                @elseif ($request === null)
                    <p class="anihow-muted">Choose a request on the left.</p>
                @else
                    <header class="anihow-split-head">
                        <div>
                            <h2 class="anihow-split-name">{{ $request->name }}</h2>
                            <p class="anihow-muted">
                                New farmer-seller for {{ $request->farm?->name ?? 'a farm' }}
                                · asked by {{ $request->requester?->name ?? 'a Content Editor' }}
                                · {{ $request->created_at?->diffForHumans() }}
                            </p>
                        </div>
                        <span @class([
                            'anihow-pill',
                            'anihow-pill-wait' => $request->isWaiting(),
                            'anihow-pill-ok' => $request->status === \App\Enums\SellerRequestStatus::Added,
                            'anihow-pill-no' => $request->status === \App\Enums\SellerRequestStatus::NotAdded,
                        ])>{{ $request->isWaiting() ? 'Waiting for you' : $request->status->label() }}</span>
                    </header>

                    <dl class="anihow-id-facts anihow-facts-stack">
                        <div><dt>Email</dt><dd>{{ $request->email }}</dd></div>
                        <div><dt>Mobile number</dt><dd>{{ $request->phone ?: '—' }}</dd></div>
                        <div class="anihow-facts-wide"><dt>Note from the Content Editor</dt><dd class="anihow-facts-plain">{{ $request->note ?: '—' }}</dd></div>
                        @unless ($request->isWaiting())
                            <div><dt>Decided by</dt><dd>{{ $request->decider?->name ?? '—' }}, {{ $request->decided_at?->format('M j, Y') }}</dd></div>
                            @if ($request->decision_reason)
                                <div class="anihow-facts-wide"><dt>Reason the Content Editor saw</dt><dd class="anihow-facts-plain">{{ $request->decision_reason }}</dd></div>
                            @endif
                        @endunless
                    </dl>

                    @if ($request->isWaiting())
                        <ul class="anihow-checks" aria-label="Checks">
                            @foreach ($this->checks($request) as $check)
                                <li @class(['anihow-check', 'anihow-check-no' => ! $check['ok']])>
                                    <span class="anihow-check-dot" aria-hidden="true"></span>
                                    <span>{{ $check['label'] }}</span>
                                </li>
                            @endforeach
                        </ul>

                        @error('decision') <p class="anihow-error">{{ $message }}</p> @enderror

                        @if ($declining)
                            <div class="anihow-id-reject">
                                <label for="sr-reason" class="anihow-field-label">Reason the Content Editor sees</label>
                                <textarea id="sr-reason" wire:model="reason" rows="2" maxlength="500" class="anihow-input" placeholder="For example: this email already has a buyer account. Ask them to use another email."></textarea>
                                @error('reason') <p class="anihow-error">{{ $message }}</p> @enderror

                                <div class="anihow-id-actions anihow-id-actions-end">
                                    <button type="button" wire:click="cancelDecline" class="anihow-btn anihow-btn-quiet">Cancel</button>
                                    <button type="button" wire:click="decline" wire:loading.attr="disabled" class="anihow-btn anihow-btn-danger">Don't add, tell the Content Editor</button>
                                </div>
                            </div>
                        @else
                            <div class="anihow-id-actions">
                                <button type="button" wire:click="startDecline" class="anihow-btn anihow-btn-quiet anihow-btn-reject">Don't add…</button>
                                <button type="button" wire:click="createAccount" wire:loading.attr="disabled" class="anihow-btn anihow-btn-primary">Create the account</button>
                            </div>
                        @endif
                    @endif
                @endif
            </section>
        </div>
    @else
        {{-- Content Editor: what they asked for, their sellers, and the form --}}
        @php
            $requests = $this->myRequests();
            $sellers = $this->farmSellers();
            $waiting = $requests->filter->isWaiting()->count();
        @endphp

        <div class="anihow-dock anihow-dock-open">
            <div class="anihow-dock-main">
                <div>
                    <h1 class="anihow-split-heading anihow-dock-title">{{ $this->getTitle() }}</h1>
                    <p class="anihow-muted anihow-dock-intro">{{ $this->intro() }}</p>
                </div>

                <section class="anihow-card" aria-labelledby="sr-sent">
                    <div class="anihow-card-head">
                        <h2 id="sr-sent" class="anihow-card-title">Requests sent to COSEL</h2>
                        @if ($waiting > 0)
                            <span class="anihow-pill anihow-pill-wait">{{ $waiting }} waiting</span>
                        @endif
                    </div>

                    @forelse ($requests as $item)
                        <div class="anihow-line" wire:key="my-request-{{ $item->id }}">
                            <div class="anihow-line-main">
                                <span class="anihow-line-title">{{ $item->name }}</span>
                                <span class="anihow-line-meta">{{ $item->email }} · sent {{ $item->created_at?->diffForHumans() }}</span>
                                @if ($item->status === \App\Enums\SellerRequestStatus::NotAdded && $item->decision_reason)
                                    <span class="anihow-line-reason">COSEL: {{ $item->decision_reason }}</span>
                                @endif
                            </div>
                            <span @class([
                                'anihow-pill',
                                'anihow-pill-wait' => $item->isWaiting(),
                                'anihow-pill-ok' => $item->status === \App\Enums\SellerRequestStatus::Added,
                                'anihow-pill-no' => $item->status === \App\Enums\SellerRequestStatus::NotAdded,
                            ])>{{ $item->status->label() }}</span>
                        </div>
                    @empty
                        <p class="anihow-muted anihow-line-empty">No requests yet. Use the form to ask COSEL for a new farmer-seller.</p>
                    @endforelse
                </section>

                <section class="anihow-card" aria-labelledby="sr-have">
                    <div class="anihow-card-head">
                        <h2 id="sr-have" class="anihow-card-title">Farmer-sellers on this farm</h2>
                    </div>

                    @forelse ($sellers as $seller)
                        <div class="anihow-line" wire:key="farm-seller-{{ $seller->id }}">
                            <span class="anihow-line-title">{{ $seller->name }}</span>
                            <span class="anihow-line-meta">
                                @if (! $seller->isActive())
                                    Suspended
                                @elseif ($seller->must_change_password)
                                    Hasn't signed in yet
                                @else
                                    {{ $seller->listings_count }} {{ \Illuminate\Support\Str::plural('listing', $seller->listings_count) }} shown
                                @endif
                            </span>
                        </div>
                    @empty
                        <p class="anihow-muted anihow-line-empty">No farmer-sellers on this farm yet.</p>
                    @endforelse
                </section>
            </div>

            <aside class="anihow-dock-panel" aria-label="Ask COSEL to add sellers">
                <div>
                    <h2 class="anihow-split-name">Ask COSEL to add farmer-sellers</h2>
                    <p class="anihow-muted">For {{ $this->farmName() }} only. COSEL creates the accounts and hands each person a temporary password.</p>
                </div>

                <div class="anihow-segment" role="tablist" aria-label="How many">
                    <button type="button" role="tab" wire:click="setMode('one')" aria-selected="{{ $mode === 'one' ? 'true' : 'false' }}" @class(['anihow-segment-on' => $mode === 'one'])>One person</button>
                    <button type="button" role="tab" wire:click="setMode('many')" aria-selected="{{ $mode === 'many' ? 'true' : 'false' }}" @class(['anihow-segment-on' => $mode === 'many'])>Many at once</button>
                </div>

                <form wire:submit="send" class="anihow-dock-form">
                    @if ($mode === 'one')
                        <div class="anihow-field">
                            <label for="rq-name" class="anihow-field-label">Full name</label>
                            <input id="rq-name" wire:model="name" maxlength="255" class="anihow-input">
                            @error('name') <p class="anihow-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="anihow-field">
                            <label for="rq-email" class="anihow-field-label">Email</label>
                            <input id="rq-email" type="email" wire:model="email" maxlength="255" class="anihow-input">
                            @error('email') <p class="anihow-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="anihow-field">
                            <label for="rq-phone" class="anihow-field-label">Mobile number (optional)</label>
                            <input id="rq-phone" type="tel" wire:model="phone" maxlength="30" placeholder="09XX XXX XXXX" class="anihow-input">
                            @error('phone') <p class="anihow-error">{{ $message }}</p> @enderror
                        </div>
                    @else
                        @php($preview = $this->preview())
                        <div class="anihow-field">
                            <label for="rq-list" class="anihow-field-label">One person per line: name, email, mobile</label>
                            <textarea id="rq-list" wire:model.live.debounce.500ms="rows" rows="5" class="anihow-input" placeholder="Maria Santos, maria@example.com&#10;Pedro Reyes, pedro@example.com, 09171234567"></textarea>
                            @error('rows') <p class="anihow-error">{{ $message }}</p> @enderror
                        </div>

                        @if ($preview['people'] !== [])
                            <div class="anihow-preview" aria-live="polite">
                                <div class="anihow-preview-title">Check before sending</div>
                                @foreach ($preview['people'] as $person)
                                    <div @class(['anihow-preview-line', 'anihow-preview-skip' => $person['problem'] !== null])>
                                        {{ $person['label'] }} · {{ $person['problem'] === null ? 'ready' : $person['problem'].', will be skipped' }}
                                    </div>
                                @endforeach
                                @if ($preview['truncated'])
                                    <div class="anihow-preview-line anihow-preview-skip">Only the first {{ \App\Actions\Accounts\RequestFarmerSellers::MAX_ROWS }} lines are read.</div>
                                @endif
                            </div>
                        @endif
                    @endif

                    <div class="anihow-field">
                        <label for="rq-note" class="anihow-field-label">Note for COSEL (optional)</label>
                        <textarea id="rq-note" wire:model="note" rows="2" maxlength="500" class="anihow-input"></textarea>
                    </div>

                    <div class="anihow-dock-foot anihow-dock-foot-stack">
                        @if ($sent)
                            <p class="anihow-dock-note anihow-dock-note-ok" role="status">Sent. COSEL sees it on their home screen; their decision shows in your list.</p>
                        @endif
                        <button type="submit" wire:loading.attr="disabled" class="anihow-btn anihow-btn-primary">Send to COSEL</button>
                    </div>
                </form>
            </aside>
        </div>
    @endif
</x-filament-panels::page>
