<?php

namespace App\Http\Controllers\User;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreContactField;
use App\Services\ContactFieldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule; 
use Inertia\Inertia;

class ContactFieldController extends BaseController
{
    private function getCurrentOrganizationId(): ?int
    {
        return session()->get('current_organization');
    }

    private function contactFieldService()
    {
        return new ContactFieldService($this->getCurrentOrganizationId());
    }

    public function index(Request $request, $id = null){
        $this->checkPermission('settings.manage', $this->getCurrentOrganizationId());
    }

    public function store(StoreContactField $request)
    {
        $this->checkPermission('settings.manage', $this->getCurrentOrganizationId());

        $this->contactFieldService()->store($request);

        return redirect('/settings/contacts')->with(
            'status', [
                'type' => 'success', 
                'message' => __('Contact field added successfully')
            ]
        );
    }

    public function show($uuid)
    {
        $this->checkPermission('settings.manage', $this->getCurrentOrganizationId());

        $contactFieldService = new ContactFieldService($this->getCurrentOrganizationId());
        $row = $contactFieldService->getByUuid($uuid);

        return response()->json(['success' => true, 'item'=> $row]);
    }

    public function update(StoreContactField $request, $id)
    {
        $this->checkPermission('settings.manage', $this->getCurrentOrganizationId());

        $this->contactFieldService()->store($request, $id);

        return redirect('/settings/contacts')->with(
            'status', [
                'type' => 'success', 
                'message' => __('Contact field updated successfully')
            ]
        );
    }

    public function destroy($id)
    {
        $this->checkPermission('settings.manage', $this->getCurrentOrganizationId());

        $this->contactFieldService()->delete($id);

        return redirect('/settings/contacts')->with(
            'status', [
                'type' => 'success', 
                'message' => __('Contact field deleted successfully')
            ]
        );
    }

    public function updatePositions(Request $request)
    {
        $this->checkPermission('settings.manage', $this->getCurrentOrganizationId());

        $request->validate([
            'orderedIds' => 'required|array',
            'orderedIds.*' => 'required|string|exists:contact_fields,uuid'
        ]);

        $this->contactFieldService()->updatePositions($request->orderedIds);

        return response()->json([
            'success' => true,
            'message' => __('Contact field order updated successfully')
        ]);
    }
}
