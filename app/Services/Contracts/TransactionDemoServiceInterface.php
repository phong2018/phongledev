<?php

namespace App\Services\Contracts;

use App\Models\Account;
use Illuminate\Database\Eloquent\Collection;

interface TransactionDemoServiceInterface
{
    public function allAccounts(): Collection;

    /**
     * COMMIT demo: both debit + credit succeed atomically.
     * If anything throws, the whole unit rolls back automatically.
     */
    public function transferWithCommit(int $fromId, int $toId, string $amount): array;

    /**
     * ROLLBACK demo: debit succeeds, then we deliberately throw.
     * Because we are inside DB::transaction(), the debit is rolled back —
     * the sender's balance never actually changes.
     */
    public function transferWithRollback(int $fromId, int $toId, string $amount): array;

    /**
     * SAVEPOINT demo: two transfers inside one outer transaction.
     * The inner transfer to Account C uses a nested transaction (savepoint).
     * If the inner one fails, only that savepoint rolls back;
     * the outer transfer to Account B is still committed.
     */
    public function transferWithSavepoint(int $fromId, int $toBId, int $toCId, string $amount): array;
}
