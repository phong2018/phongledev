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
        // lockForUpdate() holds a row-level lock until the transaction commits,
        // preventing double-spend when concurrent requests hit the same account.
        Account::lockForUpdate()->findOrFail($account->id);

        $account->decrement('balance', $amount);
    }

    public function credit(Account $account, string $amount): void
    {
        $account->increment('balance', $amount);
    }
}
