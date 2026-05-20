<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreCompanyEmployee;
use App\Http\Requests\UpdateCompanyEmployee;
use App\Services\CompanyWorkforceService;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Illuminate\Http\RedirectResponse;

class CompanyTeamController extends BaseController
{
    public function __construct(
        private readonly CompanyWorkforceService $companyWorkforceService,
    ) {
    }

    public function index()
    {
        $payload = $this->companyWorkforceService->indexPayload(request());
        $payload['modules'] = \App\Models\Module::all();

        return Inertia::render('User/Team/CompanyIndex', $payload);
    }

    public function invite(StoreCompanyEmployee $request): RedirectResponse
    {
        try {
            $employee = $this->companyWorkforceService->invite($request);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('status', [
                'type' => 'error',
                'message' => $exception->validator->errors()->first() ?: __('Unable to invite this employee right now.'),
            ]);
        }

        return back()->with('status', [
            'type' => 'success',
            'message' => $employee->status === 'pending'
                ? __('Employee invitation sent successfully!')
                : __('Employee assignments saved successfully!'),
        ]);
    }

    public function update(UpdateCompanyEmployee $request, string $uuid): RedirectResponse
    {
        try {
            $this->companyWorkforceService->updateEmployee($request, $uuid);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('status', [
                'type' => 'error',
                'message' => $exception->validator->errors()->first() ?: __('Unable to update this employee right now.'),
            ]);
        }

        return back()->with('status', [
            'type' => 'success',
            'message' => __('Employee updated successfully!'),
        ]);
    }

    public function resendInvite(string $uuid): RedirectResponse
    {
        try {
            $this->companyWorkforceService->resendInvite($uuid);
        } catch (ValidationException $exception) {
            return back()->with('status', [
                'type' => 'error',
                'message' => $exception->validator->errors()->first() ?: __('Unable to resend the invitation right now.'),
            ]);
        }

        return back()->with('status', [
            'type' => 'success',
            'message' => __('Invitation resent successfully!'),
        ]);
    }

    public function suspend(string $uuid): RedirectResponse
    {
        try {
            $this->companyWorkforceService->suspendEmployee($uuid);
        } catch (ValidationException $exception) {
            return back()->with('status', [
                'type' => 'error',
                'message' => $exception->validator->errors()->first() ?: __('Unable to suspend this employee right now.'),
            ]);
        }

        return back()->with('status', [
            'type' => 'success',
            'message' => __('Employee suspended successfully!'),
        ]);
    }

    public function restore(string $uuid): RedirectResponse
    {
        try {
            $employee = $this->companyWorkforceService->restoreEmployee($uuid);
        } catch (ValidationException $exception) {
            return back()->with('status', [
                'type' => 'error',
                'message' => $exception->validator->errors()->first() ?: __('Unable to restore this employee right now.'),
            ]);
        }

        return back()->with('status', [
            'type' => 'success',
            'message' => $employee->status === 'active'
                ? __('Employee access restored successfully!')
                : __('Employee invite restored successfully!'),
        ]);
    }

    public function destroy(string $uuid): RedirectResponse
    {
        try {
            $this->companyWorkforceService->deleteEmployee($uuid);
        } catch (ValidationException $exception) {
            return back()->with('status', [
                'type' => 'error',
                'message' => $exception->validator->errors()->first() ?: __('Unable to delete this employee right now.'),
            ]);
        }

        return back()->with('status', [
            'type' => 'success',
            'message' => __('Employee removed from the company successfully!'),
        ]);
    }
}
