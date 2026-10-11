<?php

namespace Tests\Unit;

use App\Support\AudioDuration;
use PHPUnit\Framework\TestCase;

class AudioDurationTest extends TestCase
{
    public function test_opus_duration_is_read_from_the_last_ogg_page(): void
    {
        $this->assertEqualsWithDelta(30.0, AudioDuration::seconds(self::opus(30), 'audio/ogg'), 0.01);
        $this->assertEqualsWithDelta(150.0, AudioDuration::seconds(self::opus(150), 'audio/ogg'), 0.01);
    }

    public function test_wav_duration_is_read_from_the_header(): void
    {
        $byteRate = 32000;
        $wav = 'RIFF'.pack('V', 36 + $byteRate * 10).'WAVE'
            .'fmt '.pack('V', 16).pack('v', 1).pack('v', 1).pack('V', 16000).pack('V', $byteRate).pack('v', 2).pack('v', 16)
            .'data'.pack('V', $byteRate * 10);

        $this->assertEqualsWithDelta(10.0, AudioDuration::seconds($wav, 'audio/wav'), 0.01);
    }

    public function test_other_formats_are_estimated_from_their_size(): void
    {
        // 480,000 bytes at 64 kbps is one minute.
        $this->assertEqualsWithDelta(60.0, AudioDuration::seconds(str_repeat('x', 480000), 'audio/mpeg', 64), 0.01);
        $this->assertNull(AudioDuration::seconds('', 'audio/mpeg'));
    }

    /**
     * A minimal Ogg Opus stream: an OpusHead page and a last page whose granule
     * position covers $seconds of 48 kHz audio after a 312-sample pre-skip.
     */
    public static function opus(int $seconds): string
    {
        $page = fn (int $granule, string $payload) => 'OggS'."\0"."\0".pack('P', $granule).pack('V', 1).pack('V', 0).pack('V', 0)."\1".chr(strlen($payload)).$payload;
        $head = 'OpusHead'."\1"."\1".pack('v', 312).pack('V', 48000).pack('v', 0)."\0";

        return $page(0, $head).$page(0, 'OpusTags').str_repeat("\x7f", 2000).$page($seconds * 48000 + 312, 'audio');
    }
}
