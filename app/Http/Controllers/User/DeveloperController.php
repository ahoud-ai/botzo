<?php

namespace App\Http\Controllers\User;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\DeveloperResource;
use App\Models\OrganizationApiKey;
use App\Models\Setting;
use App\Services\OrganizationApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class DeveloperController extends BaseController
{
    private $organizationApiService;

    public function __construct(OrganizationApiService $organizationApiService)
    {
        $this->organizationApiService = $organizationApiService;
    }

    public function index(){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('developer_tools.view', $organizationId);
        
        $rows = OrganizationApiKey::where('organization_id', $organizationId)
            ->where('deleted_at', NULL)
            ->paginate(9);
        $data['rows'] = DeveloperResource::collection($rows);
        $data['title'] = __('API keys');
        $data['url'] = url('/');
        $data['apirequests'] = config('apiguide');

        return Inertia::render('User/Developer/Index', $data);
    }

    public function store(Request $request){
        $organizationId = session()->get('current_organization');
        $this->checkPermission('developer_tools.add', $organizationId);
        
        $token = $this->organizationApiService->generate((int) $organizationId);

        return Redirect::back()->with(
            [
                'status' => [
                    'type' => 'success',
                    'message' => __('Your API token has been generated successfully. Save it now because it will not be shown again.'),
                ],
                'generated_api_token' => $token['token'],
                'generated_api_token_action' => 'created',
            ]
        );
    }

    public function rotate($uuid)
    {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('developer_tools.edit', $organizationId);

        $token = $this->organizationApiService->rotate($uuid, (int) $organizationId);

        return Redirect::back()->with([
            'status' => [
                'type' => 'success',
                'message' => __('Your API token has been rotated successfully. Save the new token now because it will not be shown again.'),
            ],
            'generated_api_token' => $token['token'],
            'generated_api_token_action' => 'rotated',
        ]);
    }

    public function delete($uuid)
    {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('developer_tools.delete', $organizationId);
        
        $this->organizationApiService->destroy($uuid);

        return Redirect::back()->with('status', [
            'type' => 'success',
            'message' => __('Your API token has been deleted successfully.'),
        ]);
    }
}
