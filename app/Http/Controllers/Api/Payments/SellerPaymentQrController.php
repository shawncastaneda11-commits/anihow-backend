<?php

namespace App\Http\Controllers\Api\Payments;

use App\Actions\Payments\DeleteSellerPaymentQrAction;
use App\Actions\Payments\StoreSellerPaymentQrAction;
use App\Enums\PaymentWallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payments\StoreSellerPaymentQrRequest;
use App\Http\Resources\Api\SellerPaymentQrResource;
use App\Models\SellerPaymentQr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SellerPaymentQrController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SellerPaymentQr::class);

        return SellerPaymentQrResource::collection(
            $request->user()->paymentQrs()->orderBy('id')->get(),
        );
    }

    public function store(StoreSellerPaymentQrRequest $request, StoreSellerPaymentQrAction $store): JsonResponse
    {
        $qr = $store->handle(
            $request->user(),
            PaymentWallet::from($request->validated('wallet')),
            $request->validated('account_name'),
            $request->validated('account_last4'),
            $request->file('image'),
        );

        return (new SellerPaymentQrResource($qr))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, SellerPaymentQr $sellerPaymentQr, DeleteSellerPaymentQrAction $delete): JsonResponse
    {
        $this->authorize('delete', $sellerPaymentQr);

        $delete->handle($request->user(), $sellerPaymentQr);

        return response()->json(['message' => 'QR code removed.']);
    }
}
