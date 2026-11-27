<?php

namespace Modules\Subscriptions\Application;

use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Subscriptions\Domain\Models\Subscription;
use Modules\Subscriptions\Domain\Models\SubscriptionPayment;

final class RecordPayment
{
    use RecordsSubscriptionEvents;

    /**
     * Records a manual payment (bank transfer, cash, cheque, online). A repeated (method, reference) pair
     * returns the original payment, so double-submitting the form never double-counts revenue.
     */
    public function handle(Subscription $subscription, int $amountMinor, string $method, ?string $reference, CarbonInterface $paidAt, ?int $adminId = null): SubscriptionPayment
    {
        if ($reference !== null) {
            $existing = SubscriptionPayment::query()->where('method', $method)->where('reference', $reference)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        try {
            return DB::transaction(function () use ($subscription, $amountMinor, $method, $reference, $paidAt, $adminId): SubscriptionPayment {
                $payment = SubscriptionPayment::create([
                    'subscription_id' => $subscription->id,
                    'amount_minor' => $amountMinor,
                    'currency' => $subscription->currency,
                    'method' => $method,
                    'reference' => $reference,
                    'paid_at' => $paidAt,
                    'recorded_by' => $adminId,
                ]);

                $this->recordEvent($subscription, 'payment_recorded', ['amount_minor' => $amountMinor, 'method' => $method, 'reference' => $reference], $adminId);

                return $payment;
            });
        } catch (UniqueConstraintViolationException $e) {
            return SubscriptionPayment::query()->where('method', $method)->where('reference', $reference)->firstOrFail();
        }
    }
}
