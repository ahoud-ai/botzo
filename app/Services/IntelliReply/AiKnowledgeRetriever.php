<?php

namespace App\Services\IntelliReply;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\IntelliReply\Models\Document;
use Modules\IntelliReply\Models\Faq;
use Throwable;

/**
 * Builds the knowledge the assistant answers from for one customer question:
 * the organization's manual FAQ plus the most relevant document chunks.
 *
 * - FAQs that fit in a small budget are always included (no embedding needed);
 *   larger FAQ sets are ranked by similarity to the question.
 * - Document chunks must pass a minimum similarity, the best few are kept within
 *   a character budget. Nothing relevant means no excerpts, so the assistant
 *   answers from the persona or hands the chat to the team.
 */
class AiKnowledgeRetriever
{
    public function __construct(private readonly AiEmbeddingService $embeddings)
    {
    }

    public function retrieve(int $organizationId, array $organizationMetadata, ?string $apiKey, string $query): ?string
    {
        $faqs = Faq::where('organization_id', $organizationId)->orderBy('id')->get();
        $documents = Document::where('organization_id', $organizationId)
            ->where('status', 'Complete')
            ->whereNotNull('embeddings')
            ->get(['id', 'title', 'content', 'embeddings']);

        if ($faqs->isEmpty() && $documents->isEmpty()) {
            return null;
        }

        $faqsFitInline = $faqs->sum(fn (Faq $faq) => mb_strlen($faq->question) + mb_strlen($faq->answer))
            <= (int) config('intellireply.retrieval.faq_inline_chars', 4000);
        $queryVector = null;

        if (($documents->isNotEmpty() || ! $faqsFitInline) && $apiKey && trim($query) !== '') {
            try {
                [$queryVector] = $this->embeddings->embed($organizationMetadata, $apiKey, $query);
            } catch (Throwable $exception) {
                Log::channel('intellireply')->warning('intellireply.retrieval.failed', [
                    'organization_id' => $organizationId,
                    'error_code' => $this->embeddings->isModelAccessError($exception) ? 'EMBEDDING_MODEL_UNAVAILABLE' : 'RETRIEVAL_FAILED',
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $selectedFaqs = $faqsFitInline ? $faqs : $this->rankFaqs($faqs, $queryVector, $organizationMetadata, $apiKey);
        $chunks = $queryVector !== null ? $this->rankChunks($documents, $queryVector) : [];

        Log::channel('intellireply')->info('intellireply.retrieval.completed', [
            'organization_id' => $organizationId,
            'faq_count' => $selectedFaqs->count(),
            'chunk_count' => count($chunks),
            'top_score' => isset($chunks[0]) ? round($chunks[0]['score'], 3) : null,
        ]);

        return $this->format($selectedFaqs, $chunks);
    }

    /**
     * Compute and store the embedding of an FAQ question. Best effort: a failure leaves it empty.
     */
    public function embedFaq(Faq $faq, array $organizationMetadata, ?string $apiKey): void
    {
        if (! $apiKey) {
            return;
        }

        try {
            [$vector, $model] = $this->embeddings->embed($organizationMetadata, $apiKey, $faq->question);
            $faq->forceFill(['embedding' => $vector, 'embedding_model' => $model])->save();
        } catch (Throwable $exception) {
            Log::channel('intellireply')->warning('intellireply.faq.embedding_failed', [
                'organization_id' => $faq->organization_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  Collection<int, Faq>  $faqs
     * @return Collection<int, Faq>
     */
    private function rankFaqs(Collection $faqs, ?array $queryVector, array $organizationMetadata, ?string $apiKey): Collection
    {
        $topK = max(1, (int) config('intellireply.retrieval.faq_top_k', 5));

        if ($queryVector === null) {
            return $faqs->take($topK)->values();
        }

        $backfillBudget = 5;
        $minScore = (float) config('intellireply.retrieval.faq_min_similarity', 0.35);

        return $faqs
            ->map(function (Faq $faq) use ($queryVector, $organizationMetadata, $apiKey, &$backfillBudget) {
                if (! is_array($faq->embedding) && $backfillBudget-- > 0) {
                    $this->embedFaq($faq, $organizationMetadata, $apiKey);
                }

                return [
                    'faq' => $faq,
                    'score' => is_array($faq->embedding) ? $this->embeddings->similarity($queryVector, $faq->embedding) : -1.0,
                ];
            })
            ->filter(fn (array $row) => $row['score'] >= $minScore)
            ->sortByDesc('score')
            ->take($topK)
            ->pluck('faq')
            ->values();
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return list<array{score:float,title:string,text:string}>
     */
    private function rankChunks(Collection $documents, array $queryVector): array
    {
        $minScore = (float) config('intellireply.retrieval.min_similarity', 0.25);
        $candidates = [];

        foreach ($documents as $document) {
            $vectors = json_decode((string) $document->embeddings, true);
            if (! is_array($vectors) || $vectors === []) {
                continue;
            }

            $texts = $this->embeddings->chunk((string) $document->content);

            foreach ($vectors as $index => $vector) {
                if (! is_array($vector) || ! isset($texts[$index])) {
                    continue;
                }

                $score = $this->embeddings->similarity($queryVector, $vector);
                if ($score >= $minScore) {
                    $candidates[] = ['score' => $score, 'title' => trim((string) $document->title), 'text' => $texts[$index]];
                }
            }
        }

        usort($candidates, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $selected = [];
        $budget = (int) config('intellireply.retrieval.max_context_chars', 6000);

        foreach (array_slice($candidates, 0, max(1, (int) config('intellireply.retrieval.top_k', 4))) as $candidate) {
            $length = mb_strlen($candidate['text']);
            if ($selected !== [] && $length > $budget) {
                break;
            }

            $selected[] = $candidate;
            $budget -= $length;
        }

        return $selected;
    }

    private function format(Collection $faqs, array $chunks): ?string
    {
        $sections = [];

        if ($faqs->isNotEmpty()) {
            $sections[] = "Frequently asked questions (use these answers when the customer's question matches):\n"
                .$faqs->map(fn (Faq $faq) => 'Q: '.$faq->question."\nA: ".$faq->answer)->implode("\n\n");
        }

        if ($chunks !== []) {
            $sections[] = "Knowledge base excerpts:\n"
                .collect($chunks)->map(fn (array $chunk) => ($chunk['title'] !== '' ? '['.$chunk['title']."]\n" : '').$chunk['text'])->implode("\n---\n");
        }

        return $sections === [] ? null : implode("\n\n", $sections);
    }
}
