<?php

namespace Modules\IntelliReply\Controllers;

use App\Helpers\CustomHelper;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Organization;
use App\Services\IntelliReply\AiKeyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Modules\IntelliReply\Models\Document;
use Modules\IntelliReply\Requests\StoreDocuments;
use OpenAI;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class DocumentController extends BaseController
{
    private const DEFAULT_EMBEDDING_MODEL = 'text-embedding-3-small';
    private const MAX_CHUNK_LENGTH = 1600;

    public function store(StoreDocuments $request): RedirectResponse
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission('automations.add', $organizationId);
        $this->ensureAiAssistantEnabled($organizationId);

        $organization = Organization::findOrFail($organizationId);
        $metadata = $organization->metadata ? json_decode($organization->metadata, true) : [];
        $metadata = is_array($metadata) ? $metadata : [];
        $keyResolver = app(AiKeyResolver::class);
        $keyBundle = $keyResolver->resolveForOrganization(
            $metadata,
            data_get($metadata, 'ai.key_source', 'auto'),
            $organizationId
        );
        $apiKey = $keyBundle['key'] ?? null;

        if (empty($apiKey)) {
            Log::warning('intellireply.document.upload.denied', [
                'organization_id' => $organizationId,
                'error_code' => 'AI_KEY_MISSING',
                'key_source' => $keyBundle['source'] ?? null,
            ]);

            return Redirect::back()->with('status', [
                'type' => 'error',
                'message' => __('OpenAI API key is required before uploading documents.'),
            ]);
        }

        $file = $request->file('file');
        $extension = strtolower((string) $file->getClientOriginalExtension());

        try {
            $content = $this->extractContentByExtension($extension, $file->getPathname());
            $normalizedContent = str_replace(["\r\n", "\r"], "\n", (string) $content);
            $normalizedContent = trim((string) preg_replace('/[ \t]+/u', ' ', $normalizedContent));

            if ($normalizedContent === '') {
                return Redirect::back()->with('status', [
                    'type' => 'error',
                    'message' => __('Uploaded document is empty or unreadable.'),
                ]);
            }

            DB::transaction(function () use ($organizationId, $request, $normalizedContent, $apiKey, $metadata): void {
                $client = OpenAI::client($apiKey);

                $document = new Document();
                $document->organization_id = $organizationId;
                $document->source = 'File';
                $document->title = (string) $request->input('title');
                $document->content = $normalizedContent;
                $document->status = 'Pending';
                $document->save();

                $chunks = $this->splitDocument($normalizedContent);
                $embeddings = [];

                foreach ($chunks as $chunk) {
                    if (trim($chunk) === '') {
                        continue;
                    }

                    [$embedding] = $this->createEmbeddingWithFallback($client, $chunk, $metadata);
                    $embeddings[] = $embedding;
                }

                $document->embeddings = json_encode($embeddings);
                $document->status = 'Complete';
                $document->save();
            });

            Log::info('intellireply.document.uploaded', [
                'organization_id' => $organizationId,
                'title' => (string) $request->input('title'),
                'key_source' => $keyBundle['source'] ?? null,
                'user_id' => auth()->id(),
            ]);

            return Redirect::back()->with('status', [
                'type' => 'success',
                'message' => __('Document uploaded successfully!'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('IntelliReply document upload failed', [
                'organization_id' => $organizationId,
                'error_code' => $this->isEmbeddingModelAccessError($e)
                    ? 'EMBEDDING_MODEL_UNAVAILABLE'
                    : 'DOCUMENT_UPLOAD_FAILED',
                'error' => $e->getMessage(),
            ]);

            return Redirect::back()->with('status', [
                'type' => 'error',
                'message' => $this->isEmbeddingModelAccessError($e)
                    ? __('Selected embedding model is not available for this OpenAI key. Choose another embedding model or use a key with access.')
                    : __('Unable to process this document right now. Please try again.'),
            ]);
        }
    }

    public function delete(string $uuid): RedirectResponse
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission('automations.delete', $organizationId);
        $this->ensureAiAssistantEnabled($organizationId);

        Document::where('uuid', $uuid)
            ->where('organization_id', $organizationId)
            ->delete();

        Log::info('intellireply.document.deleted', [
            'organization_id' => $organizationId,
            'document_uuid' => $uuid,
            'user_id' => auth()->id(),
        ]);

        return Redirect::back()->with('status', [
            'type' => 'success',
            'message' => __('Row deleted successfully!'),
        ]);
    }

    private function ensureAiAssistantEnabled(int $organizationId): void
    {
        if (!CustomHelper::isModuleEnabled('AI Assistant', $organizationId)) {
            abort(404);
        }
    }

    private function extractContentByExtension(string $extension, string $path): string
    {
        if ($extension === 'txt') {
            return (string) file_get_contents($path);
        }

        if ($extension === 'pdf') {
            $parser = new PdfParser();
            return (string) $parser->parseFile($path)->getText();
        }

        if (in_array($extension, ['doc', 'docx'], true)) {
            $phpWord = WordIOFactory::load($path);
            return $this->extractTextFromWord($phpWord);
        }

        throw new \RuntimeException(__('Unsupported file type'));
    }

    private function extractTextFromWord($phpWord): string
    {
        $content = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                    foreach ($element->getElements() as $textElement) {
                        if (method_exists($textElement, 'getText')) {
                            $content .= $textElement->getText().' ';
                        }
                    }
                } elseif ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                    $content .= $element->getText().' ';
                } elseif ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                    foreach ($element->getRows() as $row) {
                        foreach ($row->getCells() as $cell) {
                            foreach ($cell->getElements() as $cellElement) {
                                if (method_exists($cellElement, 'getText')) {
                                    $content .= $cellElement->getText().' ';
                                }
                            }
                        }
                    }
                }
            }
        }

        return $content;
    }

    private function splitDocument(string $content): array
    {
        $paragraphs = preg_split("/(\r\n|\n|\r){2,}/", $content) ?: [];
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $segment = trim((string) $paragraph);
            if ($segment === '') {
                continue;
            }

            if (mb_strlen($current.' '.$segment) <= self::MAX_CHUNK_LENGTH) {
                $current = trim($current.' '.$segment);
                continue;
            }

            if ($current !== '') {
                $chunks[] = $current;
            }

            if (mb_strlen($segment) <= self::MAX_CHUNK_LENGTH) {
                $current = $segment;
                continue;
            }

            foreach (mb_str_split($segment, self::MAX_CHUNK_LENGTH) as $piece) {
                $piece = trim($piece);
                if ($piece !== '') {
                    $chunks[] = $piece;
                }
            }
            $current = '';
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        if (empty($chunks)) {
            $chunks[] = mb_substr($content, 0, self::MAX_CHUNK_LENGTH);
        }

        return $chunks;
    }

    private function resolveEmbeddingModel(array $metadata): string
    {
        $model = data_get($metadata, 'ai.embedding_model');
        if (is_string($model) && trim($model) !== '') {
            return $model;
        }

        $default = config('intellireply.default_embedding_model', self::DEFAULT_EMBEDDING_MODEL);
        return is_string($default) && trim($default) !== ''
            ? $default
            : self::DEFAULT_EMBEDDING_MODEL;
    }

    private function embeddingModelCandidates(array $metadata): array
    {
        $preferred = $this->resolveEmbeddingModel($metadata);
        $allowlist = config('intellireply.embedding_models', [self::DEFAULT_EMBEDDING_MODEL]);
        if (!is_array($allowlist)) {
            $allowlist = [self::DEFAULT_EMBEDDING_MODEL];
        }

        $fallbackMap = config('intellireply.embedding_model_fallbacks', []);
        if (!is_array($fallbackMap)) {
            $fallbackMap = [];
        }

        $candidates = [$preferred];
        $preferredFallbacks = $fallbackMap[$preferred] ?? [];
        if (is_array($preferredFallbacks)) {
            foreach ($preferredFallbacks as $fallbackModel) {
                if (is_string($fallbackModel) && trim($fallbackModel) !== '') {
                    $candidates[] = trim($fallbackModel);
                }
            }
        }

        foreach ($allowlist as $allowedModel) {
            if (is_string($allowedModel) && trim($allowedModel) !== '') {
                $candidates[] = trim($allowedModel);
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return array{0:array,1:string}
     */
    private function createEmbeddingWithFallback($client, string $chunk, array $metadata): array
    {
        $lastError = null;

        foreach ($this->embeddingModelCandidates($metadata) as $model) {
            try {
                $response = $client->embeddings()->create([
                    'input' => $chunk,
                    'model' => $model,
                ]);

                return [$response->embeddings[0]->embedding, $model];
            } catch (\Throwable $e) {
                $lastError = $e;
                continue;
            }
        }

        throw $lastError ?? new \RuntimeException('Unable to generate embeddings');
    }

    private function isEmbeddingModelAccessError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'does not have access to model')
            || str_contains($message, 'model_not_found')
            || str_contains($message, 'invalid model');
    }
}
