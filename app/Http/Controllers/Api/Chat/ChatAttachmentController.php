<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\StallMessage;
use App\Support\ImageVariants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatAttachmentController extends Controller
{
    public function __invoke(Request $request, StallMessage $stallMessage, ImageVariants $images): StreamedResponse
    {
        $conversation = $stallMessage->conversation;
        $canReadThread = $conversation !== null && $request->user()?->can('view', $conversation);
        $canReadTaggedOrder = ! $canReadThread
            && $stallMessage->order_id !== null
            && $stallMessage->order !== null
            && $request->user()?->can('chat', $stallMessage->order);

        abort_unless($canReadThread || $canReadTaggedOrder, 403);
        abort_unless(filled($stallMessage->attachment_path), 404);

        $thumb = $request->query('variant') === 'thumb';
        $photo = str_starts_with((string) $stallMessage->attachment_mime, 'image/');

        if ($thumb && ! $photo) {
            abort(404);
        }

        $disk = Storage::disk('local');
        $path = $thumb
            ? $images->thumbnailPath((string) $stallMessage->attachment_path)
            : (string) $stallMessage->attachment_path;

        abort_unless($disk->exists($path), 404);

        $name = str_replace(['"', "\r", "\n"], '', (string) ($stallMessage->attachment_name ?: 'attachment'));
        $mime = $thumb ? 'image/jpeg' : (string) $stallMessage->attachment_mime;
        $disposition = $photo ? 'inline' : 'attachment';

        return $disk->response($path, $name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ], $disposition);
    }
}
