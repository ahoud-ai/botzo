<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ArabicLanguageSeeder extends Seeder
{
    public function run(): void
    {
        Language::updateOrCreate(
            ['code' => 'ar'],
            [
                'name' => 'Arabic',
                'status' => 'active',
                'is_rtl' => true,
                'deleted_at' => null,
                'deleted_by' => null,
            ]
        );

        $arabicFile = base_path('lang/ar.json');
        $englishFile = base_path('lang/en.json');

        if (!File::exists($arabicFile) && File::exists($englishFile)) {
            File::copy($englishFile, $arabicFile);
        }
    }
}

