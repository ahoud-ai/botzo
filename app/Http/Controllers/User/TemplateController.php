<?php

namespace App\Http\Controllers\User;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use App\Services\TemplateService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Validator;

class TemplateController extends BaseController
{
    private function templateService()
    {
        return new TemplateService(session()->get('current_organization'));
    }

    public function index(Request $request, $uuid = null)
    {
        $organizationId = session()->get('current_organization');
        
        // Check sync permission if syncing templates
        if ($uuid === 'sync') {
            $this->checkPermission('message_templates.sync', $organizationId);
        } else {
            $this->checkPermission('message_templates.view_all', $organizationId);
        }
        
        return $this->templateService()->getTemplates($request, $uuid, $request->query('search'));
    }

    public function create(Request $request)
    {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('message_templates.add', $organizationId);
        
        return $this->templateService()->createTemplate($request);
    }

    public function update(Request $request, $uuid)
    {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('message_templates.edit', $organizationId);
        
        return $this->templateService()->updateTemplate($request, $uuid);
    }

    public function delete($uuid)
    {
        $organizationId = session()->get('current_organization');
        $this->checkPermission('message_templates.delete', $organizationId);
        
        return $this->templateService()->deleteTemplate($uuid);
    }
}