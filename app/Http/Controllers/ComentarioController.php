<?php

namespace App\Http\Controllers;

use App\Events\ComentarioCreado;
use App\Models\Comentario;
use App\Models\Ticket;
use App\Models\TicketAdjunto;
use App\Models\User;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketMentionedNotification;
use App\Notifications\TicketMessageNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ComentarioController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        $this->authorize('comment', $ticket);

        $request->validate([
            'contenido' => 'nullable|string|max:5000',
            'imagen' => 'nullable|file|max:15360|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,webm,ogg,mp3,m4a,wav,aac,mpeg,mpga,opus',
            'archivo' => 'nullable|file|max:15360|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,webm,ogg,mp3,m4a,wav,aac,mpeg,mpga,opus',
        ]);

        $contenido = trim((string) $request->input('contenido', ''));
        $file = $request->file('archivo') ?: $request->file('imagen');
        $imagePath = null;
        $adjunto = null;

        if ($file) {
            $mime = $file->getMimeType() ?: $file->getClientMimeType();
            $kind = TicketAdjunto::kindFromMime($mime, $file->getClientOriginalName());
            $stored = $file->store('tickets/'.$ticket->id, 'public');

            if ($kind === 'image') {
                $imagePath = $stored;
            }
        }

        if ($contenido === '' && ! $file) {
            return response()->json(['message' => 'Escribe un mensaje o adjunta un archivo.'], 422);
        }

        $comentario = $ticket->comentarios()->create([
            'contenido' => $contenido !== '' ? $contenido : '',
            'imagen' => $imagePath,
            'user_id' => Auth::id(),
        ]);

        if ($file && isset($stored)) {
            $mime = $file->getMimeType() ?: $file->getClientMimeType();
            $adjunto = TicketAdjunto::create([
                'ticket_id' => $ticket->id,
                'comentario_id' => $comentario->id,
                'user_id' => Auth::id(),
                'path' => $stored,
                'nombre_original' => $file->getClientOriginalName(),
                'mime' => $mime,
                'size' => $file->getSize() ?: 0,
                'kind' => TicketAdjunto::kindFromMime($mime, $file->getClientOriginalName()),
            ]);
        }

        $this->clearTyping($ticket->id, Auth::id());

        broadcast(new ComentarioCreado($ticket, $comentario))->toOthers();

        $mentioned = $this->resolveMentions($contenido);
        $mentionedIds = $mentioned->pluck('id')->all();

        if ($mentioned->isNotEmpty() && Auth::user()->can('assign', Ticket::class)) {
            $already = $ticket->users()->pluck('users.id')->all();
            $toAttach = array_values(array_diff($mentionedIds, $already, [Auth::id()]));
            if ($toAttach) {
                $ticket->users()->syncWithoutDetaching($toAttach);
                $newAssignees = User::whereIn('id', $toAttach)->get();
                foreach ($newAssignees as $assignee) {
                    $assignee->notify(new TicketAssignedNotification($ticket));
                }
            }
        }

        foreach ($mentioned->where('id', '!=', Auth::id()) as $user) {
            $user->notify(new TicketMentionedNotification($ticket, Str::limit($contenido, 120)));
        }

        $recipients = collect();
        if (Auth::user()->esSoporte()) {
            // Agente escribe → avisa al solicitante y a otros asignados (no a todo el staff)
            if ($ticket->user_id !== Auth::id()) {
                $recipients->push($ticket->user);
            }
            $recipients = $recipients->merge($ticket->users()->where('users.id', '!=', Auth::id())->get());
        } else {
            // Solicitante escribe → solo asignados; si nadie está asignado, avisar a soporte
            $assignees = $ticket->users()->where('users.id', '!=', Auth::id())->get();
            if ($assignees->isNotEmpty()) {
                $recipients = $assignees;
            } else {
                $recipients = User::role(['admin', 'soporte'])->where('id', '!=', Auth::id())->get();
            }
        }

        $recipients = $recipients
            ->filter()
            ->unique('id')
            ->reject(fn ($u) => in_array($u->id, $mentionedIds, true) || $u->id === Auth::id());

        $mutedIds = \App\Models\ChatState::query()
            ->where('chat_type', \App\Models\ChatState::TYPE_TICKET)
            ->where('chat_id', $ticket->id)
            ->whereNotNull('muted_at')
            ->whereIn('user_id', $recipients->pluck('id'))
            ->pluck('user_id')
            ->all();

        $recipients = $recipients->reject(fn ($u) => in_array($u->id, $mutedIds, true));

        if ($recipients->isNotEmpty()) {
            $kind = $file
                ? TicketAdjunto::kindFromMime(
                    $file->getMimeType() ?: $file->getClientMimeType(),
                    $file->getClientOriginalName()
                )
                : null;
            $excerpt = $contenido !== ''
                ? $contenido
                : match ($kind) {
                    'audio' => '🎤 Nota de voz',
                    'image' => 'Envió una captura',
                    'pdf', 'word' => 'Envió un archivo',
                    default => $file ? 'Envió un archivo' : null,
                };

            Notification::send(
                $recipients,
                new TicketMessageNotification($ticket, $excerpt, $kind === 'image')
            );
        }

        $ticket->touch();
        $comentario->load('user:id,name,username');
        $ticket->load('users:id,name,username');

        $payload = [
            'comentario' => [
                'id' => $comentario->id,
                'contenido' => $comentario->contenido,
                'imagen' => $comentario->imagen,
                'imagen_url' => $comentario->imagen_url,
                'adjunto' => $adjunto?->toPayload(),
                'user' => $comentario->user,
                'created_at' => $comentario->created_at?->toIso8601String(),
                'mine' => true,
            ],
            'asignados' => $ticket->users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
            ])->values(),
            'adjunto' => $adjunto?->toPayload(),
        ];

        if ($request->wantsJson() || $request->ajax() || $request->hasFile('imagen') || $request->hasFile('archivo')) {
            return response()->json($payload);
        }

        return back()->with('success', 'Mensaje enviado.');
    }

    protected function clearTyping(int $ticketId, int $userId): void
    {
        $key = "ticket_typing_{$ticketId}";
        $list = Cache::get($key, []);
        unset($list[$userId]);
        Cache::put($key, $list, now()->addSeconds(30));
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function resolveMentions(string $contenido)
    {
        if ($contenido === '') {
            return collect();
        }

        preg_match_all('/@([a-zA-Z0-9._-]+)/u', $contenido, $matches);
        $handles = collect($matches[1] ?? [])
            ->map(fn ($h) => mb_strtolower($h))
            ->unique()
            ->values();

        if ($handles->isEmpty()) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->where(function ($q) use ($handles) {
                foreach ($handles as $handle) {
                    $q->orWhereRaw('LOWER(username) = ?', [$handle]);
                }
            })
            ->get(['id', 'name', 'username', 'email']);
    }

    public function destroy(Comentario $comentario)
    {
        $ticket = $comentario->ticket;
        $this->authorize('view', $ticket);

        if (Auth::id() !== $comentario->user_id && ! Auth::user()->esSoporte()) {
            abort(403);
        }

        if ($comentario->imagen) {
            Storage::disk('public')->delete($comentario->imagen);
        }

        $comentario->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Mensaje eliminado.');
    }

    public function edit(Comentario $comentario)
    {
        if (Auth::id() !== $comentario->user_id) {
            abort(403);
        }

        return Inertia::render('Comentarios/Edit', [
            'comentario' => $comentario->load('ticket:id,titulo'),
        ]);
    }

    public function update(Request $request, Comentario $comentario)
    {
        if (Auth::id() !== $comentario->user_id) {
            abort(403);
        }

        $request->validate(['contenido' => 'required|string']);
        $comentario->update($request->only('contenido'));

        return redirect()->route('tickets.show', $comentario->ticket_id)->with('success', 'Mensaje actualizado.');
    }
}
