<?php

namespace App\Actions\Orders;

use App\Enums\CancellationReason;
use App\Enums\OrderActor;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderStateMachine;
use App\Support\InAppNotifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SweepStalePlacedOrdersAction
{
    public function __construct(
        private OrderStateMachine $stateMachine,
        private InAppNotifier $notifier,
    ) {}

    /**
     * @return array{reminded: int, cancelled: int}
     */
    public function handle(): array
    {
        $cancelled = $this->cancelUnresponsive();
        $reminded = $this->remindWaitingSellers();

        return [
            'reminded' => $reminded,
            'cancelled' => $cancelled,
        ];
    }

    private function cancelUnresponsive(): int
    {
        $cutoff = now()->subHours((int) config('anihow.order_auto_cancel_after_hours'));
        $cancelled = 0;

        Order::query()
            ->where('status', OrderStatus::Placed)
            ->where('source', OrderSource::App)
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->each(function (Order $order) use (&$cancelled): void {
                try {
                    $this->stateMachine->transition(
                        $order,
                        OrderStatus::Cancelled,
                        OrderActor::System,
                        note: 'Automatically cancelled after the seller did not respond.',
                        reason: CancellationReason::SellerUnresponsive,
                    );
                } catch (ValidationException) {
                    Log::info('Skipping stale order that changed state mid-sweep.', [
                        'order_id' => $order->id,
                    ]);

                    return;
                }

                $cancelled++;
            });

        return $cancelled;
    }

    private function remindWaitingSellers(): int
    {
        $cutoff = now()->subHours((int) config('anihow.order_reminder_after_hours'));
        $reminded = 0;

        Order::query()
            ->where('status', OrderStatus::Placed)
            ->where('source', OrderSource::App)
            ->whereNull('reminder_sent_at')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->each(function (Order $order) use (&$reminded): void {
                $claimed = Order::query()
                    ->whereKey($order->id)
                    ->where('status', OrderStatus::Placed)
                    ->where('source', OrderSource::App)
                    ->whereNull('reminder_sent_at')
                    ->update(['reminder_sent_at' => now()]);

                if ($claimed !== 1) {
                    return;
                }

                $order->loadMissing('farmerSeller');

                if ($order->farmerSeller !== null) {
                    $this->notifier->orderAwaitingConfirmation($order->farmerSeller, $order);
                }

                $reminded++;
            });

        return $reminded;
    }
}
