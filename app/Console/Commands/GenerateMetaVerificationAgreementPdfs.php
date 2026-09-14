<?php

namespace App\Console\Commands;

use App\Support\MetaVerificationAgreementContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateMetaVerificationAgreementPdfs extends Command
{
    protected $signature = 'meta-verification:generate-agreement-pdfs';
    protected $description = 'Pre-generate the Arabic and English Meta verification agreement PDFs into public/documents';

    public function handle()
    {
        $outputDir = public_path('documents');
        File::ensureDirectoryExists($outputDir);

        foreach (['ar', 'en'] as $locale) {
            $content = MetaVerificationAgreementContent::for($locale);
            $data = $this->prepareData($content, $locale);

            $pdf = \Pdf::loadView('pdf.meta-verification-agreement', $data)->setPaper('a4');
            $pdf->save($outputDir.'/meta-verification-agreement-'.$locale.'.pdf');

            $this->info("Generated meta-verification-agreement-{$locale}.pdf");
        }

        return self::SUCCESS;
    }

    private function prepareData(array $content, string $locale): array
    {
        $dir = $content['dir'];
        $rtl = $dir === 'rtl';

        if ($locale === 'ar') {
            $arabicShaper = new \ArPHP\I18N\Arabic();
            // max_chars must stay well under the article box's actual line width: utf8Glyphs()
            // shapes/reverses per-line, and if a "line" it produces is still too wide, dompdf's
            // own word-wrap re-breaks that already-reversed text and scrambles line order.
            $shape = fn (string $text) => $this->fixArabicLatinSpacing($arabicShaper->utf8Glyphs($text, 90, false, false));
        } else {
            $shape = fn (string $text) => $text;
        }

        $articles = [];
        foreach ($content['articles'] as $index => $article) {
            $articles[] = [
                'number' => $index + 1,
                'heading' => $shape($content['articleLabel'].' '.($index + 1).' — '.$article['title']),
                'body' => array_map($shape, $article['body']),
            ];
        }

        $cols = [
            ['title' => $shape($content['whatItIncludesTitle']), 'items' => array_map($shape, $content['whatItIncludes'])],
            ['title' => $shape($content['whatWeNeedTitle']), 'items' => array_map($shape, $content['whatWeNeed'])],
        ];

        $chips = array_map(function (array $chip) use ($shape) {
            return ['label' => $shape($chip['label']), 'value' => $shape($chip['value'])];
        }, $content['chips']);

        $officialFields = array_map(function (array $field) use ($shape) {
            return ['label' => $shape($field['label']), 'value' => $shape($field['value'])];
        }, $content['officialFields']);

        $signFields = array_map(function (array $field) use ($shape) {
            return ['label' => $shape($field['label'])];
        }, $content['signFields']);

        $footerContacts = array_map($shape, $content['footerContacts']);

        if ($rtl) {
            $chips = array_reverse($chips);
            $cols = array_reverse($cols);
            $officialFields = array_reverse($officialFields);
            $signFields = array_reverse($signFields);
            $footerContacts = array_reverse($footerContacts);
        }

        return [
            'dir' => $dir,
            'headerBadge' => $shape($content['headerBadge']),
            'offerTitle' => $shape($content['offerTitle']),
            'offerDesc' => $shape($content['offerDesc']),
            'priceLabel' => $shape($content['priceLabel']),
            'priceValue' => $shape($content['priceValue']),
            'priceNote' => $shape($content['priceNote']),
            'accreditationBadge' => $shape($content['accreditationBadge']),
            'accreditationTitle' => $shape($content['accreditationTitle']),
            'accreditationDesc' => $shape($content['accreditationDesc']),
            'chips' => $chips,
            'cols' => $cols,
            'aboutTitle' => $shape($content['aboutTitle']),
            'aboutDesc' => $shape($content['aboutDesc']),
            'footerContacts' => $footerContacts,
            'agreementBadge' => $shape($content['agreementBadge']),
            'agreementTitle' => $shape($content['agreementTitle']),
            'agreementIntro' => $shape($content['agreementIntro']),
            'articles' => $articles,
            'noticeTitle' => $shape($content['noticeTitle']),
            'noticeBody' => $shape($content['noticeBody']),
            'officialLabel' => $shape($content['officialLabel']),
            'officialName' => $shape($content['officialName']),
            'officialFields' => $officialFields,
            'signFields' => $signFields,
            'articleBadgeFirst' => ! $rtl,
        ];
    }

    /**
     * ar-php's utf8Glyphs() reorders mixed Arabic/Latin runs for RTL display but
     * loses the space at the Arabic/Latin boundary in the process. This restores
     * it, while leaving a standalone "و" (and) connector glued to the following
     * Latin word alone, since that is correct Arabic grammar.
     */
    private function fixArabicLatinSpacing(string $shaped): string
    {
        $arabicRange = '\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}';

        // Arabic punctuation (، ؛ ؟) should hug the preceding word, never get a forced space.
        $shaped = preg_replace('/([A-Za-z0-9\)])(?![،؛؟])(['.$arabicRange.'])/u', '$1 $2', $shaped);

        return preg_replace_callback(
            '/(^|.)(['.$arabicRange.'])([A-Za-z0-9\(])/u',
            function (array $matches) {
                [, $before, $arabicChar, $latinChar] = $matches;
                $isStandaloneWawConnector = $arabicChar === 'و' && ($before === '' || $before === ' ');
                $separator = $isStandaloneWawConnector ? '' : ' ';

                return $before.$arabicChar.$separator.$latinChar;
            },
            $shaped
        );
    }
}
