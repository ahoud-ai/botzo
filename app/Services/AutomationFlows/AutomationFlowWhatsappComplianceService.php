<?php

namespace App\Services\AutomationFlows;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AutomationFlowWhatsappComplianceService
{
    public function builderConstraints(): array
    {
        return [
            'interactive' => [
                'header_text_max_length' => 60,
                'body_max_length' => 1024,
                'footer_text_max_length' => 60,
                'buttons' => [
                    'max_count' => 3,
                    'title_max_length' => 20,
                ],
                'list' => [
                    'button_label_max_length' => 20,
                    'max_sections' => 10,
                    'max_total_rows' => 10,
                    'row_id_max_length' => 200,
                    'row_title_max_length' => 24,
                    'row_description_max_length' => 72,
                ],
            ],
            'media' => [
                'image' => [
                    'max_size_kb' => 5 * 1024,
                    'accept' => '.jpg,.jpeg,.png',
                    'extensions' => ['jpg', 'jpeg', 'png'],
                    'mime_prefixes' => ['image/'],
                    'description' => 'JPG, JPEG, PNG',
                ],
                'video' => [
                    'max_size_kb' => 16 * 1024,
                    'accept' => '.mp4,.3gp',
                    'extensions' => ['mp4', '3gp'],
                    'mime_prefixes' => ['video/'],
                    'description' => 'MP4, 3GP',
                ],
                'audio' => [
                    'max_size_kb' => 16 * 1024,
                    'accept' => '.aac,.amr,.mp3,.m4a,.ogg',
                    'extensions' => ['aac', 'amr', 'mp3', 'm4a', 'ogg'],
                    'mime_prefixes' => ['audio/'],
                    'description' => 'AAC, AMR, MP3, M4A, OGG',
                ],
                'document' => [
                    'max_size_kb' => 100 * 1024,
                    'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt',
                    'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'],
                    'mime_prefixes' => [],
                    'description' => 'PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT',
                ],
            ],
        ];
    }

    public function interactiveTextHeader(?string $value): array
    {
        $text = trim((string) $value);

        if ($text === '') {
            return [];
        }

        return [
            'type' => 'text',
            'text' => $text,
        ];
    }

    public function validateSendButtonsConfig(array $config): array
    {
        $constraints = Arr::get($this->builderConstraints(), 'interactive', []);
        $maxButtons = (int) Arr::get($constraints, 'buttons.max_count', 3);
        $buttonTitleMax = (int) Arr::get($constraints, 'buttons.title_max_length', 20);
        $headerMax = (int) Arr::get($constraints, 'header_text_max_length', 60);
        $bodyMax = (int) Arr::get($constraints, 'body_max_length', 1024);
        $footerMax = (int) Arr::get($constraints, 'footer_text_max_length', 60);

        $errors = [];
        $buttons = collect(Arr::get($config, 'buttons', []))
            ->filter(fn ($button) => filled(Arr::get($button, 'id')) && filled(Arr::get($button, 'title')))
            ->values();

        if ($buttons->count() > $maxButtons) {
            $errors[] = __('WhatsApp reply buttons support up to :count choices per message.', ['count' => $maxButtons]);
        }

        if ($this->exceedsLength((string) Arr::get($config, 'header', ''), $headerMax)) {
            $errors[] = __('Interactive headers support up to :count characters.', ['count' => $headerMax]);
        }

        if ($this->exceedsLength((string) Arr::get($config, 'body', ''), $bodyMax)) {
            $errors[] = __('Interactive message bodies support up to :count characters.', ['count' => $bodyMax]);
        }

        if ($this->exceedsLength((string) Arr::get($config, 'footer', ''), $footerMax)) {
            $errors[] = __('Interactive footers support up to :count characters.', ['count' => $footerMax]);
        }

        foreach ($buttons as $index => $button) {
            if ($this->exceedsLength((string) Arr::get($button, 'title', ''), $buttonTitleMax)) {
                $errors[] = __('Reply button :number supports up to :count characters.', [
                    'number' => $index + 1,
                    'count' => $buttonTitleMax,
                ]);
            }
        }

        return $errors;
    }

    public function validateSendListConfig(array $config): array
    {
        $constraints = Arr::get($this->builderConstraints(), 'interactive', []);
        $listConstraints = Arr::get($constraints, 'list', []);
        $headerMax = (int) Arr::get($constraints, 'header_text_max_length', 60);
        $bodyMax = (int) Arr::get($constraints, 'body_max_length', 1024);
        $footerMax = (int) Arr::get($constraints, 'footer_text_max_length', 60);
        $buttonLabelMax = (int) Arr::get($listConstraints, 'button_label_max_length', 20);
        $maxSections = (int) Arr::get($listConstraints, 'max_sections', 10);
        $maxTotalRows = (int) Arr::get($listConstraints, 'max_total_rows', 10);
        $rowIdMax = (int) Arr::get($listConstraints, 'row_id_max_length', 200);
        $rowTitleMax = (int) Arr::get($listConstraints, 'row_title_max_length', 24);
        $rowDescriptionMax = (int) Arr::get($listConstraints, 'row_description_max_length', 72);

        $errors = [];
        $sections = collect(Arr::get($config, 'sections', []))
            ->filter(fn ($section) => collect(Arr::get($section, 'rows', []))
                ->contains(fn ($row) => filled(Arr::get($row, 'id')) || filled(Arr::get($row, 'title')) || filled(Arr::get($row, 'description'))))
            ->values();

        if ($sections->count() > $maxSections) {
            $errors[] = __('WhatsApp list messages support up to :count sections.', ['count' => $maxSections]);
        }

        $totalRows = $sections
            ->flatMap(fn ($section) => Arr::get($section, 'rows', []))
            ->filter(fn ($row) => filled(Arr::get($row, 'id')) || filled(Arr::get($row, 'title')) || filled(Arr::get($row, 'description')))
            ->count();

        if ($totalRows > $maxTotalRows) {
            $errors[] = __('WhatsApp list messages support up to :count rows in total across all sections.', ['count' => $maxTotalRows]);
        }

        if ($this->exceedsLength((string) Arr::get($config, 'header', ''), $headerMax)) {
            $errors[] = __('Interactive headers support up to :count characters.', ['count' => $headerMax]);
        }

        if ($this->exceedsLength((string) Arr::get($config, 'body', ''), $bodyMax)) {
            $errors[] = __('Interactive message bodies support up to :count characters.', ['count' => $bodyMax]);
        }

        if ($this->exceedsLength((string) Arr::get($config, 'footer', ''), $footerMax)) {
            $errors[] = __('Interactive footers support up to :count characters.', ['count' => $footerMax]);
        }

        if ($this->exceedsLength((string) Arr::get($config, 'button_label', ''), $buttonLabelMax)) {
            $errors[] = __('List button labels support up to :count characters.', ['count' => $buttonLabelMax]);
        }

        foreach ($sections as $sectionIndex => $section) {
            foreach (collect(Arr::get($section, 'rows', []))->values() as $rowIndex => $row) {
                if ($this->exceedsLength((string) Arr::get($row, 'id', ''), $rowIdMax)) {
                    $errors[] = __('List row IDs support up to :count characters.', ['count' => $rowIdMax]);
                }

                if ($this->exceedsLength((string) Arr::get($row, 'title', ''), $rowTitleMax)) {
                    $errors[] = __('Row :row in section :section supports up to :count characters for the title.', [
                        'row' => $rowIndex + 1,
                        'section' => $sectionIndex + 1,
                        'count' => $rowTitleMax,
                    ]);
                }

                if ($this->exceedsLength((string) Arr::get($row, 'description', ''), $rowDescriptionMax)) {
                    $errors[] = __('Row :row in section :section supports up to :count characters for the description.', [
                        'row' => $rowIndex + 1,
                        'section' => $sectionIndex + 1,
                        'count' => $rowDescriptionMax,
                    ]);
                }
            }
        }

        return $errors;
    }

    public function validateUploadedAsset(?string $mediaKind, UploadedFile $file): array
    {
        $kind = strtolower(trim((string) $mediaKind));
        $constraint = Arr::get($this->builderConstraints(), "media.{$kind}");

        if ($kind === '' || !is_array($constraint)) {
            return [__('Choose which WhatsApp media type this asset should use.')];
        }

        $errors = [];
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $mimeType = strtolower((string) $file->getMimeType());
        $allowedExtensions = Arr::get($constraint, 'extensions', []);
        $maxSizeKb = (int) Arr::get($constraint, 'max_size_kb', 0);
        $mimePrefixes = Arr::get($constraint, 'mime_prefixes', []);

        if ($allowedExtensions !== [] && !in_array($extension, $allowedExtensions, true)) {
            $errors[] = __('This :media file type is not supported by WhatsApp. Allowed formats: :formats.', [
                'media' => $kind,
                'formats' => (string) Arr::get($constraint, 'description', strtoupper(implode(', ', $allowedExtensions))),
            ]);
        }

        if (!$this->mimeMatchesMediaKind($mimeType, $kind, $mimePrefixes)) {
            $errors[] = __('Uploaded file MIME type does not match the selected :media media type.', [
                'media' => $kind,
            ]);
        }

        if ($maxSizeKb > 0 && (int) ceil(((int) $file->getSize()) / 1024) > $maxSizeKb) {
            $errors[] = __('WhatsApp supports up to :size MB for :media files.', [
                'size' => $this->formatMegabytes($maxSizeKb),
                'media' => $kind,
            ]);
        }

        return $errors;
    }

    private function exceedsLength(string $value, int $maxLength): bool
    {
        return $maxLength > 0 && mb_strlen(trim($value)) > $maxLength;
    }

    private function formatMegabytes(int $kilobytes): string
    {
        return rtrim(rtrim(number_format($kilobytes / 1024, 2, '.', ''), '0'), '.');
    }

    private function mimeMatchesMediaKind(string $mimeType, string $mediaKind, array $mimePrefixes): bool
    {
        if ($mimeType === '') {
            return false;
        }

        if ($mimePrefixes !== []) {
            foreach ($mimePrefixes as $prefix) {
                if (Str::startsWith($mimeType, (string) $prefix)) {
                    return true;
                }
            }

            return false;
        }

        return !Str::startsWith($mimeType, 'image/')
            && !Str::startsWith($mimeType, 'video/')
            && !Str::startsWith($mimeType, 'audio/');
    }
}
