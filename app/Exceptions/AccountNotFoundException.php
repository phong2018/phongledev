<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountNotFoundException extends \RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("Account [{$id}] not found.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 404);
    }
}
