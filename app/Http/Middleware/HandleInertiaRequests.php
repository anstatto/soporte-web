<?php

namespace App\Http\Middleware;

use App\Models\Departamento;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        $workspaces = [];
        if ($user) {
            $workspaces = ($user->hasRole('admin')
                ? Workspace::where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : $user->workspaces()->where('workspaces.is_active', true)->orderBy('name')->get(['workspaces.id', 'workspaces.name'])
            )->map(fn ($w) => ['id' => $w->id, 'name' => $w->name])->values();
        }

        return [
            ...parent::share($request),
            'csrf_token' => csrf_token(),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'departamento_id' => $user->departamento_id,
                    'current_workspace_id' => $user->current_workspace_id,
                    'roles' => $user->getRoleNames()->values()->all(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                    'is_admin' => $user->hasRole('admin'),
                    'is_soporte' => $user->esSoporte(),
                    'is_solicitante' => $user->hasRole('solicitante') && ! $user->esSoporte(),
                ] : null,
            ],
            'workspaces' => $workspaces,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'temp_password' => fn () => $request->session()->get('temp_password'),
            ],
            'unreadNotificationsCount' => $user
                ? $user->unreadNotifications()->count()
                : 0,
            'unreadChatsCount' => $user
                ? \App\Support\ChatInbox::unreadChatsCount($user)
                : 0,
            'catalog' => $user ? [
                'departamentos' => Departamento::orderBy('nombre')->get(['id', 'nombre']),
                'agentes' => $user->esSoporte()
                    ? User::where('is_active', true)
                        ->whereHas('roles', function ($r) {
                            $r->where('is_agent', true)
                                ->orWhereIn('name', ['admin', 'soporte']);
                        })
                        ->orderBy('name')
                        ->get(['id', 'name', 'username'])
                    : [],
            ] : null,
            'appSettings' => [
                'app_name' => Setting::get('app_name'),
                'company_name' => Setting::get('company_name'),
                'support_email' => Setting::get('support_email'),
            ],
            'livekit' => \App\Support\LiveKitConfig::status(),
        ];
    }
}
