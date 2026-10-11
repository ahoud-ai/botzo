<?php

namespace App\Support;

/**
 * Audio duration without ffmpeg.
 *
 * - Ogg (Opus or Vorbis), which is what WhatsApp voice notes use: the granule position
 *   of the last page divided by the sample rate.
 * - WAV: data size divided by the byte rate from the header.
 * - Anything else: an estimate from the file size at an assumed bitrate.
 */
class AudioDuration
{
    public static function seconds(string $audio, string $mimeType, int $fallbackBitrateKbps = 64): ?float
    {
        if ($audio === '') {
            return null;
        }

        if (str_starts_with($audio, 'OggS')) {
            return self::ogg($audio);
        }

        if (str_starts_with($audio, 'RIFF') && substr($audio, 8, 4) === 'WAVE') {
            return self::wav($audio);
        }

        return $fallbackBitrateKbps > 0 ? strlen($audio) * 8 / ($fallbackBitrateKbps * 1000) : null;
    }

    private static function ogg(string $audio): ?float
    {
        $last = strrpos($audio, 'OggS');
        // A real page header has stream structure version 0 right after the capture pattern.
        if ($last === false || strlen($audio) < $last + 14 || $audio[$last + 4] !== "\0") {
            return null;
        }

        $granule = unpack('P', substr($audio, $last + 6, 8))[1];
        $preSkip = 0;

        if (($opus = strpos($audio, 'OpusHead')) !== false && strlen($audio) >= $opus + 12) {
            // Opus granule positions always count 48 kHz samples.
            $rate = 48000;
            $preSkip = unpack('v', substr($audio, $opus + 10, 2))[1];
        } elseif (($vorbis = strpos($audio, "\x01vorbis")) !== false && strlen($audio) >= $vorbis + 16) {
            $rate = unpack('V', substr($audio, $vorbis + 12, 4))[1];
        } else {
            return null;
        }

        if ($rate <= 0 || $granule <= $preSkip) {
            return null;
        }

        return ($granule - $preSkip) / $rate;
    }

    private static function wav(string $audio): ?float
    {
        if (strlen($audio) < 44) {
            return null;
        }

        $byteRate = unpack('V', substr($audio, 28, 4))[1];
        $data = strpos($audio, 'data', 12);
        if ($byteRate <= 0 || $data === false || strlen($audio) < $data + 8) {
            return null;
        }

        return unpack('V', substr($audio, $data + 4, 4))[1] / $byteRate;
    }
}
