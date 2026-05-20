<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Addon;

class AddonsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = [
            [
                'category' => 'chat',
                'name' => 'Embedded Signup',
                'logo' => 'whatsapp.png',
                'description' => __('Embedded Signup allows app users to register using their WhatsApp account.'),
                'metadata' => NULL,
                'status' => 0,
            ],
        ];

        foreach ($rows as $row) {
            // Check if a record with the same name exists
            Addon::firstOrCreate(
                ['name' => $row['name']],
                $row 
            );
        }
    }
}
