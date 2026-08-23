<?php

use App\Http\Controllers\User\OnboardingTourController;
use Illuminate\Support\Facades\Route;

Route::post('/onboarding-tour/finish', [OnboardingTourController::class, 'finish'])->name('onboarding-tour.finish');
