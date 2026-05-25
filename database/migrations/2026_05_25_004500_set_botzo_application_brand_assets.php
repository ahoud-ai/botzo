<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    private const LOGO = 'botzo-logo-app.png';
    private const FAVICON = 'botzo-favicon-app.png';

    public function up(): void
    {
        $this->publishBrandAsset(self::LOGO);
        $this->publishBrandAsset(self::FAVICON);

        Setting::updateOrCreate(
            ['key' => 'logo'],
            ['value' => 'public/' . self::LOGO]
        );

        Setting::updateOrCreate(
            ['key' => 'favicon'],
            ['value' => 'public/' . self::FAVICON]
        );
    }

    public function down(): void
    {
        Setting::where('key', 'logo')
            ->where('value', 'public/' . self::LOGO)
            ->update(['value' => null]);

        Setting::where('key', 'favicon')
            ->where('value', 'public/' . self::FAVICON)
            ->update(['value' => null]);
    }

    private function publishBrandAsset(string $filename): void
    {
        $source = public_path('images/brand/' . $filename);
        $target = storage_path('app/public/' . $filename);

        if (! File::exists($source)) {
            return;
        }

        File::ensureDirectoryExists(dirname($target));
        File::copy($source, $target);
    }
};
