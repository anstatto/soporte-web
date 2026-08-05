<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\LiveKitConfig;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LlamadasController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        abort_unless($user->can('use calls') || $user->hasRole('admin'), 403);

        $directory = [];
        if ($user->can('chat with users') || $user->esSoporte()) {
            $wsId = $user->current_workspace_id;
            $q = User::query()
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->orderBy('name');

            if ($wsId) {
                $q->whereHas('workspaces', fn ($w) => $w->where('workspaces.id', $wsId));
            }

            $directory = $q->limit(40)->get(['id', 'name', 'username'])->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
            ])->values();
        }

        return Inertia::render('Llamadas/Index', [
            'livekit' => LiveKitConfig::status(),
            'canManage' => $user->can('manage calls') || $user->hasRole('admin'),
            'directory' => $directory,
            'canChat' => $user->can('chat with users'),
        ]);
    }
}
