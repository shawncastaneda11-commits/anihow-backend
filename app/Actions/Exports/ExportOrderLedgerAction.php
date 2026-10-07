<?php

namespace App\Actions\Exports;

use App\Enums\ExportType;
use App\Enums\Permission;
use App\Models\ExportLog;
use App\Models\Order;
use App\Models\User;
use App\Support\CsvDownload;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportOrderLedgerAction
{
    /**
     * @var list<string>
     */
    public const COLUMNS = [
        'order_number',
        'source',
        'status',
        'farm',
        'seller_shop_name',
        'buyer_name',
        'placed_at',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'subtotal',
        'tawad_total',
        'total',
        'amount_received',
        'fulfillment_preference',
    ];

    public function __construct(private CsvDownload $csv) {}

    /**
     * @param  Builder<Order>  $query
     * @param  array<string, mixed>  $filters
     */
    public function download(User $actor, Builder $query, array $filters = []): StreamedResponse
    {
        abort_unless($actor->can(Permission::GenerateExports->value), 403);

        $filename = 'anihow-orders-'.now()->toDateString().'.csv';
        $rowCount = (clone $query)->count();

        ExportLog::query()->create([
            'user_id' => $actor->id,
            'type' => ExportType::OrderLedger,
            'filters' => $filters,
            'row_count' => $rowCount,
        ]);

        return $this->csv->stream($filename, function ($handle) use ($query): void {
            fputcsv($handle, $this->csv->row(self::COLUMNS));

            (clone $query)
                ->with(['farm', 'farmerSeller', 'buyer'])
                ->reorder()
                ->orderBy('id')
                ->chunkById(200, function ($orders) use ($handle): void {
                    foreach ($orders as $order) {
                        fputcsv($handle, $this->csv->row($this->row($order)));
                    }
                });
        });
    }

    /**
     * @return list<string|float|int|null>
     */
    private function row(Order $order): array
    {
        $buyerName = $order->isWalkIn()
            ? ($order->walk_in_buyer_name ?? '')
            : ($order->buyer?->name ?? '');

        return [
            $order->order_number,
            $order->source->value,
            $order->status->value,
            $order->farm?->name,
            $order->farmerSeller?->shop_name,
            $buyerName,
            $order->created_at?->toDateTimeString(),
            $order->confirmed_at?->toDateTimeString(),
            $order->completed_at?->toDateTimeString(),
            $order->cancelled_at?->toDateTimeString(),
            $order->cancellation_reason?->value,
            (float) $order->subtotal,
            (float) $order->tawad_total,
            (float) $order->total,
            $order->amount_received === null ? null : (float) $order->amount_received,
            $order->fulfillment_preference->value,
        ];
    }
}
