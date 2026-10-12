<?php

use App\Http\Controllers\Api\Admin\IdVerificationPhotoController;
use App\Http\Controllers\Api\Payments\PaymentProofScreenshotController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware('auth')->group(function (): void {
    Route::get('admin-files/payment-proofs/{paymentProof}/screenshot', PaymentProofScreenshotController::class)
        ->name('payments.proofs.screenshot');
    Route::get('admin-files/id-verifications/{idVerification}/{side}', IdVerificationPhotoController::class)
        ->whereIn('side', ['front', 'selfie'])
        ->name('id-verifications.photo');
});
