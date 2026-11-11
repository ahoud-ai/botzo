<?php

namespace App\Services\IntelliReply;

/**
 * Tells a request for a person ("أبغى أكلم أحد") from a sentence that only mentions one
 * ("الموظف كان محترم"), without calling the AI model.
 */
class HumanRequestDetector
{
    /** Words that can surround a keyword without changing that the message is just asking for it. */
    private const FILLER_WORDS = [
        'لو', 'سمحت', 'سمحتي', 'من', 'فضلك', 'ممكن', 'عايز', 'عاوز', 'عايزه', 'عاوزه', 'ابغي', 'ابغا', 'ابي', 'اريد', 'بدي',
        'محتاج', 'محتاجه', 'ارجو', 'يا', 'اكلم', 'اتكلم', 'مع', 'الي', 'please', 'pls', 'i', 'want', 'need', 'a', 'an', 'to',
        'the', 'talk', 'speak', 'with',
    ];

    /** Verbs that only make sense when asking to be put through. */
    private const CONTACT_VERBS = [
        'اكلم', 'اتكلم', 'نكلم', 'نتكلم', 'اتواصل', 'نتواصل', 'اتحدث', 'حولني', 'حولوني', 'تحولني', 'وصلني', 'وصلوني',
        'talk', 'speak', 'connect', 'transfer',
    ];

    /** Verbs that follow the person, as in "حد يكلمني". */
    private const REPLY_VERBS = ['يكلمني', 'يرد', 'يتواصل', 'يساعدني', 'يكلمنا'];

    private const NEGATIONS = ['مش', 'ما', 'مو', 'لا', 'مب', 'not', 'dont', 'never'];

    private const PERSON_TOKEN = '/^(ال|لل|ل|ب|بال)?(موظف|موظفه|انسان|بشري|مندوب|ممثل|شخص|حد|احد)$/u';

    private const ENGLISH_PERSON_TOKEN = '/^(agent|human|person|representative|someone)$/u';

    private const LOOKAHEAD_WORDS = 4;

    /**
     * @param  array<int, string>  $keywords  shown to count on their own, such as "موظف"
     * @param  bool  $looseKeywords  the organization wrote its own list, so any mention of a word counts
     */
    public function asksForPerson(string $text, array $keywords, bool $looseKeywords = false): bool
    {
        $normalized = $this->normalize($text);
        if ($normalized === '') {
            return false;
        }

        return $this->matchesKeyword($normalized, $keywords, $looseKeywords) || $this->matchesPhrase($normalized);
    }

    private function matchesKeyword(string $normalized, array $keywords, bool $loose): bool
    {
        $haystack = ' '.$normalized.' ';

        foreach ($keywords as $keyword) {
            $needle = $this->normalize((string) $keyword);
            if ($needle === '' || ! str_contains($haystack, ' '.$needle.' ')) {
                continue;
            }

            if ($loose) {
                return true;
            }

            $rest = trim(str_replace(' '.$needle.' ', ' ', $haystack));
            $others = $rest === '' ? [] : explode(' ', $rest);

            if (array_diff($others, self::FILLER_WORDS) === []) {
                return true;
            }
        }

        return false;
    }

    private function matchesPhrase(string $normalized): bool
    {
        $words = explode(' ', $normalized);
        $count = count($words);

        foreach ($words as $index => $word) {
            if ($this->isNegated($words, $index)) {
                continue;
            }

            if (in_array($word, self::CONTACT_VERBS, true)) {
                $window = array_slice($words, $index + 1, self::LOOKAHEAD_WORDS);
                $joined = implode(' ', $window);

                if (str_contains($joined, 'خدمه العملاء') || str_contains($joined, 'customer service')) {
                    return true;
                }

                foreach ($window as $next) {
                    if ($this->isPerson($next)) {
                        return true;
                    }
                }
            }

            if ($index + 1 < $count && $this->isPerson($word) && in_array($words[$index + 1], self::REPLY_VERBS, true)) {
                return true;
            }
        }

        return false;
    }

    private function isNegated(array $words, int $index): bool
    {
        foreach (array_slice($words, max(0, $index - 2), min($index, 2)) as $previous) {
            if (in_array($previous, self::NEGATIONS, true)) {
                return true;
            }
        }

        return false;
    }

    private function isPerson(string $word): bool
    {
        return preg_match(self::PERSON_TOKEN, $word) === 1 || preg_match(self::ENGLISH_PERSON_TOKEN, $word) === 1;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text); // tashkeel and tatweel
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
