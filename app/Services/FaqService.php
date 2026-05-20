<?php

namespace App\Services;

use App\Http\Resources\FaqResource;
use App\Models\Faq;

class FaqService
{
    private function normalizeText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Get all FAQs based on the provided request filters.
     *
     * @param Request $request
     * @return mixed
     */
    public function get(object $request)
    {
        $rows = (new Faq)->listAll($request->query('search'));

        return FaqResource::collection($rows);
    }

    public function getByUuid($uuid = null)
    {
        return Faq::where('id', $uuid)->first();
    }

    /**
     * Store FAQ
     *
     * @param Request $request
     * @param string $id
     * @return \App\Models\Faq
     */
    public function store(object $request, $id = NULL)
    {
        $faq = $id === null ? new Faq() : Faq::where('id', $id)->firstOrFail();

        $questionAr = $this->normalizeText($request->input('question_ar'));
        $questionEn = $this->normalizeText($request->input('question_en'));
        $previousQuestion = $this->normalizeText($request->input('question'));

        $answerAr = $this->normalizeText($request->input('answer_ar'));
        $answerEn = $this->normalizeText($request->input('answer_en'));
        $previousAnswer = $this->normalizeText($request->input('answer'));

        $faq->question_ar = clean($questionAr ?? $previousQuestion);
        $faq->question_en = clean($questionEn ?? $previousQuestion);
        $faq->answer_ar = clean($answerAr ?? $previousAnswer);
        $faq->answer_en = clean($answerEn ?? $previousAnswer);

        $faq->question = clean($previousQuestion ?? $questionAr ?? $questionEn);
        $faq->answer = clean($previousAnswer ?? $answerAr ?? $answerEn);
        $faq->status = $request->status;
        $faq->created_at = now();
        $faq->updated_at = now();
        $faq->save();

        return $faq;
    }

    /**
     * Delete FAQ
     *
     * @param Request $request
     * @param string $id
     * @return \App\Models\Faq
     */
    public function delete($id)
    {
        return Faq::where('id', $id)->delete();
    } 
}
