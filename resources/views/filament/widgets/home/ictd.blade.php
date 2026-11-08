<x-filament-widgets::widget>
    <div class="anihow-home">
        <div class="anihow-home-actions">
            <span @class(['anihow-home-pill', 'anihow-home-pill-warn' => $overview['maintenance'] || $overview['failed_jobs'] > 0])>
                {{ $overview['maintenance'] ? 'Maintenance mode is on' : ($overview['failed_jobs'] > 0 ? 'Some background jobs failed' : 'All systems normal') }}
            </span>
            <a href="{{ $createAdminUrl }}" class="anihow-home-button">Create administrator account</a>
        </div>

        <section aria-labelledby="home-health" class="anihow-home-block">
            <h2 id="home-health" class="anihow-home-heading">System health</h2>
            <div class="anihow-home-tiles">
                @foreach ($tiles as $tile)
                    <a href="{{ $healthUrl }}" class="anihow-home-tile">
                        <span class="anihow-home-tile-label">{{ $tile['label'] }}</span>
                        <span class="anihow-home-tile-value">{{ $tile['value'] }}</span>
                        <span @class(['anihow-home-tile-note', 'anihow-home-warn' => $tile['warn']])>{{ $tile['note'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <div class="anihow-home-columns">
            <x-filament::section heading="Security and app" class="anihow-home-narrow">
                <dl class="anihow-home-settings">
                    <div>
                        <dt>Two-step sign-in for administrators</dt>
                        <dd>{{ $overview['two_step_required'] ? 'Required' : 'Off' }} · {{ $overview['admins_with_two_step'] }} of {{ $overview['admins'] }} set up</dd>
                    </div>
                    <div>
                        <dt>Minimum app version</dt>
                        <dd>{{ $overview['min_version'] ?? 'None' }}</dd>
                    </div>
                    <div>
                        <dt>Maintenance mode</dt>
                        <dd>{{ $overview['maintenance'] ? 'On' : 'Off' }}</dd>
                    </div>
                </dl>
                <a href="{{ $healthUrl }}" class="anihow-home-link">Change on System health</a>
            </x-filament::section>

            <div class="anihow-home-wide">
                @include('filament.widgets.home.activity', ['activity' => $activity])
                <a href="{{ $activityUrl }}" class="anihow-home-link">See the whole activity log</a>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
