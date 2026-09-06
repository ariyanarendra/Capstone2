<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoCrudCleanupSeeder extends Seeder
{
    public function run(): void
    {
        $sampleAccounts = [
            ['email' => 'yudhayudhai@gmail.com', 'identifier' => '1224017'],
            ['email' => 'ariyanarendrra@gmail.com', 'identifier' => '3224012'],
            ['email' => 'sendaljpt003@gmail.com', 'identifier' => '1333332222'],
            ['email' => 'yudha@gmail.com', 'identifier' => '3224011'],
        ];

        DB::transaction(function () use ($sampleAccounts) {
            foreach ($sampleAccounts as $sampleAccount) {
                $user = User::query()
                    ->where('email', $sampleAccount['email'])
                    ->where('identifier', $sampleAccount['identifier'])
                    ->first();

                if (!$user) {
                    continue;
                }

                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                $user->delete();
            }
        });

        $this->command?->info('Akun contoh CRUD yang disetujui telah dibersihkan.');
    }
}
