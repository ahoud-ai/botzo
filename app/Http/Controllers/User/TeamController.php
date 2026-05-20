<?php

namespace App\Http\Controllers\User;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreTeam;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Services\PermissionService;
use App\Services\TeamService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TeamController extends BaseController
{
    private $teamService;

    public function __construct(TeamService $teamService)
    {
        $this->teamService = $teamService;
    }

    public function index(Request $request){
        $this->authorizeOwnerAccess(__('Only owners can manage team members'));

        $rows = TeamResource::collection(
            Team::with(['user', 'organizationRole'])
                ->where('organization_id', session()->get('current_organization'))
                ->latest()
                ->paginate(10)
        );

        if($request->expectsJson()){
            $rows = DB::table('users')
                ->join('teams', 'users.id', '=', 'teams.user_id')
                ->where('teams.organization_id', '=', session()->get('current_organization'))
                ->whereNull('teams.deleted_at')
                ->select('users.*')
                ->get();

            return response()->json([
                'rows' => $rows
            ]);
        } else {
            $modules = \App\Models\Module::all();
            
            return Inertia::render('User/Team/Index', [
                'title' => __('Team'),
                'filters' => $request->all(),
                'rows' => $rows,
                'modules' => $modules
            ]);
        }
    }

    public function invite(StoreTeam $request){
        $this->authorizeOwnerAccess(__('Only owners can invite team members'));

        try {
            $this->teamService->invite($request);
        } catch (ValidationException $exception) {
            return Redirect::back()->withErrors($exception->errors())->with(
                'status',
                [
                    'type' => 'error',
                    'message' => $exception->validator->errors()->first() ?: __('Unable to invite this user right now.'),
                ]
            );
        }

        //response()->json(['success' => true, 'message'=> __('User invited successfully!'), 'data' => $invite])

        return Redirect::back()->with(
            'status', [
                'type' => 'success', 
                'message' => __('User invited successfully!')
            ]
        );
    }

    public function update(Request $request, $uuid){
        $this->authorizeOwnerAccess(__('Only owners can update team members'));

        $result = $this->teamService->update($request, $uuid);

        if ($result instanceof RedirectResponse) {
            return $result;
        }

        return Redirect::back()->with(
            'status', [
                'type' => 'success', 
                'message' => __('User account updated successfully!')
            ]
        );
    }

    public function delete($uuid)
    {
        $this->authorizeOwnerAccess(__('Only owners can delete team members'));

        return $this->teamService->destroy($uuid);
    }

    private function authorizeOwnerAccess(string $message): void
    {
        $permissionService = new PermissionService();

        if (! $permissionService->isOwner(session()->get('current_organization'))) {
            abort(403, $message);
        }
    }
}
