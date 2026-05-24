<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class GenerateSubscriptionOrders extends Command
{
    protected $signature = 'subscriptions:generate';
    protected $description = 'Generate orders for active subscriptions that are due';

    public function handle(SubscriptionService $service): int
    {
        $due = Subscription::where('status', SubscriptionStatus::Active)
            ->whereNotNull('next_delivery_at')
            ->where('next_delivery_at', '<=', now())
            ->with('items.product')
            ->get();

        if ($due->isEmpty()) {
            $this->info('No subscriptions are due.');
            return self::SUCCESS;
        }

        $generated = 0;
        $failed = 0;

        foreach ($due as $subscription) {
            try {
                $order = $service->generate($subscription);
                $generated++;
                $this->line("  Generated order {$order->order_number} for subscription #{$subscription->id}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  Failed subscription #{$subscription->id}: {$e->getMessage()}");
                report($e);
            }
        }

        $this->info("Done. Generated: {$generated}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
