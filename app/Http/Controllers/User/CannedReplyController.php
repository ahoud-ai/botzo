<?php

namespace App\Http\Controllers\User;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Helpers\CustomHelper;
use App\Http\Requests\StoreAutoReply;
use App\Models\Addon;
use App\Models\AutoReply;
use App\Models\Setting;
use App\Services\AutoReplyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Helper;

class CannedReplyController extends BaseController
{
    private $autoReplyService;

    public function __construct(AutoReplyService $autoReplyService)
    {
        $this->autoReplyService = $autoReplyService;
    }

    public function index(Request $request){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('automations.view_all', $organizationId);
        
        $rows = $this->autoReplyService->getRows($request);
        $aimodule = CustomHelper::isModuleEnabled('AI Assistant');

        return Inertia::render('User/Automation/Basic/Index', [ 
            'title' => __('Canned replies'), 
            'allowCreate' => true, 
            'rows' => $rows, 
            'filters' => request()->all(), 
            'aimodule' => $aimodule,
        ]);
    }

    public function create(){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('automations.add', $organizationId);
        
        $data['title'] = __('Canned replies');
        $placeholders = config('formats.placeholders');
        $organizationId = session()->get('current_organization');
        $additionalFields = DB::table('contact_fields')
            ->where('organization_id', $organizationId)
            ->where('deleted_at', null)
            ->pluck('name');

        $additionalPlaceholders = $additionalFields->map(function($name) {
            // Convert name to lowercase and replace spaces with underscores
            $value = '{' . strtolower(str_replace(' ', '_', $name)) . '}';
            return [
                'value' => $value,
                'label' => $name,
            ];
        })->toArray();

        // Add URL-encoded versions of custom contact fields
        $additionalUrlPlaceholders = $additionalFields->map(function($name) {
            $urlValue = '{url:' . strtolower(str_replace(' ', '_', $name)) . '}';
            return [
                'value' => $urlValue,
                'label' => $name . ' (URL encoded)',
            ];
        })->toArray();

        $data['placeholders'] = array_merge($placeholders, $additionalPlaceholders, $additionalUrlPlaceholders);

        return Inertia::render('User/Automation/Basic/Create', $data);
    }

    public function store(StoreAutoReply $request){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('automations.add', $organizationId);
        
        $this->autoReplyService->store($request);

        return Redirect::route('cannedReply.create')->with(
            'status', [
                'type' => 'success', 
                'message' => __('Data added successfully!')
            ]
        );
    }

    public function edit($uuid){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('automations.view_all', $organizationId);
        
        $data['title'] = __('Canned replies');
        $data['autoreply'] = AutoReply::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->firstOrFail();
        $placeholders = config('formats.placeholders');
        $organizationId = session()->get('current_organization');
        $additionalFields = DB::table('contact_fields')
            ->where('organization_id', $organizationId)
            ->where('deleted_at', null)
            ->pluck('name');

        $additionalPlaceholders = $additionalFields->map(function($name) {
            // Convert name to lowercase and replace spaces with underscores
            $value = '{' . strtolower(str_replace(' ', '_', $name)) . '}';
            return [
                'value' => $value,
                'label' => $name,
            ];
        })->toArray();

        $data['placeholders'] = array_merge($placeholders, $additionalPlaceholders);

        return Inertia::render('User/Automation/Basic/Edit', $data);
    }

    public function update(StoreAutoReply $request, $uuid){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('automations.edit', $organizationId);
        
        $this->autoReplyService->store($request, $uuid);

        return Redirect::route('cannedReply.edit', $uuid)->with(
            'status', [
                'type' => 'success', 
                'message' => __('Data updated successfully!')
            ]
        );
    }

    public function delete($uuid)
    {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('automations.delete', $organizationId);
        
        $this->autoReplyService->destroy($uuid);

        return Redirect::back()->with(
            'status', [
                'type' => 'success', 
                'message' => __('Row deleted successfully!')
            ]
        );
    }
}
