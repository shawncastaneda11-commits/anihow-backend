<?php

namespace App\Actions\Privacy;

use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\Review;
use App\Models\TawadRule;
use App\Models\User;
use Illuminate\Support\Collection;

class ExportOwnDataAction
{
    /**
     * The requester's own records only. Counterparties appear by display name,
     * never by email or phone.
     *
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        $user->load(['farm', 'roles']);

        $orders = $this->ordersFor($user);
        $listingIds = $user->listings()->pluck('id');

        return [
            'exported_at' => now()->toIso8601String(),
            'profile' => $this->profile($user),
            'orders' => $orders->map(fn (Order $order): array => $this->order($order, $user))->values()->all(),
            'reviews_written' => $user->reviewsWritten()->with('farmerSeller:id,name,shop_name')->get()
                ->map(fn ($review): array => [
                    'id' => $review->id,
                    'order_id' => $review->order_id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'seller_name' => $review->farmerSeller?->shop_name ?: $review->farmerSeller?->name,
                    'created_at' => $review->created_at?->toIso8601String(),
                ])->all(),
            'reviews_received' => $user->reviewsReceived()->with('buyer:id,name')->get()
                ->map(fn ($review): array => [
                    'id' => $review->id,
                    'order_id' => $review->order_id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'buyer_name' => $review->buyer?->name,
                    'created_at' => $review->created_at?->toIso8601String(),
                ])->all(),
            'favorites' => $user->favorites()->with('listing:id,title')->get()
                ->map(fn ($favorite): array => [
                    'id' => $favorite->id,
                    'listing_id' => $favorite->listing_id,
                    'listing_title' => $favorite->listing?->title,
                    'created_at' => $favorite->created_at?->toIso8601String(),
                ])->all(),
            'shop_favorites' => $user->shopFavorites()->with('farmerSeller:id,name,shop_name')->get()
                ->map(fn ($favorite): array => [
                    'id' => $favorite->id,
                    'shop_name' => $favorite->farmerSeller?->shop_name ?: $favorite->farmerSeller?->name,
                    'created_at' => $favorite->created_at?->toIso8601String(),
                ])->all(),
            'cart' => $user->cartItems()->with('listing:id,title')->get()
                ->map(fn ($item): array => [
                    'id' => $item->id,
                    'listing_id' => $item->listing_id,
                    'listing_title' => $item->listing?->title,
                    'quantity' => $item->quantity,
                ])->all(),
            'order_messages' => OrderMessage::query()
                ->where('user_id', $user->id)
                ->orderBy('id')
                ->get(['id', 'order_id', 'body', 'created_at'])
                ->map(fn (OrderMessage $message): array => [
                    'id' => $message->id,
                    'order_id' => $message->order_id,
                    'body' => $message->body,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])->all(),
            'notifications' => $user->inAppNotifications()
                ->orderByDesc('id')
                ->get(['id', 'type', 'title', 'body', 'read_at', 'created_at'])
                ->map(fn ($notification): array => [
                    'id' => $notification->id,
                    'type' => $notification->type->value,
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at?->toIso8601String(),
                ])->all(),
            'listings' => $user->listings()
                ->get(['id', 'title', 'description', 'price_per_unit', 'quantity_available', 'is_active', 'status', 'created_at'])
                ->map(fn ($listing): array => [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'description' => $listing->description,
                    'price_per_unit' => $listing->price_per_unit,
                    'quantity_available' => $listing->quantity_available,
                    'is_active' => $listing->is_active,
                    'status' => $listing->status->value,
                    'created_at' => $listing->created_at?->toIso8601String(),
                ])->all(),
            'tawad_rules' => TawadRule::query()
                ->whereIn('listing_id', $listingIds)
                ->get()
                ->map(fn (TawadRule $rule): array => [
                    'id' => $rule->id,
                    'listing_id' => $rule->listing_id,
                    'type' => $rule->type->value,
                    'discount_amount' => $rule->discount_amount,
                    'min_quantity' => $rule->min_quantity,
                    'is_active' => $rule->is_active,
                    'ended_at' => $rule->ended_at?->toIso8601String(),
                ])->all(),
            'reports_submitted' => $user->reportsFiled()
                ->orderBy('id')
                ->get()
                ->map(fn ($report): array => [
                    'target_type' => match ($report->reportable_type) {
                        Listing::class => 'listing',
                        Review::class => 'review',
                        default => class_basename((string) $report->reportable_type),
                    },
                    'reason' => $report->reason instanceof \BackedEnum
                        ? $report->reason->value
                        : $report->reason,
                    'status' => $report->status->value,
                    'created_at' => $report->created_at?->toIso8601String(),
                ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'location' => $user->location,
            'shop_name' => $user->shop_name,
            'bio' => $user->bio,
            'contact' => $user->contact,
            'status' => $user->status->value,
            'roles' => $user->roles->pluck('name')->values()->all(),
            'farm' => $user->farm === null ? null : [
                'id' => $user->farm->id,
                'name' => $user->farm->name,
            ],
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return Collection<int, Order>
     */
    private function ordersFor(User $user): Collection
    {
        return Order::query()
            ->where(function ($query) use ($user): void {
                $query->where('buyer_id', $user->id)
                    ->orWhere('farmer_seller_id', $user->id);
            })
            ->with([
                'items:id,order_id,listing_name,quantity,unit,unit_price,line_subtotal,tawad_amount,line_total',
                'statusHistories:id,order_id,from_status,to_status,note,created_at',
                'buyer:id,name',
                'farmerSeller:id,name,shop_name',
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function order(Order $order, User $user): array
    {
        $asBuyer = $order->buyer_id === $user->id;

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'as' => $asBuyer ? 'buyer' : 'seller',
            'status' => $order->status->value,
            'source' => $order->source->value,
            'counterparty_name' => $asBuyer
                ? ($order->farmerSeller?->shop_name ?: $order->farmerSeller?->name)
                : ($order->buyer?->name ?? $order->walk_in_buyer_name),
            'subtotal' => $order->subtotal,
            'tawad_total' => $order->tawad_total,
            'total' => $order->total,
            'amount_received' => $order->amount_received,
            'items' => $order->items->map(fn ($item): array => [
                'listing_name' => $item->listing_name,
                'quantity' => $item->quantity,
                'unit' => $item->unit->value,
                'unit_price' => $item->unit_price,
                'line_subtotal' => $item->line_subtotal,
                'tawad_amount' => $item->tawad_amount,
                'line_total' => $item->line_total,
            ])->all(),
            'status_history' => $order->statusHistories->map(fn ($history): array => [
                'from_status' => $history->from_status?->value,
                'to_status' => $history->to_status->value,
                'note' => $history->note,
                'created_at' => $history->created_at?->toIso8601String(),
            ])->all(),
            'created_at' => $order->created_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
        ];
    }
}
