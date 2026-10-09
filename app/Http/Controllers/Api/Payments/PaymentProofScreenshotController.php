<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofScreenshotController extends Controller
{
    public function __invoke(Request $request, PaymentProof $paymentProof): StreamedResponse
    {
        $user = $request->user();
        $order = $paymentProof->order;
        $reservation = $paymentProof->reservation;

        $allowed = $user !== null && (
            $user->isSuperAdmin()
            || ($order !== null && ($order->isOwnedByBuyer($user) || $order->isOwnedByFarmer($user)))
            || ($reservation !== null && (
                (int) $reservation->buyer_id === (int) $user->id
                || (int) $reservation->farmer_seller_id === (int) $user->id
            ))
        );

        abort_unless($allowed, 403);
        abort_unless($paymentProof->hasScreenshot(), 404);

        $disk = Storage::disk('local');
        $path = (string) $paymentProof->screenshot_path;
        abort_unless($disk->exists($path), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return $disk->response($path, 'payment.'.$extension, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
