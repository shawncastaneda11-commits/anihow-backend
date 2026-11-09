<x-filament-panels::page>
    @php
        $items = $this->items();
        $record = $this->selected();
        $waiting = $this->waitingCount();
    @endphp

    <div class="anihow-split">
        {{-- The queue --}}
        <section class="anihow-split-list" aria-label="ID checks">
            <div class="anihow-split-intro">
                <h1 class="anihow-split-heading">{{ $this->getTitle() }}</h1>
                <p class="anihow-muted">{{ $this->intro() }}</p>
            </div>

            <div class="anihow-split-tabs" role="tablist" aria-label="Waiting or decided">
                <button type="button" role="tab" wire:click="showTab('waiting')" aria-selected="{{ $tab === 'waiting' ? 'true' : 'false' }}" @class(['anihow-chip', 'anihow-chip-on' => $tab === 'waiting'])>
                    Waiting <span class="anihow-chip-count">{{ $waiting }}</span>
                </button>
                <button type="button" role="tab" wire:click="showTab('decided')" aria-selected="{{ $tab === 'decided' ? 'true' : 'false' }}" @class(['anihow-chip', 'anihow-chip-on' => $tab === 'decided'])>
                    Decided
                </button>
            </div>

            @forelse ($items as $item)
                <button
                    type="button"
                    wire:key="id-check-{{ $item->id }}"
                    wire:click="select({{ $item->id }})"
                    @class(['anihow-split-item', 'anihow-split-item-on' => $record?->is($item)])
                    aria-current="{{ $record?->is($item) ? 'true' : 'false' }}"
                >
                    <span class="anihow-split-item-title">{{ $item->user?->name ?? 'Deleted account' }}</span>
                    <span class="anihow-split-item-meta">
                        {{ $item->id_type->label() }} ·
                        {{ $tab === 'decided' ? $item->status->label().' '.$item->reviewed_at?->diffForHumans() : $item->created_at?->diffForHumans() }}
                    </span>
                </button>
            @empty
                <p class="anihow-muted anihow-split-empty">{{ $tab === 'decided' ? 'Nothing decided yet.' : 'No IDs waiting. You are all caught up.' }}</p>
            @endforelse
        </section>

        {{-- The review --}}
        <section class="anihow-split-panel" aria-label="Review">
            @if ($record === null)
                <p class="anihow-muted">Choose an ID on the left.</p>
            @else
                <header class="anihow-split-head">
                    <div>
                        <h2 class="anihow-split-name">{{ $record->user?->name ?? 'Deleted account' }}</h2>
                        <p class="anihow-muted">{{ $record->user?->email }} · sent {{ $record->created_at?->diffForHumans() }}</p>
                    </div>
                    <span @class([
                        'anihow-pill',
                        'anihow-pill-wait' => $record->isPending(),
                        'anihow-pill-ok' => $record->status === \App\Enums\IdVerificationStatus::Approved,
                        'anihow-pill-no' => $record->status === \App\Enums\IdVerificationStatus::Rejected,
                    ])>{{ $record->isPending() ? 'Waiting for you' : $record->status->label() }}</span>
                </header>

                @if ($record->isPending())
                    <div class="anihow-id-photos">
                        @foreach (['front' => ['Photo of the ID', $record->id_type->label().'. Click to zoom.'], 'selfie' => ['Selfie holding the ID', 'Compare the face with the ID photo.']] as $side => [$label, $caption])
                            @php($url = $this->photoUrl($record, $side))
                            <figure class="anihow-id-photo">
                                @if ($url)
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="anihow-id-photo-frame">
                                        <img src="{{ $url }}" alt="{{ $label }}">
                                    </a>
                                @else
                                    <div class="anihow-id-photo-frame anihow-id-photo-missing">{{ $label }}: not available</div>
                                @endif
                                <figcaption class="anihow-muted">{{ $caption }}</figcaption>
                            </figure>
                        @endforeach
                    </div>
                @endif

                <dl class="anihow-id-facts">
                    <div><dt>Name on the account</dt><dd>{{ $record->user?->name ?? '—' }}</dd></div>
                    <div><dt>ID type they chose</dt><dd>{{ $record->id_type->label() }}</dd></div>
                    @if ($record->isPending())
                        <div><dt>Earlier tries</dt><dd>{{ $this->earlierTries($record) }}</dd></div>
                    @else
                        <div><dt>Decided by</dt><dd>{{ $record->reviewer?->name ?? '—' }}, {{ $record->reviewed_at?->format('M j, Y') }}</dd></div>
                    @endif
                </dl>

                @if ($record->isPending())
                    <p class="anihow-muted">Check that the name matches, the whole card shows, it is not expired, and the selfie matches. The photos are deleted once you decide.</p>

                    @if ($rejecting)
                        <div class="anihow-id-reject">
                            <label for="id-reject-reason" class="anihow-field-label">Reason</label>
                            <select id="id-reject-reason" wire:model.live="reason" class="anihow-input">
                                @foreach ($this->reasons() as $value => $text)
                                    <option value="{{ $value }}">{{ $text }}</option>
                                @endforeach
                            </select>
                            @error('reason') <p class="anihow-error">{{ $message }}</p> @enderror

                            <label for="id-reject-note" class="anihow-field-label">Note for the buyer {{ $reason === 'other' ? '' : '(optional)' }}</label>
                            <textarea id="id-reject-note" wire:model="note" rows="2" maxlength="300" class="anihow-input"></textarea>
                            @error('note') <p class="anihow-error">{{ $message }}</p> @enderror

                            <div class="anihow-id-actions anihow-id-actions-end">
                                <button type="button" wire:click="cancelReject" class="anihow-btn anihow-btn-quiet">Cancel</button>
                                <button type="button" wire:click="reject" wire:loading.attr="disabled" class="anihow-btn anihow-btn-danger">Reject and tell the buyer</button>
                            </div>
                        </div>
                    @else
                        <div class="anihow-id-actions">
                            <button type="button" wire:click="startReject" class="anihow-btn anihow-btn-quiet anihow-btn-reject">Reject…</button>
                            <button type="button" wire:click="approve" wire:loading.attr="disabled" class="anihow-btn anihow-btn-primary">Approve and open the next one</button>
                        </div>
                    @endif
                @elseif ($record->rejection_reason)
                    <p><strong>Reason the buyer saw:</strong> {{ $record->rejection_reason }}</p>
                @endif
            @endif
        </section>
    </div>
</x-filament-panels::page>
