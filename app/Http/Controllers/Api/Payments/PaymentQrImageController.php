<?php

namespace App\Http\Controllers\Api\Payments;

use App\Enums\OrderPaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SellerPaymentQr;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentQrImageController extends Controller
{
    public function __invoke(Request $request, SellerPaymentQr $sellerPaymentQr): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $this->allowed($user, $sellerPaymentQr), 403);

        $disk = Storage::disk('local');
        $path = (string) $sellerPaymentQr->image_path;
        abort_unless($path !== '' && $disk->exists($path), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return $disk->response($path, 'qr.'.$extension, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function allowed(User $user, SellerPaymentQr $qr): bool
    {
        if ($qr->farmer_seller_id === $user->id || $user->isSuperAdmin()) {
            return true;
        }

        return Order::query()
            ->where('buyer_id', $user->id)
            ->where('farmer_seller_id', $qr->farmer_seller_id)
            ->whereIn('payment_status', [
                OrderPaymentStatus::AwaitingPayment,
                OrderPaymentStatus::PaymentSent,
            ])
            ->whereJsonContains('payment_qr_ids', $qr->id)
            ->exists();
    }
}
