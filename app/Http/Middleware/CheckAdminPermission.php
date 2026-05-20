<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\RolePermission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminPermission
{
    private const COMPANION_PERMISSION_FALLBACKS = [
        'languages' => [
            ['module' => 'settings', 'action' => 'general'],
        ],
        'logs' => [
            ['module' => 'customers', 'action' => 'view'],
        ],
    ];

    public function handle(Request $request, Closure $next, string $module, ?string $action = null): Response
    {
        $user = Auth::guard('admin')->user();

        if (! $user) {
            abort(403, __('Unauthorized.'));
        }

        $normalizedRole = strtolower((string) $user->role);

        if (in_array($normalizedRole, ['admin', 'owner'], true)) {
            return $next($request);
        }

        $resolvedAction = $this->resolveAction($request, $module, $action);

        if ($resolvedAction === null) {
            abort(403, __('You do not have permission to perform this action.'));
        }

        $role = Role::query()
            ->whereRaw('LOWER(name) = ?', [$normalizedRole])
            ->whereNull('deleted_at')
            ->first();

        if (! $role) {
            abort(403, __('You do not have permission to perform this action.'));
        }

        $allowed = RolePermission::query()
            ->where('role_id', $role->id)
            ->where('module', $module)
            ->where('action', $resolvedAction)
            ->exists();

        if (! $allowed && ! $this->hasCompanionPermission((int) $role->id, $module)) {
            abort(403, __('You do not have permission to perform this action.'));
        }

        return $next($request);
    }

    private function resolveAction(Request $request, string $module, ?string $action): ?string
    {
        if ($module === 'settings' && $action === 'auto') {
            return $this->resolveSettingsActionFromType($request);
        }

        if (is_string($action) && $action !== '') {
            return $action;
        }

        $routeMethod = strtolower((string) optional($request->route())->getActionMethod());

        if (str_contains($routeMethod, 'destroy') || str_contains($routeMethod, 'delete')) {
            return 'delete';
        }

        if (str_contains($routeMethod, 'assign') || str_contains($routeMethod, 'status') || str_contains($routeMethod, 'priority')) {
            return 'assign';
        }

        if ($routeMethod === 'store' || $routeMethod === 'create' || $routeMethod === 'comment') {
            return 'create';
        }

        if ($routeMethod === 'update' || $routeMethod === 'edit') {
            return 'edit';
        }

        if ($routeMethod === 'index' || $routeMethod === 'show' || str_contains($routeMethod, 'preview') || str_contains($routeMethod, 'download')) {
            return 'view';
        }

        $verb = strtoupper($request->method());

        return match ($verb) {
            'GET', 'HEAD' => 'view',
            'POST' => 'create',
            'PUT', 'PATCH' => 'edit',
            'DELETE' => 'delete',
            default => null,
        };
    }

    private function resolveSettingsActionFromType(Request $request): string
    {
        $type = strtolower((string) $request->input('type', 'general'));

        return match ($type) {
            'timezone' => 'timezone',
            'broadcast', 'broadcast-driver' => 'broadcast_driver',
            'email' => 'smtp',
            'billing' => 'billing',
            'frontend', 'frontend-seo', 'premium-home-media', 'frontend-contact' => 'frontend',
            default => 'general',
        };
    }

    private function hasCompanionPermission(int $roleId, string $module): bool
    {
        $fallbacks = self::COMPANION_PERMISSION_FALLBACKS[$module] ?? [];

        if (empty($fallbacks)) {
            return false;
        }

        foreach ($fallbacks as $fallback) {
            $fallbackModule = strtolower(trim((string) ($fallback['module'] ?? '')));
            $fallbackAction = strtolower(trim((string) ($fallback['action'] ?? '')));

            if ($fallbackModule === '' || $fallbackAction === '') {
                continue;
            }

            if (RolePermission::query()
                ->where('role_id', $roleId)
                ->where('module', $fallbackModule)
                ->where('action', $fallbackAction)
                ->exists()) {
                return true;
            }
        }

        return false;
    }
}
