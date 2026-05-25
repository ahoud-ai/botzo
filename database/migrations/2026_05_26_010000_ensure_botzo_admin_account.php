<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $now = now();
        $email = 'botzo.sass@gmail.com';

        $payload = [
            'first_name' => 'Botzo',
            'last_name' => 'Admin',
            'role' => 'admin',
            'status' => 1,
            'email_verified_at' => $now,
            'password' => '$2y$10$gSpidlrIbcT4quaqgBNee.E.3V7K9NRqHg0nq4e9F4pvvLZMT4uPq',
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('users', 'is_system_owner')) {
            $payload['is_system_owner'] = true;
        }

        $existing = DB::table('users')
            ->where('email', $email)
            ->first(['id']);

        if ($existing) {
            DB::table('users')
                ->where('id', $existing->id)
                ->update($payload);

            return;
        }

        $insertPayload = array_merge($payload, [
            'email' => $email,
            'created_at' => $now,
        ]);

        if (Schema::hasColumn('users', 'uuid')) {
            $insertPayload['uuid'] = (string) Str::uuid();
        }

        DB::table('users')->insert($insertPayload);
    }

    public function down(): void
    {
        // Keep production admin access intact on rollback.
    }
};
