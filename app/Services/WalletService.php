<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WalletService
{
    /**
     * Credit the user's wallet.
     */
    public function deposit(User $user, float $amount, ?string $referenceType = null, ?int $referenceId = null, array $meta = []): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Deposit amount must be positive.');
        }

        DB::transaction(function () use ($user, $amount, $referenceType, $referenceId, $meta) {
            $wallet = $user->wallet()->lockForUpdate()->firstOrCreate([
                'user_id' => $user->id
            ], [
                'balance' => 0,
                'currency' => 'TJS'
            ]);

            $wallet->increment('balance', $amount);

            $wallet->transactions()->create([
                'type' => TransactionType::Deposit,
                'amount' => $amount,
                'status' => TransactionStatus::Completed,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'meta' => $meta,
            ]);
        });
    }

    /**
     * Debit the user's wallet.
     */
    public function withdraw(User $user, float $amount, ?string $referenceType = null, ?int $referenceId = null, array $meta = []): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Withdrawal amount must be positive.');
        }

        DB::transaction(function () use ($user, $amount, $referenceType, $referenceId, $meta) {
            $wallet = $user->wallet()->lockForUpdate()->first();

            if (!$wallet || $wallet->balance < $amount) {
                throw new InvalidArgumentException('Insufficient funds in wallet.');
            }

            $wallet->decrement('balance', $amount);

            $wallet->transactions()->create([
                'type' => TransactionType::Withdrawal,
                'amount' => $amount,
                'status' => TransactionStatus::Completed,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'meta' => $meta,
            ]);
        });
    }

    /**
     * Record a payment from the wallet.
     */
    public function pay(User $user, float $amount, string $referenceType, int $referenceId, array $meta = []): void
    {
        DB::transaction(function () use ($user, $amount, $referenceType, $referenceId, $meta) {
            $this->withdraw($user, $amount, $referenceType, $referenceId, array_merge($meta, ['action' => 'payment']));
        });
    }

    /**
     * Refund to the wallet.
     */
    public function refund(User $user, float $amount, string $referenceType, int $referenceId, array $meta = []): void
    {
        DB::transaction(function () use ($user, $amount, $referenceType, $referenceId, $meta) {
            $this->deposit($user, $amount, $referenceType, $referenceId, array_merge($meta, ['action' => 'refund']));
        });
    }
}
