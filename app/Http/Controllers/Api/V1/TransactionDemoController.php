<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\SavepointTransferRequest;
use App\Http\Requests\Transaction\TransferRequest;
use App\Http\Resources\AccountResource;
use App\Services\Contracts\TransactionDemoServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionDemoController extends Controller
{
    public function __construct(
        private readonly TransactionDemoServiceInterface $demoService,
    ) {}

    /**
     * GET /api/v1/demo/transactions/accounts
     * List all accounts with current balances.
     */
    public function accounts(): AnonymousResourceCollection
    {
        return AccountResource::collection($this->demoService->allAccounts());
    }

    /**
     * POST /api/v1/demo/transactions/commit
     *
     * Demonstrates: BEGIN → debit → credit → COMMIT
     * Both changes are atomic. Other connections see nothing until COMMIT.
     */
    public function commit(TransferRequest $request): JsonResponse
    {
        $result = $this->demoService->transferWithCommit(
            $request->integer('from_account_id'),
            $request->integer('to_account_id'),
            $request->string('amount')->toString(),
        );

        return response()->json($result);
    }

    /**
     * POST /api/v1/demo/transactions/rollback
     *
     * Demonstrates: BEGIN → debit → (simulated crash) → ROLLBACK
     * The debit is undone. Sender's balance never changes.
     */
    public function rollback(TransferRequest $request): JsonResponse
    {
        $result = $this->demoService->transferWithRollback(
            $request->integer('from_account_id'),
            $request->integer('to_account_id'),
            $request->string('amount')->toString(),
        );

        return response()->json($result);
    }

    /**
     * POST /api/v1/demo/transactions/savepoint
     *
     * Demonstrates: nested transactions using MySQL SAVEPOINTs.
     * Outer (A→B) commits. Inner (A→C) rolls back to its savepoint.
     * Outer commit is unaffected.
     */
    public function savepoint(SavepointTransferRequest $request): JsonResponse
    {
        $result = $this->demoService->transferWithSavepoint(
            $request->integer('from_account_id'),
            $request->integer('to_b_account_id'),
            $request->integer('to_c_account_id'),
            $request->string('amount')->toString(),
        );

        return response()->json($result);
    }
}
