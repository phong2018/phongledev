<?php

namespace App\Services;

use App\Exceptions\AccountNotFoundException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Services\Contracts\TransactionDemoServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TransactionDemoService implements TransactionDemoServiceInterface
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepo,
    ) {}

    public function allAccounts(): Collection
    {
        return $this->accountRepo->all();
    }

    // -------------------------------------------------------------------------
    // DEMO 1 — COMMIT
    // Both debit and credit run inside one transaction.
    // MySQL only makes the changes visible to other connections on COMMIT.
    // If an exception fires anywhere inside, Laravel calls ROLLBACK automatically.
    // -------------------------------------------------------------------------
    public function transferWithCommit(int $fromId, int $toId, string $amount): array
    {
        $from = $this->resolveAccount($fromId);
        $to   = $this->resolveAccount($toId);

        $this->guardBalance($from, $amount);

        $snapBefore = $this->snapshot();

        DB::transaction(function () use ($from, $to, $amount) {
            // Step 1 — debit sender (row-level lock acquired inside repo)
            $this->accountRepo->debit($from, $amount);

            // Step 2 — credit receiver
            $this->accountRepo->credit($to, $amount);

            // Both steps reached → MySQL commits on closure exit.
        });

        return [
            'demo'       => 'COMMIT',
            'explanation'=> 'Both debit and credit committed atomically. '
                           .'Other connections only see the new balances after COMMIT.',
            'before'     => $snapBefore,
            'after'      => $this->snapshot(),
        ];
    }

    // -------------------------------------------------------------------------
    // DEMO 2 — ROLLBACK
    // Debit fires successfully, then we simulate a downstream failure
    // (e.g. a third-party payment API crash). Because we are still inside the
    // transaction, MySQL discards the debit — money never left the account.
    // -------------------------------------------------------------------------
    public function transferWithRollback(int $fromId, int $toId, string $amount): array
    {
        $from = $this->resolveAccount($fromId);
        $to   = $this->resolveAccount($toId);

        $this->guardBalance($from, $amount);

        $snapBefore = $this->snapshot();

        try {
            DB::transaction(function () use ($from, $to, $amount) {
                // Step 1 — debit succeeds
                $this->accountRepo->debit($from, $amount);

                // Step 2 — simulated crash before credit
                throw new \RuntimeException('Payment gateway timeout — simulated failure.');

                // This line is never reached; MySQL rolls back the debit above.
                $this->accountRepo->credit($to, $amount);
            });
        } catch (\RuntimeException $e) {
            // DB::transaction() already issued ROLLBACK before re-throwing.
            return [
                'demo'        => 'ROLLBACK',
                'explanation' => 'Debit ran but a failure occurred before credit. '
                                .'MySQL rolled back the debit — sender balance is unchanged.',
                'error'       => $e->getMessage(),
                'before'      => $snapBefore,
                'after'       => $this->snapshot(), // same as before — rollback worked
            ];
        }

        // Unreachable; the catch above always returns.
        return [];
    }

    // -------------------------------------------------------------------------
    // DEMO 3 — SAVEPOINT (nested transactions)
    // Laravel wraps each nested DB::transaction() call in a MySQL SAVEPOINT.
    //
    //   OUTER transaction  ──────────────────────────────────────────►  COMMIT
    //     debit from A          ✓ committed with outer
    //     credit to B           ✓ committed with outer
    //     SAVEPOINT sp1
    //       credit to C         ✗ simulated failure → ROLLBACK TO sp1
    //     RELEASE SAVEPOINT sp1
    //
    // Result: A→B transfer is committed; A→C transfer is rolled back.
    // -------------------------------------------------------------------------
    public function transferWithSavepoint(int $fromId, int $toBId, int $toCId, string $amount): array
    {
        $from = $this->resolveAccount($fromId);
        $toB  = $this->resolveAccount($toBId);
        $toC  = $this->resolveAccount($toCId);

        $this->guardBalance($from, bcmul($amount, '2', 2)); // needs 2× amount

        $snapBefore = $this->snapshot();
        $savepointError = null;

        DB::transaction(function () use ($from, $toB, $toC, $amount, &$savepointError) {

            // ── Outer: debit A, credit B ─────────────────────────────────────
            $this->accountRepo->debit($from, $amount);
            $this->accountRepo->credit($toB, $amount);

            // ── Inner (SAVEPOINT): try to also credit C ──────────────────────
            try {
                DB::transaction(function () use ($from, $toC, $amount) {
                    $this->accountRepo->debit($from, $amount);

                    // Simulate failure mid-way through the inner transaction
                    throw new \RuntimeException('Inner transfer to Account C failed — simulated.');

                    $this->accountRepo->credit($toC, $amount);
                });
            } catch (\RuntimeException $e) {
                // MySQL executed: ROLLBACK TO SAVEPOINT sp1
                // The outer debit+credit (A→B) is untouched.
                $savepointError = $e->getMessage();
            }

            // Outer transaction continues and will COMMIT the A→B transfer.
        });

        return [
            'demo'            => 'SAVEPOINT',
            'explanation'     => 'Outer transaction (A→B) committed. '
                                .'Inner savepoint (A→C) rolled back independently. '
                                .'Outer COMMIT is unaffected by the inner failure.',
            'savepoint_error' => $savepointError,
            'before'          => $snapBefore,
            'after'           => $this->snapshot(),
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function resolveAccount(int $id): Account
    {
        return $this->accountRepo->findById($id)
            ?? throw new AccountNotFoundException($id);
    }

    private function guardBalance(Account $account, string $amount): void
    {
        if (bccomp($account->balance, $amount, 2) < 0) {
            throw new InsufficientBalanceException(
                $account->owner,
                $account->balance,
                $amount,
            );
        }
    }

    private function snapshot(): array
    {
        return $this->accountRepo->all()
            ->map(fn (Account $a) => [
                'id'      => $a->id,
                'owner'   => $a->owner,
                'balance' => $a->balance,
            ])
            ->all();
    }
}
