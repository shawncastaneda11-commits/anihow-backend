<?php

namespace Tests\Feature;

use App\Actions\Chat\SendStallMessage;
use App\Actions\Privacy\AnonymizeUserAction;
use App\Actions\Privacy\ExportOwnDataAction;
use App\Enums\NotificationType;
use App\Jobs\SendPushNotification;
use App\Models\DeviceToken;
use App\Models\InAppNotification;
use App\Models\StallConversation;
use App\Models\User;
use App\Support\FcmPush;
use App\Support\InAppNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['firebase.projects.app.credentials' => null]);
    }

    public function test_a_token_is_upserted_and_moves_to_the_current_user(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->postJson('/api/me/device-tokens', [
            'token' => 'phone-token',
        ])->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'token' => 'phone-token',
            'user_id' => $first->id,
            'platform' => 'android',
        ]);

        $this->travel(5)->minutes();

        $this->actingAs($second)->postJson('/api/me/device-tokens', [
            'token' => 'phone-token',
        ])->assertOk();

        $this->assertSame(1, DeviceToken::query()->where('token', 'phone-token')->count());
        $device = DeviceToken::query()->where('token', 'phone-token')->first();
        $this->assertSame($second->id, $device->user_id);
        $this->assertTrue($device->last_seen_at->greaterThan(now()->subMinute()));
    }

    public function test_deleting_a_token_and_logging_out_with_it_removes_only_that_device(): void
    {
        $user = User::factory()->create();
        $keep = DeviceToken::factory()->for($user)->create(['token' => 'keep-me']);
        DeviceToken::factory()->for($user)->create(['token' => 'drop-me']);
        $access = $user->createToken('phone');

        $this->withToken($access->plainTextToken)->deleteJson('/api/me/device-tokens', [
            'token' => 'drop-me',
        ])->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'drop-me']);
        $this->assertDatabaseHas('device_tokens', ['id' => $keep->id]);

        $this->withToken($access->plainTextToken)->postJson('/api/auth/logout', [
            'device_token' => 'keep-me',
        ])->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'keep-me']);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $access->accessToken->id]);
    }

    public function test_push_preferences_default_to_on_and_a_patch_stores_the_change(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/me/push-preferences')
            ->assertOk()
            ->assertJsonPath('data.orders', true)
            ->assertJsonPath('data.payments', true)
            ->assertJsonPath('data.chats', true)
            ->assertJsonPath('data.farm_updates', true);

        $this->actingAs($user)->patchJson('/api/me/push-preferences', [
            'orders' => false,
        ])->assertOk()->assertJsonPath('data.orders', false);

        $this->assertFalse($user->fresh()->push_preferences['orders']);
        $this->assertTrue($user->fresh()->allowsPushCategory(null));
        $this->assertFalse($user->fresh()->allowsPushCategory('orders'));
    }

    public function test_the_job_is_dispatched_when_an_in_app_notice_is_created(): void
    {
        Bus::fake();
        $user = User::factory()->create();

        app(InAppNotifier::class)->send(
            $user,
            NotificationType::AccountApproved,
            'Account approved',
            'You can sell now.',
        );

        Bus::assertDispatched(SendPushNotification::class);
    }

    public function test_the_job_skips_when_push_is_not_configured_or_the_category_is_off_or_there_are_no_tokens(): void
    {
        $unconfiguredLogs = 0;
        Log::listen(function ($event) use (&$unconfiguredLogs): void {
            if ($event->level === 'info' && str_contains((string) $event->message, 'FCM is not configured')) {
                $unconfiguredLogs++;
            }
        });
        $messaging = $this->mockMessaging(expectSend: false);
        $user = User::factory()->create();
        $user->deviceTokens()->create([
            'token' => 'device-1',
            'platform' => 'android',
            'last_seen_at' => now(),
        ]);
        $notice = $this->notice($user, NotificationType::OrderPlaced, 'Buyer placed PHP 18.00');

        (new SendPushNotification($notice->id))->handle(app(FcmPush::class));
        (new SendPushNotification($notice->id))->handle(app(FcmPush::class));
        $this->assertSame(1, $unconfiguredLogs);

        $this->enablePush();
        $user->push_preferences = [
            'orders' => false,
            'payments' => true,
            'chats' => true,
            'farm_updates' => true,
        ];
        $user->save();
        (new SendPushNotification($notice->id))->handle(app(FcmPush::class));

        $user->deviceTokens()->delete();
        $user->push_preferences = [
            'orders' => true,
            'payments' => true,
            'chats' => true,
            'farm_updates' => true,
        ];
        $user->save();
        (new SendPushNotification($notice->id))->handle(app(FcmPush::class));

        $messaging->shouldNotHaveReceived('send');
    }

    public function test_payloads_are_string_only_privacy_safe_and_use_the_right_channel(): void
    {
        $this->enablePush();
        $captured = [];
        $this->mockMessaging(capture: $captured);

        $user = User::factory()->create(['name' => 'ZeldaBuyer']);
        $user->deviceTokens()->create([
            'token' => 'device-1',
            'platform' => 'android',
            'last_seen_at' => now(),
        ]);

        $cases = [
            [NotificationType::OrderPlaced, 'orders', 'Order update', 'ZeldaBuyer ordered SecretPechay totaling PHP 18.00', 'App\\Models\\Order'],
            [NotificationType::PaymentConfirmed, 'payments', 'Payment update', 'ZeldaBuyer paid PHP 18.00 for SecretPechay', 'App\\Models\\Reservation'],
            [NotificationType::OrderMessage, 'chats', 'New message', 'ZeldaBuyer: SecretPechay is ready', 'App\\Models\\Order'],
            [NotificationType::AccountApproved, 'general', 'AniHow', 'ZeldaBuyer, your stall is open', 'App\\Models\\Order'],
        ];

        foreach ($cases as [$type, $channel, $title, $body, $related]) {
            $notice = $this->notice($user, $type, $body, $related);
            (new SendPushNotification($notice->id))->handle(app(FcmPush::class));
            $message = array_pop($captured);
            $this->assertInstanceOf(CloudMessage::class, $message);
            $payload = $message->jsonSerialize();
            $this->assertSame($title, $payload['notification']['title']);
            $this->assertSame('Open AniHow to see it.', $payload['notification']['body']);
            $this->assertSame('high', $payload['android']['priority']);
            $this->assertSame($channel, $payload['android']['notification']['channel_id']);
            foreach ($payload['data'] as $value) {
                $this->assertIsString($value);
            }
            $this->assertSame((string) $notice->id, $payload['data']['notification_id']);
            $this->assertSame($type->value, $payload['data']['type']);
            $this->assertSame($related, $payload['data']['related_type']);
            $this->assertSame('41', $payload['data']['related_id']);
            $encoded = json_encode($payload);
            $this->assertIsString($encoded);
            $this->assertStringNotContainsString('ZeldaBuyer', $encoded);
            $this->assertStringNotContainsString('SecretPechay', $encoded);
            $this->assertStringNotContainsString('18.00', $encoded);
        }
    }

    public function test_an_invalid_token_is_deleted_and_does_not_fail_the_send(): void
    {
        $this->enablePush();
        $user = User::factory()->create();
        $device = $user->deviceTokens()->create([
            'token' => 'gone-token',
            'platform' => 'android',
            'last_seen_at' => now(),
        ]);
        $messaging = \Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->once()->andThrow(NotFound::becauseTokenNotFound('gone-token'));
        $this->app->instance(Messaging::class, $messaging);

        $notice = $this->notice($user, NotificationType::OrderReady, 'Ready');
        (new SendPushNotification($notice->id))->handle(app(FcmPush::class));
        $this->assertDatabaseMissing('device_tokens', ['id' => $device->id]);

        $again = $user->deviceTokens()->create([
            'token' => 'bad-argument',
            'platform' => 'android',
            'last_seen_at' => now(),
        ]);
        $messaging = \Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->once()->andThrow(new InvalidArgument('INVALID_ARGUMENT'));
        $this->app->instance(Messaging::class, $messaging);
        (new SendPushNotification($notice->id))->handle(app(FcmPush::class));
        $this->assertDatabaseMissing('device_tokens', ['id' => $again->id]);
    }

    public function test_an_untagged_stall_message_pushes_once_every_two_minutes_without_an_in_app_row(): void
    {
        $this->enablePush();
        $captured = [];
        $this->mockMessaging(capture: $captured, times: 1);

        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $seller->deviceTokens()->create([
            'token' => 'seller-phone',
            'platform' => 'android',
            'last_seen_at' => now(),
        ]);
        $conversation = StallConversation::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_seller_id' => $seller->id,
        ]);

        $action = app(SendStallMessage::class);
        $action->handle($buyer, $conversation, ['body' => 'Is the pechay still there?']);
        $action->handle($buyer, $conversation, ['body' => 'Hello again']);

        $this->assertSame(0, InAppNotification::query()->count());
        $this->assertCount(1, $captured);
        $payload = $captured[0]->jsonSerialize();
        $this->assertSame('chats', $payload['android']['notification']['channel_id']);
        $this->assertSame('New message', $payload['notification']['title']);
        $this->assertSame('stall_message', $payload['data']['type']);
        $this->assertSame((string) $conversation->id, $payload['data']['conversation_id']);
        $this->assertSame((string) $seller->id, $payload['data']['seller_id']);
        $this->assertIsString($payload['data']['seller_id']);
    }

    public function test_the_push_test_command_reports_counts_and_refuses_an_unknown_email(): void
    {
        $this->enablePush();
        $user = User::factory()->create();
        $user->deviceTokens()->create([
            'token' => 'secret-device-token',
            'platform' => 'android',
            'last_seen_at' => now(),
        ]);
        $this->mockMessaging();

        $this->artisan('anihow:push-test', ['email' => 'nobody@example.com'])
            ->expectsOutput('No account uses that email.')
            ->assertFailed();

        $this->artisan('anihow:push-test', ['email' => $user->email])
            ->expectsOutput('Sent to 1 devices. Failed: 0.')
            ->doesntExpectOutputToContain('secret-device-token')
            ->assertOk();

        $this->assertSame(0, InAppNotification::query()->count());
    }

    public function test_anonymize_deletes_tokens_and_export_omits_the_token_value(): void
    {
        $user = User::factory()->create();
        $user->deviceTokens()->create([
            'token' => 'export-must-not-include-this',
            'platform' => 'android',
            'last_seen_at' => now(),
        ]);

        $export = app(ExportOwnDataAction::class)->handle($user);
        $this->assertSame('android', $export['device_tokens'][0]['platform']);
        $this->assertArrayHasKey('created_at', $export['device_tokens'][0]);
        $this->assertArrayNotHasKey('token', $export['device_tokens'][0]);
        $encoded = json_encode($export);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('export-must-not-include-this', $encoded);

        app(AnonymizeUserAction::class)->handle($user);

        $this->assertSame(0, DeviceToken::query()->where('user_id', $user->id)->count());
    }

    private function enablePush(): void
    {
        $path = storage_path('framework/testing-firebase.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, '{}');
        config(['firebase.projects.app.credentials' => $path]);
    }

    /**
     * @param  list<CloudMessage>  $capture
     */
    private function mockMessaging(bool $expectSend = true, array &$capture = [], int $times = 0): Messaging
    {
        $messaging = \Mockery::mock(Messaging::class);
        if (! $expectSend) {
            $messaging->shouldReceive('send')->never();
        } else {
            $expectation = $messaging->shouldReceive('send');
            if ($times > 0) {
                $expectation->times($times);
            }
            $expectation->andReturnUsing(function (CloudMessage $message) use (&$capture): array {
                $capture[] = $message;

                return ['name' => 'projects/test/messages/1'];
            });
        }
        $this->app->instance(Messaging::class, $messaging);

        return $messaging;
    }

    private function notice(User $user, NotificationType $type, string $body, string $related = 'App\\Models\\Order'): InAppNotification
    {
        return $user->inAppNotifications()->create([
            'type' => $type,
            'title' => $body,
            'body' => $body,
            'related_id' => 41,
            'related_type' => $related,
        ]);
    }
}
