<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Addon;

class AddonsTableSeeder4 extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = [
            [
                'category' => 'utility',
                'name' => 'Flow builder',
                'logo' => 'flow_icon.png',
                'description' => __('Build WhatsApp qualification journeys with CRM updates and visual customer paths.'),
                'metadata' => json_encode([
                    'name' => 'FlowBuilder',
                ]),
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
