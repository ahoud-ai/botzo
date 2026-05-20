<?php

namespace App\Services;

use App\Http\Resources\PageResource;
use App\Models\Page;

class PageService
{
    private function normalizeText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function sanitizeHtml(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return clean($value, 'email_template');
    }

    private function sanitizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return clean($value);
    }

    private function firstFilled(...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Get all rows based on the provided request filters.
     *
     * @param Request $request
     * @return mixed
     */
    public function getAll(object $request)
    {
        $emails = (new Page)->listAll($request->query('search'));

        return PageResource::collection($emails);
    }

    /**
     * Retrieve row by its ID.
     *
     * @param string $id
     * @return \App\Models\Page
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getPageByID($request, $id)
    {
        $page = Page::where('id', $id)->first();

        return $page;
    }

    /**
     * Create page.
     *
     * @param Request $request
     * @return \App\Models\Page
     */
    public function create($request)
    {
        $nameAr = $this->normalizeText($request->input('name_ar'));
        $nameEn = $this->normalizeText($request->input('name_en'));
        $previousName = $this->normalizeText($request->input('name'));

        $contentAr = $this->normalizeText($request->input('content_ar'));
        $contentEn = $this->normalizeText($request->input('content_en'));
        $previousContent = $this->normalizeText($request->input('content'));

        $resolvedName = $this->firstFilled($nameEn, $nameAr, $previousName);
        $resolvedContent = $this->firstFilled($contentEn, $contentAr, $previousContent);

        $page = Page::create([
            'name' => $this->sanitizeText($resolvedName),
            'name_ar' => $this->sanitizeText($nameAr),
            'name_en' => $this->sanitizeText($nameEn),
            'content' => $this->sanitizeHtml($resolvedContent),
            'content_ar' => $this->sanitizeHtml($contentAr),
            'content_en' => $this->sanitizeHtml($contentEn),
        ]);

        return $page;
    }

    /**
     * Update page.
     *
     * @param Request $request
     * @param number $id
     * @return \App\Models\Page
     */
    public function update($request, $id)
    {
        $page = Page::where('id', $id)->firstOrFail();

        $nameAr = $this->normalizeText($request->input('name_ar'));
        $nameEn = $this->normalizeText($request->input('name_en'));
        $previousName = $this->normalizeText($request->input('name'));

        $contentAr = $this->normalizeText($request->input('content_ar'));
        $contentEn = $this->normalizeText($request->input('content_en'));
        $previousContent = $this->normalizeText($request->input('content'));

        $resolvedName = $this->firstFilled($nameEn, $nameAr, $previousName);
        $resolvedContent = $this->firstFilled($contentEn, $contentAr, $previousContent);

        $page->update([
            'name' => $this->sanitizeText($resolvedName),
            'name_ar' => $this->sanitizeText($nameAr),
            'name_en' => $this->sanitizeText($nameEn),
            'content' => $this->sanitizeHtml($resolvedContent),
            'content_ar' => $this->sanitizeHtml($contentAr),
            'content_en' => $this->sanitizeHtml($contentEn),
        ]);

        return $page;
    }

    public function delete($id)
    {
        $page = Page::where('id', $id)->delete();

        return $page;
    }
}
