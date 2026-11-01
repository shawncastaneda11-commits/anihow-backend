<?php

use App\Http\Controllers\Api\Admin\IdVerificationPhotoController;
use App\Http\Controllers\Api\Payments\PaymentProofScreenshotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Public pages that Google Play links to: the privacy policy and how to delete an account.
Route::get('privacy', function (Request $request) {
    return view('legal.privacy', [
        'lang' => $request->query('lang') === 'fil' ? 'fil' : 'en',
        'contact' => config('anihow.seller_help.email'),
    ]);
})->name('legal.privacy');

Route::get('delete-account', function (Request $request) {
    return view('legal.delete-account', [
        'lang' => $request->query('lang') === 'fil' ? 'fil' : 'en',
        'contact' => config('anihow.seller_help.email'),
    ]);
})->name('legal.delete-account');

Route::middleware('auth')->group(function (): void {
    Route::get('admin-files/payment-proofs/{paymentProof}/screenshot', PaymentProofScreenshotController::class)
        ->name('payments.proofs.screenshot');
    Route::get('admin-files/id-verifications/{idVerification}/{side}', IdVerificationPhotoController::class)
        ->whereIn('side', ['front', 'selfie'])
        ->name('id-verifications.photo');
});
