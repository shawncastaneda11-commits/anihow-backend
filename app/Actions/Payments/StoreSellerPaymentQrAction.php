<?php

namespace App\Actions\Payments;

use App\Enums\PaymentWallet;
use App\Models\SellerPaymentQr;
use App\Models\User;
use App\Support\ImageVariants;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StoreSellerPaymentQrAction
{
    public function __construct(private ImageVariants $images) {}

    public function handle(
        User $seller,
        PaymentWallet $wallet,
        string $accountName,
        string $accountLast4,
        UploadedFile $image,
    ): SellerPaymentQr {
        if ($seller->paymentQrs()->count() >= 3) {
            throw ValidationException::withMessages([
                'image' => 'You can save up to 3 QR codes.',
            ]);
        }

        $path = $this->images->store($image, 'payment-qrs/'.$seller->id, Storage::disk('local'));

        return $seller->paymentQrs()->create([
            'wallet' => $wallet,
            'account_name' => $accountName,
            'account_last4' => $accountLast4,
            'image_path' => $path,
        ]);
    }
}
