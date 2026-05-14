<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsufficientBalanceException extends \RuntimeException
{
    public function __construct(string $owner, string $balance, string $amount)
    {
        parent::__construct(
            "Account '{$owner}' has insufficient balance ({$balance}). Tried to debit {$amount}."
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 422);
    }
}
