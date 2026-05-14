<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['owner', 'balance'])]
class Account extends Model
{
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }
}
