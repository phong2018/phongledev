<?php

namespace App\Repositories;

use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentAccountRepository implements AccountRepositoryInterface
{
    public function all(): Collection
    {
        return Account::orderBy('id')->get();
    }

    public function findById(int $id): ?Account
    {
        return Account::find($id);
    }

    public function debit(Account $account, string $amount): void
    {
        // Re-fetch with a row-level lock and operate on THAT instance
        $locked = Account::lockForUpdate()->findOrFail($account->id);

        if (bccomp($locked->balance, $amount, 2) < 0) {
            throw new \RuntimeException('Insufficient funds.');
        }

        $locked->decrement('balance', $amount);
    }

    public function credit(Account $account, string $amount): void
    {
        // Also lock the row on credit — protects against concurrent writes
        $locked = Account::lockForUpdate()->findOrFail($account->id);

        $locked->increment('balance', $amount);
    }
}
