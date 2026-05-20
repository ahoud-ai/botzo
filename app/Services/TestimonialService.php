<?php

namespace App\Services;

use App\Http\Resources\ReviewResource;
use App\Models\Review;

class TestimonialService
{
    /**
     * Get all Reviews based on the provided request filters.
     *
     * @param Request $request
     * @return mixed
     */
    public function get(object $request)
    {
        $rows = (new Review)->listAll($request->query('search'));

        return ReviewResource::collection($rows);
    }

    /**
     * Store Review
     *
     * @param Request $request
     * @param string $id
     * @return \App\Models\Review
     */
    public function store(object $request, $id = NULL)
    {
        $review = $id === null ? new Review() : Review::where('id', $id)->firstOrFail();
        $name = $this->normalizeText($request->input('name'));
        $nameAr = $this->normalizeText($request->input('name_ar'));
        $nameEn = $this->normalizeText($request->input('name_en'));

        $position = $this->normalizeText($request->input('position'));
        $positionAr = $this->normalizeText($request->input('position_ar'));
        $positionEn = $this->normalizeText($request->input('position_en'));

        $reviewText = $this->normalizeText($request->input('review'));
        $reviewTextAr = $this->normalizeText($request->input('review_ar'));
        $reviewTextEn = $this->normalizeText($request->input('review_en'));

        $review->name_ar = $nameAr;
        $review->name_en = $nameEn;
        $review->position_ar = $positionAr;
        $review->position_en = $positionEn;
        $review->review_ar = $reviewTextAr;
        $review->review_en = $reviewTextEn;

        // Keep previous single-language columns populated for full backward compatibility.
        $review->name = $this->firstFilled($name, $nameEn, $nameAr);
        $review->position = $this->firstFilled($position, $positionEn, $positionAr);
        $review->review = $this->firstFilled($reviewText, $reviewTextEn, $reviewTextAr);

        $review->rating = (int) $request->input('rating');
        $review->status = (int) $request->input('status');

        if ($request->hasFile('image')) {
            $review->image = $request->file('image')->store('reviews', 'public');
        }

        $review->updated_at = now();
        $review->save();

        return $review;
    }

    /**
     * Delete Review
     *
     * @param Request $request
     * @param string $id
     * @return \App\Models\Review
     */
    public function delete($id)
    {
        return Review::where('id', $id)->delete();
    }

    private function normalizeText($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : clean($value);
    }

    private function firstFilled(...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
