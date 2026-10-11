<?php

namespace Modules\IntelliReply\Controllers;

use App\Helpers\CustomHelper;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Organization;
use App\Services\IntelliReply\AiKeyResolver;
use App\Services\IntelliReply\AiEmbeddingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Modules\IntelliReply\Models\Document;
use Modules\IntelliReply\Requests\StoreDocuments;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class DocumentController extends BaseController
{
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
                $embeddingService = app(AiEmbeddingService::class);

                $document = new Document();
                $document->organization_id = $organizationId;
                $document->source = 'File';
                $document->title = (string) $request->input('title');
                $document->content = $normalizedContent;
                $document->status = 'Pending';
                $document->save();

                $chunks = $embeddingService->chunk($normalizedContent);
                $embeddings = [];

                foreach ($chunks as $chunk) {
                    if (trim($chunk) === '') {
                        continue;
                    }

                    [$embedding] = $embeddingService->embed($metadata, $apiKey, $chunk);
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
                'error_code' => app(AiEmbeddingService::class)->isModelAccessError($e)
                    ? 'EMBEDDING_MODEL_UNAVAILABLE'
                    : 'DOCUMENT_UPLOAD_FAILED',
                'error' => $e->getMessage(),
            ]);

            return Redirect::back()->with('status', [
                'type' => 'error',
                'message' => app(AiEmbeddingService::class)->isModelAccessError($e)
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






}
