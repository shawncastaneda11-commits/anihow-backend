<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\IdVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams an ID photo to an admin reviewing it. Only staff who manage
 * accounts can see it, and only while the check is still pending.
 */
class IdVerificationPhotoController extends Controller
{
    public function __invoke(Request $request, IdVerification $idVerification, string $side): StreamedResponse
    {
        abort_unless($request->user()?->can(Permission::ManageAccounts->value), 403);

        $path = match ($side) {
            'front' => $idVerification->front_path,
            'selfie' => $idVerification->selfie_path,
            default => null,
        };
        abort_unless(filled($path), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, 'id-'.$side.'.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
