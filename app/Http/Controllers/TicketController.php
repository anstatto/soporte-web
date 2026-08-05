<?php

namespace App\Http\Controllers;

use App\Events\UsuarioEscribiendo;
use App\Models\Departamento;
use App\Models\Estado;
use App\Models\Etiqueta;
use App\Models\Ticket;
use App\Models\TicketAdjunto;
use App\Models\User;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketMovedNotification;
use App\Support\ChatInbox;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Ticket::class);

        $user = Auth::user();
        $query = Ticket::with(['departamento', 'estado', 'user', 'users'])
            ->withCount('comentarios')
            ->withMax('comentarios', 'created_at');

        if ($user->current_workspace_id) {
            $query->where('workspace_id', $user->current_workspace_id);
        }

        if (! $user->esSoporte()) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('users', fn ($u) => $u->where('users.id', $user->id));
            });
        }

        if ($request->filled('titulo')) {
            $query->where('titulo', 'like', '%'.$request->input('titulo').'%');
        }
        if ($request->filled('departamento_id')) {
            $query->where('departamento_id', $request->input('departamento_id'));
        }
        if ($request->filled('estado_id')) {
            $query->where('estado_id', $request->input('estado_id'));
        }
        if ($request->filled('fecha_inicio')) {
            $query->where('created_at', '>=', Carbon::parse($request->fecha_inicio)->startOfDay());
        }
        if ($request->filled('fecha_fin')) {
            $query->where('created_at', '<=', Carbon::parse($request->fecha_fin)->endOfDay());
        }
        if ($request->input('cerrados') === '0') {
            $query->whereHas('estado', fn ($q) => $q->where('nombre', '!=', 'Cerrado'));
        }
        if ($request->input('cerrados') === '1') {
            $query->whereHas('estado', fn ($q) => $q->where('nombre', 'Cerrado'));
        }

        if ($request->input('sin_asignar') === '1') {
            $query->whereDoesntHave('users');
        }

        $tickets = $query
            ->orderByRaw('COALESCE((SELECT MAX(created_at) FROM comentarios WHERE comentarios.ticket_id = tickets.id), tickets.created_at) DESC')
            ->paginate(40)
            ->withQueryString();

        $lastMessages = $this->latestComentariosFor($tickets->getCollection()->pluck('id')->all());

        // Marcar leído antes de calcular badges del listado
        $chatId = (int) $request->input('chat');
        $activeTicketModel = null;
        $ticketHighlightAfter = null;
        $ticketHadUnread = false;
        if ($chatId > 0) {
            $candidate = Ticket::find($chatId);
            if ($candidate && $user->can('view', $candidate)) {
                $wsOk = ! $user->current_workspace_id
                    || (int) $candidate->workspace_id === (int) $user->current_workspace_id;
                if ($wsOk) {
                    $prevState = \App\Models\ChatState::for($user, \App\Models\ChatState::TYPE_TICKET, $candidate->id);
                    $ticketHighlightAfter = $prevState->last_read_at?->toIso8601String();
                    $ticketHadUnread = ((ChatInbox::ticketMetas($user, [$candidate->id])[$candidate->id]['unread'] ?? 0) > 0);
                    ChatInbox::markTicketRead($user, $candidate->id);
                    $user->unreadNotifications
                        ->filter(fn ($n) => (int) ($n->data['ticket_id'] ?? 0) === $candidate->id)
                        ->each->markAsRead();
                    $activeTicketModel = $candidate;
                }
            }
        }

        $dmController = app(ConversacionController::class);
        $canDm = $user->can('chat with users');
        $dmId = (int) $request->input('dm');
        $dmHighlightAfter = null;
        $dmHadUnread = false;
        if ($canDm && $dmId > 0) {
            $convPreview = \App\Models\Conversacion::with(['users', 'latestMensaje'])->find($dmId);
            if ($convPreview && $user->can('view', $convPreview)) {
                $prevDm = \App\Models\ChatState::for($user, \App\Models\ChatState::TYPE_DM, $convPreview->id);
                $pivotRead = $convPreview->users->firstWhere('id', $user->id)?->pivot?->last_read_at;
                $dmHighlightAfter = $prevDm->last_read_at?->toIso8601String()
                    ?: ($pivotRead instanceof \Carbon\CarbonInterface ? $pivotRead->toIso8601String() : ($pivotRead ?: null));
                $dmHadUnread = ((ChatInbox::dmMetas($user, collect([$convPreview]))[$convPreview->id]['unread'] ?? 0) > 0);
                ChatInbox::markDmRead($user, $convPreview->id);
            }
        }

        $ticketMetas = ChatInbox::ticketMetas($user, $tickets->getCollection()->pluck('id')->all());

        $tickets->through(function (Ticket $t) use ($user, $lastMessages, $ticketMetas) {
            $metaPack = $ticketMetas[$t->id] ?? ['unread' => 0, 'meta' => []];

            return [
                'id' => $t->id,
                'titulo' => $t->titulo,
                'descripcion' => \Illuminate\Support\Str::limit($t->descripcion, 80),
                'estado' => $t->estado?->only(['id', 'nombre', 'color']),
                'departamento' => $t->departamento?->only(['id', 'nombre']),
                'prioridad' => $t->prioridad,
                'prioridad_label' => Ticket::PRIORIDADES[$t->prioridad]['label'] ?? ucfirst($t->prioridad ?? 'media'),
                'prioridad_color' => Ticket::PRIORIDADES[$t->prioridad]['color'] ?? '#5B6B7C',
                'user' => $t->user?->only(['id', 'name', 'username']),
                'asignados' => $t->users->map->only(['id', 'name', 'username'])->values(),
                'sin_asignar' => $t->users->isEmpty(),
                'comentarios_count' => $t->comentarios_count,
                'last_activity' => $t->comentarios_max_created_at ?? $t->created_at?->toIso8601String(),
                'last_message' => $lastMessages[$t->id] ?? null,
                'fecha_entrega' => $t->fecha_entrega,
                'created_at' => $t->created_at?->toIso8601String(),
                'peer' => $this->chatPeer($t, $user),
                'unread' => (int) ($metaPack['unread'] ?? 0),
                'meta' => $metaPack['meta'] ?? [],
            ];
        });

        $activeChat = null;
        if ($activeTicketModel) {
            $ticket = Ticket::with([
                'user:id,name,username',
                'departamento:id,nombre',
                'estado:id,nombre,color',
                'users:id,name,username',
                'comentarios.user:id,name,username',
                'comentarios.adjunto',
                'adjuntos',
            ])->find($activeTicketModel->id);

            if ($ticket) {
                $activeChat = [
                    'id' => $ticket->id,
                    'titulo' => $ticket->titulo,
                    'descripcion' => $ticket->descripcion,
                    'estado' => $ticket->estado?->only(['id', 'nombre', 'color']),
                    'departamento' => $ticket->departamento?->only(['id', 'nombre']),
                    'user' => $ticket->user?->only(['id', 'name', 'username']),
                    'asignados' => $ticket->users->map->only(['id', 'name', 'username'])->values(),
                    'participantes' => $this->chatParticipants($ticket),
                    'comentarios' => $ticket->comentarios->map(fn ($c) => $this->comentarioPayload($c))->values(),
                    'adjuntos' => $ticket->adjuntos->map->toPayload()->values(),
                    'canComment' => $user->can('comment', $ticket),
                    'created_at' => $ticket->created_at?->toIso8601String(),
                    'meta' => \App\Models\ChatState::for($user, \App\Models\ChatState::TYPE_TICKET, $ticket->id)->toMeta(),
                    'highlight_after' => $ticketHighlightAfter,
                    'had_unread' => $ticketHadUnread,
                ];
            }
        }

        $activeDm = null;
        if ($canDm && $dmId > 0) {
            $conv = \App\Models\Conversacion::with(['users:id,name,username', 'mensajes.user:id,name,username'])->find($dmId);
            if ($conv && $user->can('view', $conv)) {
                $activeDm = $dmController->payload($conv, $user);
                $activeDm['meta'] = \App\Models\ChatState::for($user, \App\Models\ChatState::TYPE_DM, $conv->id)->toMeta();
                $activeDm['highlight_after'] = $dmHighlightAfter;
                $activeDm['had_unread'] = $dmHadUnread;
            }
        }

        $inboxFilter = $request->input('inbox', 'all');
        if (! in_array($inboxFilter, ['all', 'unread', 'starred', 'archived'], true)) {
            $inboxFilter = 'all';
        }

        $tab = $request->input('tab', $activeDm ? 'personas' : 'incidencias');
        if (! in_array($tab, ['incidencias', 'personas'], true)) {
            $tab = 'incidencias';
        }
        if ($tab === 'personas' && ! $canDm) {
            $tab = 'incidencias';
        }

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'filters' => array_merge(
                $request->only(['titulo', 'departamento_id', 'estado_id', 'fecha_inicio', 'fecha_fin', 'cerrados', 'sin_asignar', 'chat', 'tab', 'dm']),
                ['inbox' => $inboxFilter],
            ),
            'departamentos' => Departamento::orderBy('nombre')->get(['id', 'nombre']),
            'estados' => Estado::orderBy('nombre')->get(['id', 'nombre', 'color']),
            'activeChat' => $activeChat,
            'isSoporte' => $user->esSoporte(),
            'canCreate' => $user->can('create tickets'),
            'mentionUsers' => $user->esSoporte()
                ? User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'username'])
                : User::role(['admin', 'soporte'])->where('is_active', true)->orderBy('name')->get(['id', 'name', 'username']),
            'tab' => $tab,
            'canDm' => $canDm,
            'dmConversations' => $canDm ? $dmController->listForUser($user) : [],
            'dmDirectory' => $canDm ? $dmController->directoryForUser($user) : [],
            'activeDm' => $activeDm,
            'inboxFilter' => $inboxFilter,
        ]);
    }

    /**
     * @param  array<int, int|string>  $ticketIds
     * @return array<int, array{preview: string, by: string|null, created_at: string|null}>
     */
    protected function latestComentariosFor(array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }

        $maxIds = DB::table('comentarios')
            ->select('ticket_id', DB::raw('MAX(id) as max_id'))
            ->whereIn('ticket_id', $ticketIds)
            ->groupBy('ticket_id')
            ->pluck('max_id', 'ticket_id');

        if ($maxIds->isEmpty()) {
            return [];
        }

        $rows = \App\Models\Comentario::with(['user:id,name', 'adjunto'])
            ->whereIn('id', $maxIds->values()->all())
            ->get()
            ->keyBy('ticket_id');

        $out = [];
        foreach ($rows as $ticketId => $c) {
            $preview = trim((string) $c->contenido);
            if ($preview === '') {
                $kind = $c->relationLoaded('adjunto') ? $c->adjunto?->kind : null;
                if (! $kind && $c->imagen) {
                    $kind = 'image';
                }
                $preview = match ($kind) {
                    'audio' => '🎤 Nota de voz',
                    'image' => '📷 Imagen',
                    'pdf' => '📄 PDF',
                    'word' => '📄 Documento',
                    default => $c->imagen ? '📷 Imagen' : '—',
                };
            }
            $out[(int) $ticketId] = [
                'preview' => \Illuminate\Support\Str::limit($preview, 72),
                'by' => $c->user?->name,
                'created_at' => $c->created_at?->toIso8601String(),
            ];
        }

        return $out;
    }

    protected function chatPeer(Ticket $t, User $viewer): array
    {
        // Para el solicitante: mostrar agente asignado o “Soporte”
        // Para el agente: mostrar el creador
        if ($viewer->esSoporte()) {
            $peer = $t->user;
        } else {
            $peer = $t->users->first() ?? null;
        }

        if (! $peer) {
            return [
                'id' => null,
                'name' => $viewer->esSoporte() ? ($t->user?->name ?? 'Solicitante') : 'Soporte',
                'username' => null,
            ];
        }

        return [
            'id' => $peer->id,
            'name' => $peer->name,
            'username' => $peer->username ?? null,
        ];
    }

    protected function chatParticipants(Ticket $ticket): array
    {
        $list = collect();
        if ($ticket->user) {
            $list->push($ticket->user);
        }
        foreach ($ticket->users as $u) {
            $list->push($u);
        }

        return $list->unique('id')->values()->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'username' => $u->username,
        ])->all();
    }

    public function board(Request $request)
    {
        $this->authorize('viewAny', Ticket::class);

        $user = Auth::user();
        $query = Ticket::with([
            'user:id,name',
            'departamento:id,nombre',
            'users:id,name,username',
            'estado:id,nombre,color',
            'etiquetas:id,nombre,color,emoji',
        ])->withCount('comentarios');

        if ($user->current_workspace_id) {
            $query->where('workspace_id', $user->current_workspace_id);
        }

        if (! $user->esSoporte()) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('users', fn ($u) => $u->where('users.id', $user->id));
            });
        }

        if ($request->filled('q')) {
            $query->where('titulo', 'like', '%'.$request->q.'%');
        }

        $estados = Estado::orderBy('id')->get(['id', 'nombre', 'color']);
        $tickets = $query->orderBy('position')->orderByDesc('updated_at')->get()
            ->groupBy('estado_id')
            ->map(fn ($group) => $group->map(fn (Ticket $t) => $this->cardPayload($t))->values());

        return Inertia::render('Tickets/Board', [
            'estados' => $estados,
            'columns' => $tickets,
            'departamentos' => Departamento::orderBy('nombre')->get(['id', 'nombre']),
            'etiquetas' => Etiqueta::orderBy('nombre')->get(['id', 'nombre', 'color', 'emoji']),
            'prioridades' => collect(Ticket::PRIORIDADES)->map(fn ($meta, $key) => [
                'value' => $key,
                'label' => $meta['label'],
                'emoji' => $meta['emoji'],
                'color' => $meta['color'],
            ])->values(),
            'usuarios' => $user->esSoporte()
                ? User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'username'])
                : [],
            'filters' => ['q' => $request->q ?? ''],
            'canDrag' => $user->esSoporte() && $user->can('edit tickets'),
            'canCreate' => $user->can('create tickets'),
            'canManageUsers' => $user->can('manage users') || $user->can('view users'),
            'defaultDepartamentoId' => $user->departamento_id
                ?? Departamento::query()->value('id'),
        ]);
    }

    public function quickStore(Request $request)
    {
        $this->authorize('create', Ticket::class);

        $user = Auth::user();
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'estado_id' => 'required|exists:estados,id',
            'departamento_id' => 'nullable|exists:departamentos,id',
            'descripcion' => 'nullable|string',
        ]);

        $departamentoId = $validated['departamento_id']
            ?? $user->departamento_id
            ?? Departamento::query()->value('id');

        abort_unless($departamentoId, 422, 'Configura al menos un departamento.');

        if (! $user->esSoporte()) {
            $estado = Estado::orderBy('id')->first();
            $validated['estado_id'] = $estado?->id ?? $validated['estado_id'];
        }

        $descripcion = ($validated['descripcion'] ?? null) ?: $validated['titulo'];

        Ticket::where('estado_id', $validated['estado_id'])->increment('position');

        $ticket = Ticket::create([
            'titulo' => $validated['titulo'],
            'descripcion' => $descripcion,
            'departamento_id' => $departamentoId,
            'estado_id' => $validated['estado_id'],
            'prioridad' => 'media',
            'position' => 0,
            'user_id' => $user->id,
            'workspace_id' => $user->current_workspace_id
                ?? $user->workspaces()->value('workspaces.id'),
            'fecha_entrega' => now()->addDays(3),
            'recordatorio' => now()->addDay(),
        ]);

        $ticket->comentarios()->create([
            'contenido' => $descripcion,
            'user_id' => $user->id,
        ]);

        $staff = User::role(['admin', 'soporte'])->where('id', '!=', $user->id)->get();
        if ($staff->isNotEmpty()) {
            Notification::send($staff, new TicketCreatedNotification($ticket));
        }

        $ticket->load(['user:id,name', 'departamento:id,nombre', 'users:id,name,username', 'estado:id,nombre,color', 'etiquetas']);
        $ticket->loadCount('comentarios');

        return response()->json(['card' => $this->cardPayload($ticket)]);
    }

    public function card(Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'user:id,name,username',
            'departamento:id,nombre',
            'estado:id,nombre,color',
            'users:id,name,username',
            'etiquetas:id,nombre,color,emoji',
            'comentarios.user:id,name,username',
            'comentarios.adjunto',
            'adjuntos',
        ]);

        return response()->json([
            'ticket' => [
                'id' => $ticket->id,
                'titulo' => $ticket->titulo,
                'descripcion' => $ticket->descripcion,
                'estado_id' => $ticket->estado_id,
                'estado' => $ticket->estado,
                'departamento_id' => $ticket->departamento_id,
                'departamento' => $ticket->departamento,
                'prioridad' => $ticket->prioridad ?? 'media',
                'user' => $ticket->user,
                'asignados' => $ticket->users->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username,
                ])->values(),
                'etiquetas' => $ticket->etiquetas->map(fn ($e) => [
                    'id' => $e->id,
                    'nombre' => $e->nombre,
                    'color' => $e->color,
                    'emoji' => $e->emoji,
                ])->values(),
                'fecha_entrega' => optional($ticket->fecha_entrega)->format('Y-m-d'),
                'recordatorio' => optional($ticket->recordatorio)->format('Y-m-d'),
                'created_at' => $ticket->created_at?->toIso8601String(),
                'comentarios' => $ticket->comentarios->map(fn ($c) => $this->comentarioPayload($c)),
                'adjuntos' => $ticket->adjuntos->map->toPayload()->values(),
            ],
            'etiquetas' => Etiqueta::orderBy('nombre')->get(['id', 'nombre', 'color', 'emoji']),
            'prioridades' => collect(Ticket::PRIORIDADES)->map(fn ($meta, $key) => [
                'value' => $key,
                'label' => $meta['label'],
                'emoji' => $meta['emoji'],
                'color' => $meta['color'],
            ])->values(),
            'canManage' => Auth::user()->can('update', $ticket),
            'canChangeStatus' => Auth::user()->can('changeStatus', $ticket),
            'canAssign' => Auth::user()->can('assign', Ticket::class),
            'canComment' => Auth::user()->can('comment', $ticket),
        ]);
    }

    public function updateCard(Request $request, Ticket $ticket)
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'titulo' => 'sometimes|required|string|max:255',
            'descripcion' => 'sometimes|required|string',
            'departamento_id' => 'sometimes|required|exists:departamentos,id',
            'estado_id' => 'sometimes|required|exists:estados,id',
            'prioridad' => 'sometimes|nullable|in:baja,media,alta,urgente',
            'fecha_entrega' => 'nullable|date',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
            'etiqueta_ids' => 'nullable|array',
            'etiqueta_ids.*' => 'exists:etiquetas,id',
        ]);

        if (isset($validated['estado_id']) && ! Auth::user()->can('changeStatus', $ticket)) {
            unset($validated['estado_id']);
        }

        $previousEstadoId = $ticket->estado_id;
        $ticket->update(collect($validated)->except(['user_ids', 'etiqueta_ids'])->all());

        if (isset($validated['estado_id']) && (int) $previousEstadoId !== (int) $ticket->estado_id) {
            $this->notifyAssigneesOfMove($ticket, $previousEstadoId, $ticket->estado_id);
        }

        if ($request->has('etiqueta_ids')) {
            $ticket->etiquetas()->sync($request->input('etiqueta_ids', []));
        }

        if (Auth::user()->can('assign', Ticket::class) && $request->has('user_ids')) {
            $this->syncAssigneesAndNotify($ticket, $request->input('user_ids', []));
        }

        $ticket->load([
            'user:id,name,username',
            'departamento:id,nombre',
            'users:id,name,username',
            'estado:id,nombre,color',
            'etiquetas:id,nombre,color,emoji',
        ]);
        $ticket->loadCount('comentarios');

        return response()->json(['card' => $this->cardPayload($ticket)]);
    }

    protected function cardPayload(Ticket $t): array
    {
        $due = $t->fecha_entrega;
        $overdue = $due && $due->isPast() && optional($t->estado)->nombre !== 'Cerrado';
        $prioridad = $t->prioridad ?? 'media';
        $prioMeta = Ticket::PRIORIDADES[$prioridad] ?? Ticket::PRIORIDADES['media'];

        return [
            'id' => $t->id,
            'titulo' => $t->titulo,
            'descripcion' => \Illuminate\Support\Str::limit(strip_tags($t->descripcion), 100),
            'estado_id' => $t->estado_id,
            'departamento' => $t->departamento?->nombre,
            'departamento_id' => $t->departamento_id,
            'prioridad' => $prioridad,
            'prioridad_label' => $prioMeta['label'],
            'prioridad_emoji' => $prioMeta['emoji'],
            'prioridad_color' => $prioMeta['color'],
            'position' => $t->position ?? 0,
            'label_color' => $t->estado?->color ?? '#1E4E79',
            'user' => $t->user?->name,
            'user_initial' => mb_strtoupper(mb_substr($t->user?->name ?? '?', 0, 1)),
            'asignados' => $t->users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'initial' => mb_strtoupper(mb_substr($u->name, 0, 1)),
            ])->values(),
            'etiquetas' => ($t->relationLoaded('etiquetas') ? $t->etiquetas : collect())->map(fn ($e) => [
                'id' => $e->id,
                'nombre' => $e->nombre,
                'color' => $e->color,
                'emoji' => $e->emoji,
            ])->values(),
            'fecha_entrega' => optional($due)->toIso8601String(),
            'fecha_entrega_label' => optional($due)->format('d M'),
            'overdue' => (bool) $overdue,
            'comentarios_count' => $t->comentarios_count ?? 0,
        ];
    }

    public function reorder(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->esSoporte() && $user->can('edit tickets'), 403);

        $data = $request->validate([
            'estado_id' => 'required|exists:estados,id',
            'ticket_ids' => 'present|array',
            'ticket_ids.*' => 'integer|exists:tickets,id',
        ]);

        $moved = [];

        DB::transaction(function () use ($data, &$moved) {
            $newEstadoId = (int) $data['estado_id'];

            foreach ($data['ticket_ids'] as $index => $ticketId) {
                $ticket = Ticket::query()->find($ticketId);
                if (! $ticket) {
                    continue;
                }

                $oldEstadoId = (int) $ticket->estado_id;
                $ticket->update([
                    'estado_id' => $newEstadoId,
                    'position' => $index,
                ]);

                if ($oldEstadoId !== $newEstadoId) {
                    $moved[] = [$ticket, $oldEstadoId, $newEstadoId];
                }
            }
        });

        foreach ($moved as [$ticket, $fromId, $toId]) {
            $this->notifyAssigneesOfMove($ticket, $fromId, $toId);
        }

        return response()->json(['ok' => true]);
    }

    public function updateEstado(Request $request, Ticket $ticket)
    {
        $this->authorize('changeStatus', $ticket);

        $data = $request->validate([
            'estado_id' => 'required|exists:estados,id',
            'position' => 'nullable|integer|min:0',
        ]);

        $previousEstadoId = $ticket->estado_id;
        $payload = ['estado_id' => $data['estado_id']];
        if (array_key_exists('position', $data)) {
            $payload['position'] = $data['position'];
        }

        $ticket->update($payload);

        if ((int) $previousEstadoId !== (int) $ticket->estado_id) {
            $this->notifyAssigneesOfMove($ticket, $previousEstadoId, $ticket->estado_id);
        }

        if ($request->header('X-Inertia')) {
            return back()->with('success', 'Estado actualizado.');
        }

        return response()->json(['ok' => true, 'ticket' => $ticket->fresh('estado')]);
    }

    public function create()
    {
        $this->authorize('create', Ticket::class);

        return Inertia::render('Tickets/Create', [
            'departamentos' => Departamento::orderBy('nombre')->get(['id', 'nombre']),
            'estados' => Estado::orderBy('nombre')->get(['id', 'nombre', 'color']),
            'usuarios' => Auth::user()->esSoporte()
                ? User::where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : [],
            'isSoporte' => Auth::user()->esSoporte(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Ticket::class);

        $user = Auth::user();
        $rules = [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string|min:10',
            'departamento_id' => 'required|exists:departamentos,id',
            'fecha_entrega' => 'nullable|date',
            'recordatorio' => 'nullable|date',
            'estado_id' => 'nullable|exists:estados,id',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
            'adjuntos' => 'nullable|array|max:8',
            'adjuntos.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx',
        ];

        $validated = $request->validate($rules);

        if (empty($validated['estado_id'])) {
            $estado = Estado::where(function ($q) {
                $q->where('nombre', 'like', '%abierto%')
                    ->orWhere('nombre', 'like', '%pendiente%')
                    ->orWhere('nombre', 'like', '%recibido%')
                    ->orWhere('nombre', 'like', '%nuevo%')
                    ->orWhere('nombre', 'like', '%sin asignar%');
            })->first() ?? Estado::orderBy('id')->first();

            $validated['estado_id'] = $estado?->id;
            abort_unless($validated['estado_id'], 422, 'No hay estados configurados.');
        }

        if (! $user->esSoporte()) {
            // Solicitante nunca asigna
            unset($validated['user_ids']);
        }

        $validated['user_id'] = $user->id;
        $validated['fecha_entrega'] = $validated['fecha_entrega'] ?? now()->addDays(3);
        $validated['recordatorio'] = $validated['recordatorio'] ?? now()->addDay();
        $validated['workspace_id'] = $user->current_workspace_id
            ?? $user->workspaces()->value('workspaces.id');

        $assignees = $user->esSoporte() ? ($request->input('user_ids') ?? []) : [];
        unset($validated['user_ids'], $validated['adjuntos']);

        $ticket = Ticket::create($validated);

        if ($assignees) {
            $this->syncAssigneesAndNotify($ticket, $assignees);
        }

        // Primer mensaje del hilo = descripción
        $comentario = $ticket->comentarios()->create([
            'contenido' => $validated['descripcion'],
            'user_id' => $user->id,
        ]);

        $this->storeTicketAdjuntos(
            $ticket,
            $request->file('adjuntos', []),
            $user->id,
            $comentario->id,
        );

        $staff = User::role(['admin', 'soporte'])->where('id', '!=', $user->id)->get();
        if ($staff->isNotEmpty()) {
            Notification::send($staff, new TicketCreatedNotification($ticket));
        }

        return redirect()
            ->route(
                $user->esSoporte() ? 'tickets.board' : 'tickets.index',
                $user->esSoporte()
                    ? ['card' => $ticket->id]
                    : ['chat' => $ticket->id],
            )
            ->with('success', 'Solicitud creada. Quedó en bandeja'.(
                $ticket->users()->exists() ? '.' : ' sin asignar.'
            ));
    }

    /**
     * @param  array<int, UploadedFile|null>  $files
     */
    protected function storeTicketAdjuntos(Ticket $ticket, array $files, int $userId, ?int $comentarioId = null): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $file->store('tickets/'.$ticket->id, 'public');
            $mime = $file->getMimeType() ?: $file->getClientMimeType();

            TicketAdjunto::create([
                'ticket_id' => $ticket->id,
                'comentario_id' => $comentarioId,
                'user_id' => $userId,
                'path' => $path,
                'nombre_original' => $file->getClientOriginalName(),
                'mime' => $mime,
                'size' => $file->getSize() ?: 0,
                'kind' => TicketAdjunto::kindFromMime($mime, $file->getClientOriginalName()),
            ]);
        }
    }

    public function show(Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        Auth::user()->unreadNotifications
            ->where('data.ticket_id', $ticket->id)
            ->markAsRead();

        $ticket->load(['user:id,name', 'departamento:id,nombre', 'estado:id,nombre,color', 'users:id,name', 'comentarios.user:id,name']);

        $inbox = Ticket::with(['estado:id,nombre,color', 'user:id,name'])
            ->when(! Auth::user()->esSoporte(), function ($q) {
                $user = Auth::user();
                $q->where(function ($qq) use ($user) {
                    $qq->where('user_id', $user->id)
                        ->orWhereHas('users', fn ($u) => $u->where('users.id', $user->id));
                });
            })
            ->latest()
            ->take(30)
            ->get(['id', 'titulo', 'estado_id', 'user_id', 'created_at', 'updated_at']);

        return Inertia::render('Tickets/Show', [
            'ticket' => [
                'id' => $ticket->id,
                'titulo' => $ticket->titulo,
                'descripcion' => $ticket->descripcion,
                'estado' => $ticket->estado,
                'departamento' => $ticket->departamento,
                'user' => $ticket->user,
                'asignados' => $ticket->users,
                'fecha_entrega' => $ticket->fecha_entrega,
                'recordatorio' => $ticket->recordatorio,
                'created_at' => $ticket->created_at?->toIso8601String(),
                'comentarios' => $ticket->comentarios->map(fn ($c) => $this->comentarioPayload($c)),
            ],
            'inbox' => $inbox,
            'estados' => Estado::orderBy('nombre')->get(['id', 'nombre', 'color']),
            'usuarios' => Auth::user()->esSoporte()
                ? User::where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : [],
            'canManage' => Auth::user()->can('update', $ticket),
            'canChangeStatus' => Auth::user()->can('changeStatus', $ticket),
            'canComment' => Auth::user()->can('comment', $ticket),
        ]);
    }

    public function edit(Ticket $ticket)
    {
        $this->authorize('update', $ticket);

        return Inertia::render('Tickets/Edit', [
            'ticket' => $ticket->load(['users:id,name']),
            'departamentos' => Departamento::orderBy('nombre')->get(['id', 'nombre']),
            'estados' => Estado::orderBy('nombre')->get(['id', 'nombre', 'color']),
            'usuarios' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Ticket $ticket)
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'departamento_id' => 'required|exists:departamentos,id',
            'estado_id' => 'required|exists:estados,id',
            'fecha_entrega' => 'nullable|date',
            'recordatorio' => 'nullable|date',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $previousEstadoId = $ticket->estado_id;
        $ticket->update($validated);

        if ((int) $previousEstadoId !== (int) $ticket->estado_id) {
            $this->notifyAssigneesOfMove($ticket, $previousEstadoId, $ticket->estado_id);
        }

        if (Auth::user()->can('assign', Ticket::class) && $request->has('user_ids')) {
            $this->syncAssigneesAndNotify($ticket, $request->input('user_ids', []));
        }

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket actualizado.');
    }

    public function destroy(Ticket $ticket)
    {
        $this->authorize('delete', $ticket);
        $ticket->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('tickets.index')->with('success', 'Ticket eliminado.');
    }

    public function typing(Request $request, Ticket $ticket)
    {
        $this->authorize('comment', $ticket);

        $key = "ticket_typing_{$ticket->id}";
        $list = Cache::get($key, []);
        $list[Auth::id()] = [
            'id' => Auth::id(),
            'name' => Auth::user()->name,
            'at' => now()->timestamp,
        ];

        $cutoff = now()->timestamp - 8;
        $list = array_filter($list, fn ($row) => ($row['at'] ?? 0) >= $cutoff);
        Cache::put($key, $list, now()->addSeconds(30));

        broadcast(new UsuarioEscribiendo(
            'ticket.'.$ticket->id,
            Auth::id(),
            Auth::user()->name
        ))->toOthers();

        return response()->json(['ok' => true]);
    }

    public function pollComentarios(Ticket $ticket, Request $request)
    {
        $this->authorize('view', $ticket);

        $after = $request->input('after');
        $query = $ticket->comentarios()->with('user:id,name,username')->orderBy('id');

        if ($after) {
            $query->where('id', '>', $after);
        }

        return response()->json([
            'comentarios' => $query->get()->map(fn ($c) => $this->comentarioPayload($c)),
            'typing' => $this->typingUsers($ticket),
        ]);
    }

    protected function typingUsers(Ticket $ticket): array
    {
        $key = "ticket_typing_{$ticket->id}";
        $list = Cache::get($key, []);
        $cutoff = now()->timestamp - 8;
        $me = Auth::id();

        return collect($list)
            ->filter(fn ($row, $uid) => (int) $uid !== (int) $me && ($row['at'] ?? 0) >= $cutoff)
            ->values()
            ->map(fn ($row) => [
                'id' => $row['id'],
                'name' => $row['name'],
            ])
            ->all();
    }

    protected function comentarioPayload($c): array
    {
        $adjunto = null;
        if ($c->relationLoaded('adjunto') && $c->adjunto) {
            $adjunto = $c->adjunto->toPayload();
        }

        return [
            'id' => $c->id,
            'contenido' => $c->contenido,
            'imagen' => $c->imagen,
            'imagen_url' => $c->imagen_url,
            'adjunto' => $adjunto,
            'user_id' => $c->user_id,
            'user' => $c->user,
            'created_at' => $c->created_at?->toIso8601String(),
            'mine' => (int) $c->user_id === (int) Auth::id(),
        ];
    }

    /**
     * Notifica a asignados (y al creador) cuando una tarjeta cambia de estado.
     */
    protected function notifyAssigneesOfMove(Ticket $ticket, int|string|null $fromEstadoId, int|string|null $toEstadoId): void
    {
        if ((int) $fromEstadoId === (int) $toEstadoId) {
            return;
        }

        $from = Estado::find($fromEstadoId)?->nombre ?? 'Sin estado';
        $to = Estado::find($toEstadoId)?->nombre ?? 'Sin estado';

        $ticket->loadMissing(['users:id,name', 'user:id,name']);

        $recipients = collect($ticket->users);
        if ($ticket->user && ! $recipients->contains('id', $ticket->user_id)) {
            $recipients->push($ticket->user);
        }

        $recipients = $recipients
            ->unique('id')
            ->reject(fn (User $u) => (int) $u->id === (int) Auth::id())
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new TicketMovedNotification($ticket->fresh(), $from, $to));
    }

    /**
     * Sincroniza asignados y notifica solo a los recién añadidos.
     */
    protected function syncAssigneesAndNotify(Ticket $ticket, array $userIds): void
    {
        $previous = $ticket->users()->pluck('users.id')->all();
        $ticket->users()->sync($userIds);
        $ticket->load('users:id,name,username');

        foreach ($ticket->users->whereNotIn('id', $previous) as $assignee) {
            if ((int) $assignee->id !== (int) Auth::id()) {
                $assignee->notify(new TicketAssignedNotification($ticket));
            }
        }
    }
}
