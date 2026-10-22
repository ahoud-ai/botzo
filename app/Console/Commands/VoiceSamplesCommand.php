<?php

namespace App\Console\Commands;

use App\Services\IntelliReply\AiKeyResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Generates the same Saudi-dialect lines with OpenAI and ElevenLabs voices and publishes them on
 * a temporary, unlisted page so the voice for the widget's calls can be chosen by ear.
 * --cleanup removes every published sample.
 */
class VoiceSamplesCommand extends Command
{
    protected $signature = 'botzo:voice-samples
        {--cleanup : Delete all published voice samples}';

    protected $description = 'Publish voice samples (OpenAI and ElevenLabs) on a temporary page to pick the widget voice.';

    private const LINES = 'هلا والله! وش أقدر أساعدك فيه؟ أبشر، خلني أوريك الأسعار. تمام، الربط ياخذ دقيقتين بس. تبي أحجز لك عرض توضيحي؟';

    private const OPENAI_VOICES = ['marin', 'cedar', 'coral', 'sage', 'shimmer', 'ash', 'ballad', 'verse', 'alloy', 'echo'];

    private const OPENAI_INSTRUCTIONS = 'Speak in a light, friendly, casual Saudi Arabic dialect (white Saudi dialect), like a warm young customer-service employee chatting naturally. Not formal, not Modern Standard Arabic, not robotic. Slightly upbeat, a little faster than normal.';

    public function handle(AiKeyResolver $keys): int
    {
        $root = public_path('voice-samples');

        if ($this->option('cleanup')) {
            File::deleteDirectory($root);
            $this->components->info('All voice samples deleted.');

            return self::SUCCESS;
        }

        $folder = Str::lower(Str::random(24));
        $directory = $root.DIRECTORY_SEPARATOR.$folder;
        File::ensureDirectoryExists($directory);

        $samples = array_merge(
            $this->openAiSamples($keys->resolveGlobalKey(), $directory),
            $this->elevenLabsSamples((string) config('services.elevenlabs.key'), $directory)
        );

        if ($samples === []) {
            File::deleteDirectory($directory);
            $this->components->error('No sample could be generated.');

            return self::FAILURE;
        }

        File::put($directory.DIRECTORY_SEPARATOR.'index.html', $this->page($samples));

        $this->newLine();
        $this->components->info(count($samples).' samples ready. Open:');
        $this->line(url('/voice-samples/'.$folder.'/'));
        $this->line('Delete them afterwards with: php artisan botzo:voice-samples --cleanup');

        return self::SUCCESS;
    }

    /**
     * @return list<array{provider:string,name:string,file:string}>
     */
    private function openAiSamples(?string $apiKey, string $directory): array
    {
        if (! $apiKey) {
            $this->components->warn('OpenAI: no platform key, skipped.');

            return [];
        }

        $samples = [];
        foreach (self::OPENAI_VOICES as $voice) {
            $response = Http::withToken($apiKey)->timeout(60)->post('https://api.openai.com/v1/audio/speech', [
                'model' => 'gpt-4o-mini-tts',
                'voice' => $voice,
                'input' => self::LINES,
                'instructions' => self::OPENAI_INSTRUCTIONS,
                'response_format' => 'mp3',
            ]);

            if (! $response->successful()) {
                $this->line("  OpenAI {$voice}: failed ({$response->status()})");

                continue;
            }

            $file = 'openai-'.$voice.'.mp3';
            File::put($directory.DIRECTORY_SEPARATOR.$file, $response->body());
            $samples[] = ['provider' => 'OpenAI', 'name' => $voice, 'file' => $file];
            $this->line("  OpenAI {$voice}: ok");
        }

        return $samples;
    }

    /**
     * Samples every voice added to the ElevenLabs account ("My voices"), premade ones excluded.
     *
     * @return list<array{provider:string,name:string,file:string}>
     */
    private function elevenLabsSamples(string $apiKey, string $directory): array
    {
        if ($apiKey === '') {
            $this->components->warn('ElevenLabs: ELEVENLABS_API_KEY is not set, skipped.');

            return [];
        }

        $list = Http::withHeaders(['xi-api-key' => $apiKey])->timeout(30)->get('https://api.elevenlabs.io/v1/voices');
        if (! $list->successful()) {
            $this->components->warn("ElevenLabs: could not list voices ({$list->status()}).");

            return [];
        }

        $voices = collect((array) $list->json('voices'))->reject(fn ($voice) => ($voice['category'] ?? '') === 'premade')->take(8);
        if ($voices->isEmpty()) {
            $this->components->warn('ElevenLabs: no voices in "My voices". Add Arabic (Saudi/Gulf) voices from the Voice Library first.');

            return [];
        }

        $samples = [];
        foreach ($voices as $voice) {
            $name = (string) ($voice['name'] ?? $voice['voice_id']);
            $response = Http::withHeaders(['xi-api-key' => $apiKey, 'Accept' => 'audio/mpeg'])
                ->timeout(60)
                ->post('https://api.elevenlabs.io/v1/text-to-speech/'.rawurlencode((string) $voice['voice_id']).'?output_format=mp3_44100_128', [
                    'text' => self::LINES,
                    'model_id' => (string) config('services.elevenlabs.model', 'eleven_multilingual_v2'),
                    'voice_settings' => ['stability' => 0.4, 'similarity_boost' => 0.8, 'style' => 0.3, 'use_speaker_boost' => true],
                ]);

            if (! $response->successful()) {
                $this->line("  ElevenLabs {$name}: failed ({$response->status()})");

                continue;
            }

            $file = 'elevenlabs-'.Str::slug($name).'-'.substr(md5((string) $voice['voice_id']), 0, 6).'.mp3';
            File::put($directory.DIRECTORY_SEPARATOR.$file, $response->body());
            $samples[] = ['provider' => 'ElevenLabs', 'name' => $name, 'file' => $file];
            $this->line("  ElevenLabs {$name}: ok");
        }

        return $samples;
    }

    /**
     * @param  list<array{provider:string,name:string,file:string}>  $samples
     */
    private function page(array $samples): string
    {
        $rows = collect($samples)->map(fn (array $sample, int $index) => sprintf(
            '<li><span class="n">%d</span><div><strong>%s</strong> <em>%s</em><audio controls preload="none" src="%s"></audio></div></li>',
            $index + 1,
            e($sample['name']),
            e($sample['provider']),
            e($sample['file'])
        ))->implode("\n");

        return '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>عينات صوت موتشي</title><style>body{margin:0;background:#0A0E16;color:#F2F5F9;font-family:Tahoma,Arial,sans-serif}main{max-width:720px;margin:0 auto;padding:24px 16px}'
            .'h1{font-size:22px}p{color:#A3ADBD;line-height:1.7}ul{list-style:none;padding:0}li{display:flex;gap:12px;align-items:flex-start;background:#1A2333;border-radius:14px;padding:14px;margin-bottom:10px}'
            .'.n{min-width:28px;height:28px;border-radius:999px;background:#22D366;color:#0A0E16;font-weight:700;display:flex;align-items:center;justify-content:center}em{color:#7C8799;font-style:normal;font-size:13px}audio{display:block;width:100%;margin-top:8px}div{flex:1}</style></head>'
            .'<body><main><h1>عينات صوت موتشي</h1><p>كل العينات بنفس الجمل: «'.e(self::LINES).'». اسمعهم وقولي رقم الصوت اللي لهجته أخف وأقرب للسعودي.</p><ul>'.$rows.'</ul></main></body></html>';
    }
}
