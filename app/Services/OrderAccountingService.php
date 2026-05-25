<?php

namespace App\Services;

use App\Enums\FinancialTransactionCategory;
use App\Enums\FinancialTransactionType;
use App\Enums\OrderStatus;
use App\Models\Order;

class OrderAccountingService
{
    /**
     * Keep a single income (sales) record in sync with a paid order's
     * recognized revenue. Removes the record when the order is no longer paid
     * or is cancelled.
     */
    public function syncPaymentRecord(Order $order): void
    {
        $order->refresh();

        $record = $order->financialRecords()
            ->where('type', FinancialTransactionType::Income)
            ->where('category', FinancialTransactionCategory::Sales)
            ->first();

        $shouldRecord = $order->status->value !== OrderStatus::Cancelled
            && $order->isFullyPaid();

        if (! $shouldRecord) {
            $record?->delete();

            return;
        }

        $amount = round($order->grand_total, 2);

        if ($amount <= 0) {
            $record?->delete();

            return;
        }

        $recordedBy = auth()->id() ?? $order->created_by;
        if (! $recordedBy) {
            return;
        }

        $description = "Order {$order->order_number} payment";

        if ($record) {
            $record->update([
                'amount'      => $amount,
                'description' => $description,
            ]);

            return;
        }

        $order->financialRecords()->create([
            'amount'             => $amount,
            'type'               => FinancialTransactionType::Income,
            'category'           => FinancialTransactionCategory::Sales,
            'description'        => $description,
            'transaction_date'     => now(),
            'recorded_by_id'       => $recordedBy,
        ]);
    }
}
