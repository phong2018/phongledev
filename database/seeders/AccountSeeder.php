<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        Account::truncate();

        Account::insert([
            ['owner' => 'Alice', 'balance' => '1000.00', 'created_at' => now(), 'updated_at' => now()],
            ['owner' => 'Bob',   'balance' => '500.00',  'created_at' => now(), 'updated_at' => now()],
            ['owner' => 'Carol', 'balance' => '250.00',  'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
