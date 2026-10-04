<?php

namespace App\Actions\Chat;

use App\Events\OrderMessageCreated;
use App\Events\StallMessageCreated;
use App\Models\Listing;
use App\Models\Order;
use App\Models\StallConversation;
use App\Models\StallMessage;
use App\Models\User;
use App\Support\ImageVariants;
use App\Support\InAppNotifier;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SendStallMessage
{
    public function __construct(
        private InAppNotifier $notifier,
        private PresentStallMessages $presentStallMessages,
        private ImageVariants $images,
    ) {}

    /**
     * @param  array{body?: string|null, order_id?: int|null, listing_id?: int|null}  $input
     */
    public function handle(User $sender, StallConversation $conversation, array $input, ?UploadedFile $attachment = null): StallMessage
    {
        $order = $this->taggedOrder($sender, $conversation, $input['order_id'] ?? null);
        $snapshot = $this->listingSnapshot($conversation, $input['listing_id'] ?? null);
        $stored = $this->storeAttachment($conversation, $attachment);
        $body = $input['body'] ?? null;
        $body = is_string($body) && trim($body) !== '' ? trim($body) : null;

        $message = $conversation->messages()->create([
            'user_id' => $sender->id,
            'body' => $body,
            'order_id' => $order?->id,
            ...$snapshot,
            ...$stored,
        ]);

        $message->load('author.roles');
        $this->presentStallMessages->links([$message]);

        if ($order !== null) {
            $counterpart = $order->isOwnedByBuyer($sender)
                ? $order->farmerSeller
                : $order->buyer;

            if ($counterpart !== null) {
                $preview = filled($message->body)
                    ? (string) $message->body
                    : (string) ($message->attachment_name ?: 'Attachment');
                $this->notifier->orderMessage($counterpart, $order, $sender, $preview);
            }
        }

        $this->broadcast($message, $order !== null);

        return $message;
    }

    private function taggedOrder(User $sender, StallConversation $conversation, mixed $orderId): ?Order
    {
        if ($orderId === null || $orderId === '') {
            return null;
        }

        $order = Order::query()->find((int) $orderId);
        $pairMatches = $order !== null
            && $order->buyer_id !== null
            && (int) $order->buyer_id === (int) $conversation->buyer_id
            && (int) $order->farmer_seller_id === (int) $conversation->farmer_seller_id;

        if (! $pairMatches || ! $sender->can('sendMessage', $order)) {
            abort(403);
        }

        return $order;
    }

    /**
     * @return array{
     *     listing_id: int|null,
     *     listing_title: string|null,
     *     listing_price_per_unit: mixed,
     *     listing_unit: string|null,
     *     listing_thumbnail_path: string|null
     * }
     */
    private function listingSnapshot(StallConversation $conversation, mixed $listingId): array
    {
        $empty = [
            'listing_id' => null,
            'listing_title' => null,
            'listing_price_per_unit' => null,
            'listing_unit' => null,
            'listing_thumbnail_path' => null,
        ];

        if ($listingId === null || $listingId === '') {
            return $empty;
        }

        $listing = Listing::query()
            ->listedForBuyers()
            ->whereKey((int) $listingId)
            ->where('farmer_seller_id', $conversation->farmer_seller_id)
            ->first();

        if ($listing === null) {
            throw ValidationException::withMessages([
                'listing_id' => 'Choose a listing this stall is currently selling.',
            ]);
        }

        return [
            'listing_id' => $listing->id,
            'listing_title' => $listing->title,
            'listing_price_per_unit' => $listing->price_per_unit,
            'listing_unit' => $listing->unit->value,
            'listing_thumbnail_path' => $listing->image_path,
        ];
    }

    /**
     * @return array{
     *     attachment_path: string|null,
     *     attachment_mime: string|null,
     *     attachment_size: int|null,
     *     attachment_name: string|null
     * }
     */
    private function storeAttachment(StallConversation $conversation, ?UploadedFile $attachment): array
    {
        $empty = [
            'attachment_path' => null,
            'attachment_mime' => null,
            'attachment_size' => null,
            'attachment_name' => null,
        ];

        if ($attachment === null) {
            return $empty;
        }

        $disk = Storage::disk('local');
        $directory = 'chat/'.$conversation->id;
        $name = $this->attachmentName($attachment);
        $mime = (string) $attachment->getMimeType();

        if (str_starts_with($mime, 'image/')) {
            try {
                $path = $this->images->store($attachment, $directory, $disk);
            } catch (\RuntimeException) {
                throw ValidationException::withMessages([
                    'attachment' => 'That photo could not be read. Please choose another.',
                ]);
            }

            return [
                'attachment_path' => $path,
                'attachment_mime' => 'image/jpeg',
                'attachment_size' => $disk->size($path),
                'attachment_name' => $name,
            ];
        }

        $path = $directory.'/'.Str::uuid()->toString().'.pdf';
        $disk->put($path, $attachment->getContent());

        return [
            'attachment_path' => $path,
            'attachment_mime' => 'application/pdf',
            'attachment_size' => $disk->size($path),
            'attachment_name' => $name,
        ];
    }

    private function attachmentName(UploadedFile $file): string
    {
        $name = str_replace(["\0", '/', '\\', '"', "\r", "\n"], '', $file->getClientOriginalName());
        $name = trim((string) preg_replace('/\s+/', ' ', $name));

        if ($name === '') {
            $name = 'attachment';
        }

        if (mb_strlen($name) <= 120) {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $suffix = $extension === '' ? '' : '.'.$extension;
        $keep = max(1, 120 - mb_strlen($suffix));

        return mb_substr($base, 0, $keep).$suffix;
    }

    private function broadcast(StallMessage $message, bool $tagged): void
    {
        try {
            event(new StallMessageCreated($message));

            if ($tagged) {
                event(new OrderMessageCreated($message));
            }
        } catch (BroadcastException $exception) {
            report($exception);
        }
    }
}
