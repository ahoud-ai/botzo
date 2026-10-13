<?php

namespace Modules\IntelliReply\Controllers;

use App\Helpers\CustomHelper;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Organization;
use App\Services\IntelliReply\AiKeyResolver;
use App\Services\IntelliReply\AiKnowledgeRetriever;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\IntelliReply\Models\Faq;

class FaqController extends BaseController
{
    public function store(Request $request): RedirectResponse
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission('automations.add', $organizationId);
        $this->ensureAiAssistantEnabled($organizationId);

        $payload = $this->validated($request);

        if (Faq::where('organization_id', $organizationId)->count() >= Faq::MAX_PER_ORGANIZATION) {
            return back()->with('status', [
                'type' => 'error',
                'message' => __('You can add up to :count questions.', ['count' => Faq::MAX_PER_ORGANIZATION]),
            ]);
        }

        $faq = Faq::create(['organization_id' => $organizationId] + $payload);
        $this->embed($faq, $organizationId);

        Log::channel('intellireply')->info('intellireply.faq.created', ['organization_id' => $organizationId, 'user_id' => auth()->id()]);

        return back()->with('status', ['type' => 'success', 'message' => __('Question saved')]);
    }

    public function update(Request $request, string $uuid): RedirectResponse
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission('automations.edit', $organizationId);
        $this->ensureAiAssistantEnabled($organizationId);

        $faq = Faq::where('organization_id', $organizationId)->where('uuid', $uuid)->firstOrFail();
        $payload = $this->validated($request);
        $questionChanged = $faq->question !== $payload['question'];

        $faq->fill($payload);
        if ($questionChanged) {
            $faq->embedding = null;
            $faq->embedding_model = null;
        }
        $faq->save();

        if ($questionChanged) {
            $this->embed($faq, $organizationId);
        }

        return back()->with('status', ['type' => 'success', 'message' => __('Question saved')]);
    }

    public function delete(string $uuid): RedirectResponse
    {
        $organizationId = (int) session()->get('current_organization');
        $this->checkPermission('automations.delete', $organizationId);
        $this->ensureAiAssistantEnabled($organizationId);

        Faq::where('organization_id', $organizationId)->where('uuid', $uuid)->delete();

        return back()->with('status', ['type' => 'success', 'message' => __('Row deleted successfully!')]);
    }

    /**
     * @return array{question:string,answer:string}
     */
    private function validated(Request $request): array
    {
        $payload = $request->validate([
            'question' => ['required', 'string', 'max:'.Faq::MAX_QUESTION_LENGTH],
            'answer' => ['required', 'string', 'max:'.Faq::MAX_ANSWER_LENGTH],
        ]);

        return [
            'question' => trim((string) preg_replace('/\s+/u', ' ', $payload['question'])),
            'answer' => trim($payload['answer']),
        ];
    }

    /**
     * Embed the question for ranking large FAQ lists. Best effort: small lists do not need it,
     * and a missing embedding is filled in later when the FAQ is first ranked.
     */
    private function embed(Faq $faq, int $organizationId): void
    {
        $metadata = json_decode((string) Organization::where('id', $organizationId)->value('metadata'), true) ?: [];
        $keyBundle = app(AiKeyResolver::class)->resolveForOrganization($metadata, data_get($metadata, 'ai.key_source', 'auto'), $organizationId);

        app(AiKnowledgeRetriever::class)->embedFaq($faq, $metadata, $keyBundle['key'] ?? null);
    }

    private function ensureAiAssistantEnabled(int $organizationId): void
    {
        if (!CustomHelper::isModuleEnabled('AI Assistant', $organizationId)) {
            abort(404);
        }
    }
}
