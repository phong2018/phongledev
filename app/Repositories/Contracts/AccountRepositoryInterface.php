<?php

namespace App\Repositories\Contracts;

use App\Models\Account;
use Illuminate\Database\Eloquent\Collection;

interface AccountRepositoryInterface
{
    public function all(): Collection;

    public function findById(int $id): ?Account;

    public function debit(Account $account, string $amount): void;

    public function credit(Account $account, string $amount): void;
}
