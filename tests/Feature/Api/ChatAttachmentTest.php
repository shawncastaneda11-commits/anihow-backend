<?php

namespace Tests\Feature\Api;

use App\Actions\Privacy\AnonymizeUserAction;
use App\Enums\Role;
use App\Models\StallConversation;
use App\Models\StallMessage;
use App\Models\User;
use App\Support\ChatAttachmentLimiter;
use App\Support\ImageVariants;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class ChatAttachmentTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_a_photo_is_private_reencoded_and_readable_by_the_thread(): void
    {
        [$buyer, $farmer, $conversationId] = $this->thread();
        $upload = $this->jpegWithMarker();

        $created = $this->asUser($buyer)
            ->post("/api/stall-chats/{$conversationId}/messages", [
                'attachment' => $upload,
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', null)
            ->json('data');

        $this->assertNull($created['listing_id'] ?? null);
        $this->assertSame('phone.jpg', $created['attachment']['name']);
        $this->assertSame('image/jpeg', $created['attachment']['mime']);
        $this->assertGreaterThan(0, $created['attachment']['size']);
        $this->assertStringContainsString('/api/chat/attachments/', $created['attachment']['url']);
        $this->assertStringNotContainsString('/storage/', $created['attachment']['url']);
        $this->assertNotNull($created['attachment']['thumbnail_url']);

        $message = StallMessage::query()->findOrFail($created['id']);
        $stored = Storage::disk('local')->get($message->attachment_path);
        $this->assertIsString($stored);
        $this->assertStringStartsWith("\xFF\xD8", $stored);
        $this->assertStringNotContainsString('GPS-SECRET-MARKER', $stored);
        $this->assertTrue(Storage::disk('local')->exists(
            app(ImageVariants::class)->thumbnailPath((string) $message->attachment_path),
        ));
        Storage::disk('public')->assertMissing((string) $message->attachment_path);

        $this->asUser($farmer)
            ->get($created['attachment']['url'])
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('content-type', 'image/jpeg');

        $disposition = (string) $this->asUser($buyer)
            ->get($created['attachment']['url'])
            ->headers->get('content-disposition');
        $this->assertStringContainsString('inline', $disposition);

        $this->asUser($buyer)
            ->get($created['attachment']['thumbnail_url'])
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');

        if (is_file($upload->getPathname())) {
            unlink($upload->getPathname());
        }
    }

    public function test_a_pdf_keeps_its_bytes_and_downloads_as_an_attachment(): void
    {
        [$buyer, , $conversationId] = $this->thread();
        $pdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";

        $created = $this->asUser($buyer)
            ->post("/api/stall-chats/{$conversationId}/messages", [
                'body' => 'The receipt',
                'attachment' => UploadedFile::fake()->createWithContent('My handover.pdf', $pdf),
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('My handover.pdf', $created['attachment']['name']);
        $this->assertSame('application/pdf', $created['attachment']['mime']);
        $this->assertNull($created['attachment']['thumbnail_url']);

        $message = StallMessage::query()->findOrFail($created['id']);
        $this->assertStringEndsWith('.pdf', (string) $message->attachment_path);
        $this->assertSame($pdf, Storage::disk('local')->get($message->attachment_path));

        $response = $this->asUser($buyer)->get($created['attachment']['url']);
        $response->assertOk();
        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('content-disposition'),
        );

        $this->asUser($buyer)
            ->get($created['attachment']['url'].'?variant=thumb')
            ->assertNotFound();
    }

    public function test_outsiders_and_content_editors_cannot_fetch_an_attachment(): void
    {
        [$buyer, $farmer, $conversationId] = $this->thread();
        $created = $this->asUser($buyer)
            ->post("/api/stall-chats/{$conversationId}/messages", [
                'attachment' => UploadedFile::fake()->image('note.jpg', 40, 30),
            ])
            ->assertCreated()
            ->json('data');
        $url = $created['attachment']['url'];

        $other = $this->buyer(['email' => 'other.buyer@example.com']);
        $this->asUser($other)->get($url)->assertForbidden();

        $editor = User::factory()->create(['farm_id' => $farmer->farm_id]);
        $editor->syncRoles(Role::ContentEditor);
        $this->asUser($editor)->get($url)->assertForbidden();

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->get('/api/chat/attachments/'.$created['id'])->assertUnauthorized();
    }

    public function test_super_admin_can_read_only_an_order_tagged_attachment(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['quantity_available' => 10, 'price_per_unit' => 30]);
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 1);
        $conversationId = $this->asUser($buyer)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $farmer->id])
            ->assertCreated()
            ->json('data.id');

        $tagged = $this->asUser($buyer)
            ->post("/api/stall-chats/{$conversationId}/messages", [
                'order_id' => $order->id,
                'attachment' => UploadedFile::fake()->image('tagged.jpg', 40, 30),
            ])
            ->assertCreated()
            ->json('data.attachment.url');

        $untagged = $this->asUser($buyer)
            ->post("/api/stall-chats/{$conversationId}/messages", [
                'body' => 'Just chatting',
                'attachment' => UploadedFile::fake()->image('aside.jpg', 40, 30),
            ])
            ->assertCreated()
            ->json('data.attachment.url');

        $admin = User::factory()->create();
        $admin->syncRoles(Role::SuperAdmin);

        $this->asUser($admin)->get($tagged)->assertOk();
        $this->asUser($admin)->get($untagged)->assertForbidden();

        $listed = $this->asUser($admin)
            ->getJson("/api/orders/{$order->id}/messages")
            ->assertOk()
            ->json('data.0');

        $this->assertSame(['id', 'body', 'created_at', 'author'], array_keys($listed));
        $this->assertSame('', $listed['body']);
    }

    public function test_a_spoofed_photo_and_an_oversized_file_are_rejected(): void
    {
        [$buyer, , $conversationId] = $this->thread();

        $this->asUser($buyer)
            ->post("/api/stall-chats/{$conversationId}/messages", [
                'attachment' => UploadedFile::fake()->createWithContent('photo.jpg', 'not really a photo'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachment');

        $this->asUser($buyer)
            ->post("/api/stall-chats/{$conversationId}/messages", [
                'attachment' => UploadedFile::fake()->image('big.jpg', 20, 20)->size(5121),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachment');

        $this->asUser($buyer)
            ->postJson("/api/stall-chats/{$conversationId}/messages", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_the_order_endpoint_stays_text_only(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['quantity_available' => 10, 'price_per_unit' => 30]);
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 1);

        $created = $this->asUser($buyer)
            ->post("/api/orders/{$order->id}/messages", [
                'body' => 'Text only',
                'attachment' => UploadedFile::fake()->image('photo.jpg', 30, 20),
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame(['id', 'body', 'created_at', 'author'], array_keys($created));
        $this->assertSame('Text only', $created['body']);
        $this->assertDatabaseHas('stall_messages', [
            'order_id' => $order->id,
            'body' => 'Text only',
            'attachment_path' => null,
        ]);
    }

    public function test_files_are_removed_when_the_message_or_account_goes_away(): void
    {
        [$buyer, , $conversationId] = $this->thread();
        $messageId = $this->asUser($buyer)
            ->post("/api/stall-chats/{$conversationId}/messages", [
                'attachment' => UploadedFile::fake()->image('gone.jpg', 40, 30),
            ])
            ->assertCreated()
            ->json('data.id');

        $message = StallMessage::query()->findOrFail($messageId);
        $path = (string) $message->attachment_path;
        $thumb = app(ImageVariants::class)->thumbnailPath($path);
        $message->delete();

        Storage::disk('local')->assertMissing($path);
        Storage::disk('local')->assertMissing($thumb);
        $this->assertDatabaseMissing('stall_messages', ['id' => $messageId]);

        $keptId = $this->asUser($buyer)
            ->post("/api/stall-chats/{$conversationId}/messages", [
                'attachment' => UploadedFile::fake()->createWithContent(
                    'keep.pdf',
                    "%PDF-1.4\n%%EOF\n",
                ),
            ])
            ->assertCreated()
            ->json('data.id');
        $kept = StallMessage::query()->findOrFail($keptId);
        $keptPath = (string) $kept->attachment_path;

        StallConversation::query()->findOrFail($conversationId)->delete();

        Storage::disk('local')->assertMissing($keptPath);
        $this->assertDatabaseMissing('stall_messages', ['id' => $keptId]);

        [$buyerAgain, , $freshConversation] = $this->thread();
        $ownedId = $this->asUser($buyerAgain)
            ->post("/api/stall-chats/{$freshConversation}/messages", [
                'body' => 'Mine',
                'attachment' => UploadedFile::fake()->image('mine.jpg', 30, 20),
            ])
            ->assertCreated()
            ->json('data.id');
        $owned = StallMessage::query()->findOrFail($ownedId);
        $ownedPath = (string) $owned->attachment_path;

        app(AnonymizeUserAction::class)->handle($buyerAgain->fresh());

        Storage::disk('local')->assertMissing($ownedPath);
        $this->assertDatabaseHas('stall_messages', [
            'id' => $ownedId,
            'body' => 'Mine',
            'attachment_path' => null,
            'attachment_name' => null,
        ]);

        $export = json_decode(
            $this->asUser($buyer)->get('/api/auth/user/export')->streamedContent(),
            true,
        );
        $this->assertIsArray($export['attachments']);
    }

    public function test_export_lists_attachment_details_without_the_file(): void
    {
        [$buyer, , $conversationId] = $this->thread();
        $this->asUser($buyer)
            ->post("/api/stall-chats/{$conversationId}/messages", [
                'attachment' => UploadedFile::fake()->image('receipt.jpg', 40, 30),
            ])
            ->assertCreated();

        $payload = json_decode(
            $this->asUser($buyer)->get('/api/auth/user/export')->streamedContent(),
            true,
        );

        $this->assertSame(
            ['name', 'mime', 'size', 'created_at'],
            array_keys($payload['attachments'][0]),
        );
        $this->assertSame('receipt.jpg', $payload['attachments'][0]['name']);
        $this->assertSame('image/jpeg', $payload['attachments'][0]['mime']);
        $this->assertStringNotContainsString('chat/', (string) json_encode($payload['attachments']));
    }

    public function test_chat_attachment_limiter_allows_twenty_per_hour_when_enabled(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/api/stall-chats/1/messages', 'POST');
        $request->setUserResolver(fn () => $user);

        $registered = RateLimiter::limiter('chat-attachments');
        $this->assertNotNull($registered);
        $this->assertInstanceOf(Unlimited::class, $registered($request));

        $limit = app(ChatAttachmentLimiter::class)->limit($request);
        $this->assertSame(20, $limit->maxAttempts);
        $this->assertSame(3600, $limit->decaySeconds);
        $this->assertSame('chat-attachments|'.$user->id, $limit->key);

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->assertFalse(RateLimiter::tooManyAttempts((string) $limit->key, $limit->maxAttempts));
            RateLimiter::hit((string) $limit->key, $limit->decaySeconds);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts((string) $limit->key, $limit->maxAttempts));
    }

    /**
     * @return array{0: User, 1: User, 2: int}
     */
    private function thread(): array
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $conversationId = $this->asUser($buyer)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $farmer->id])
            ->assertCreated()
            ->json('data.id');

        return [$buyer, $farmer, $conversationId];
    }

    private function jpegWithMarker(): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'chat').'.jpg';
        $image = imagecreatetruecolor(48, 32);
        $color = imagecolorallocate($image, 20, 140, 60);
        imagefilledrectangle($image, 0, 0, 47, 31, $color);
        imagejpeg($image, $tmp, 80);
        imagedestroy($image);
        file_put_contents($tmp, (string) file_get_contents($tmp).'GPS-SECRET-MARKER');

        return new UploadedFile($tmp, 'phone.jpg', 'image/jpeg', null, true);
    }
}
