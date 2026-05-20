<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreTestimonial;
use App\Models\Review;
use App\Services\TestimonialService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TestimonialController extends BaseController
{
    public function __construct(TestimonialService $testimonialService)
    {
        $this->testimonialService = $testimonialService;
    }

    public function index(Request $request){
        return Inertia::render('Admin/Testimonial/Index', [
            'title' => __('Reviews'),
            'rows' => $this->testimonialService->get($request), 
            'filters' => $request->all()
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Testimonial/Show', [
            'title' => __('Reviews'),
            'testimonial' => null,
        ]);
    }

    public function store(StoreTestimonial $request)
    {
        $this->testimonialService->store($request);

        return redirect('/admin/testimonials')->with(
            'status', [
                'type' => 'success', 
                'message' => __('Review added successfully!')
            ]
        );
    }

    public function show($id)
    {
        $query = Review::where('id', $id)->first();

        return Inertia::render('Admin/Testimonial/Show', [
            'title' => __('Reviews'),
            'testimonial' => $query,
        ]);
    }

    public function edit($id)
    {
        $query = Review::where('id', $id)->first();

        return Inertia::render('Admin/Testimonial/Show', [
            'title' => __('Reviews'),
            'testimonial' => $query,
        ]);
    }

    public function update(StoreTestimonial $request, $id)
    {
        $this->testimonialService->store($request, $id);

        return redirect('/admin/testimonials')->with(
            'status', [
                'type' => 'success', 
                'message' => __('Review updated successfully!')
            ]
        );
    }

    public function destroy($id)
    {
        $this->testimonialService->delete($id);

        return redirect('/admin/testimonials')->with(
            'status', [
                'type' => 'success', 
                'message' => __('Review deleted successfully!')
            ]
        );
    }
}
