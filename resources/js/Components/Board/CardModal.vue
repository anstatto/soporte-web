<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { ElMessageBox } from 'element-plus';
import { formatDate, timeAgo } from '@/Composables/useDate';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    ticketId: { type: [Number, String], required: true },
    estados: Array,
    departamentos: Array,
    usuarios: Array,
    etiquetas: { type: Array, default: () => [] },
    prioridades: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'updated', 'deleted']);
const page = usePage();

const loading = ref(true);
const ticket = ref(null);
const catalogEtiquetas = ref([]);
const catalogPrioridades = ref([]);
const canManage = ref(false);
const canChangeStatus = ref(false);
const canAssign = ref(false);
const canComment = ref(false);
const comment = ref('');
const saving = ref(false);
const sending = ref(false);
const pendingImage = ref(null);
const imagePreview = ref(null);
const threadRef = ref(null);
const composerRef = ref(null);
const memberMenuOpen = ref(false);
const memberQuery = ref('');
const mentionOpen = ref(false);
const mentionQuery = ref('');
const mentionIndex = ref(0);
const mentionStart = ref(-1);
const typingUsers = ref([]);
const realtimeActive = ref(false);
let pollTimer = null;
let typingTimer = null;
let lastTypingSent = 0;
let echoChannel = null;

const headers = () => ({
    'X-Requested-With': 'XMLHttpRequest',
    Accept: 'application/json',
});

const chipVars = (color) => ({
    '--chip-fg': color || '#85B8FF',
    '--chip-bg': `${color || '#579DFF'}26`,
    '--chip-border': `${color || '#579DFF'}55`,
});

const priorityLabel = (p) => (p?.emoji ? `${p.emoji} ${p.label}` : p?.label);

const typingLabel = computed(() => {
    const names = typingUsers.value.map((u) => u.name).filter(Boolean);
    if (!names.length) return '';
    if (names.length === 1) return `${names[0]} está escribiendo…`;
    if (names.length === 2) return `${names[0]} y ${names[1]} están escribiendo…`;
    return 'Varias personas están escribiendo…';
});

const avatar = (name) =>
    `https://ui-avatars.com/api/?name=${encodeURIComponent(name || '?')}&background=579DFF&color=fff`;

const meId = computed(() => Number(page.props.auth?.user?.id));
const isMine = (c) =>
    c.mine === true || Number(c.user_id ?? c.user?.id) === meId.value;

const msgTime = (iso) => {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
    } catch {
        return timeAgo(iso);
    }
};

const assignedIds = computed(() => new Set((ticket.value?.asignados || []).map((a) => a.id)));
const selectedEtiquetaIds = computed(() => new Set((ticket.value?.etiquetas || []).map((e) => e.id)));

const labelCatalog = computed(() =>
    (catalogEtiquetas.value.length ? catalogEtiquetas.value : props.etiquetas) || [],
);
const priorityCatalog = computed(() =>
    (catalogPrioridades.value.length ? catalogPrioridades.value : props.prioridades) || [],
);

const availableMembers = computed(() => {
    const q = memberQuery.value.trim().toLowerCase();
    return (props.usuarios || []).filter((u) => {
        if (assignedIds.value.has(u.id)) return false;
        if (!q) return true;
        return (
            u.name.toLowerCase().includes(q) ||
            (u.username || '').toLowerCase().includes(q)
        );
    });
});

const mentionCandidates = computed(() => {
    const q = mentionQuery.value.toLowerCase();
    return (props.usuarios || [])
        .filter((u) => {
            const un = (u.username || '').toLowerCase();
            const nm = (u.name || '').toLowerCase();
            return !q || un.includes(q) || nm.includes(q);
        })
        .slice(0, 8);
});

const scrollBottom = async ({ force = false } = {}) => {
    await nextTick();
    const el = threadRef.value;
    if (!el) return;
    const nearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 80;
    if (force || nearBottom) {
        el.scrollTop = el.scrollHeight;
    }
};

const appendComment = async (raw, { forceScroll = false } = {}) => {
    if (!ticket.value?.comentarios) return;
    const me = page.props.auth.user.id;
    const incoming = {
        ...raw,
        mine: Number(raw.user_id ?? raw.user?.id) === Number(me),
    };
    if (ticket.value.comentarios.some((c) => c.id === incoming.id)) return;
    ticket.value.comentarios.push(incoming);
    await scrollBottom({ force: forceScroll });
};

const load = async ({ silent = false } = {}) => {
    if (!silent) loading.value = true;
    try {
        const { data } = await axios.get(`/tickets/${props.ticketId}/card`, { headers: headers() });
        ticket.value = data.ticket;
        canManage.value = data.canManage;
        canChangeStatus.value = data.canChangeStatus;
        canAssign.value = data.canAssign;
        canComment.value = data.canComment;
        if (data.etiquetas?.length) catalogEtiquetas.value = data.etiquetas;
        if (data.prioridades?.length) catalogPrioridades.value = data.prioridades;
        axios.post('/notifications/mark-ticket-read', { ticket_id: props.ticketId }, { headers: headers() }).catch(() => {});
        if (!silent) await scrollBottom({ force: true });
    } catch {
        if (!silent) {
            toast.error('No se pudo abrir la tarjeta');
            emit('close');
        }
    } finally {
        loading.value = false;
    }
};

const pollNew = async () => {
    // Fallback: solo agrega mensajes nuevos. Nunca toca `comment` ni el composer.
    if (!ticket.value) return;
    const after = ticket.value.comentarios?.at(-1)?.id || 0;
    try {
        const { data } = await axios.get(`/tickets/${props.ticketId}/comentarios/poll`, {
            params: { after },
            headers: headers(),
        });
        if (data.comentarios?.length) {
            for (const c of data.comentarios) {
                await appendComment(c);
            }
        }
        if (!realtimeActive.value) {
            typingUsers.value = data.typing || [];
        }
    } catch {
        // silencioso
    }
};

const clearTypingUser = (userId) => {
    typingUsers.value = typingUsers.value.filter((u) => u.id !== userId);
};

const setTypingUser = (user) => {
    if (!user?.id || Number(user.id) === Number(page.props.auth.user.id)) return;
    const rest = typingUsers.value.filter((u) => u.id !== user.id);
    typingUsers.value = [...rest, user];
    if (typingTimer) clearTimeout(typingTimer);
    typingTimer = setTimeout(() => clearTypingUser(user.id), 4000);
};

const subscribeRealtime = () => {
    if (!window.Echo) {
        realtimeActive.value = false;
        return;
    }
    try {
        echoChannel = window.Echo.private(`ticket.${props.ticketId}`);
        echoChannel
            .listen('.comentario.creado', async (payload) => {
                await appendComment(payload.comentario);
                clearTypingUser(payload.comentario?.user_id ?? payload.comentario?.user?.id);
            })
            .listen('.usuario.escribiendo', (payload) => {
                setTypingUser(payload.user);
            });
        realtimeActive.value = true;
    } catch {
        realtimeActive.value = false;
    }
};

const unsubscribeRealtime = () => {
    if (window.Echo && props.ticketId) {
        window.Echo.leave(`ticket.${props.ticketId}`);
    }
    echoChannel = null;
    realtimeActive.value = false;
};

const pingTyping = () => {
    const now = Date.now();
    if (now - lastTypingSent < 1500) return;
    lastTypingSent = now;
    axios.post(`/tickets/${props.ticketId}/typing`, null, { headers: headers() }).catch(() => {});
};

onMounted(async () => {
    await load();
    subscribeRealtime();
    // Con Reverb: poll de respaldo cada 45s. Sin Reverb: cada 8s (nunca borra lo que escribes).
    const interval = realtimeActive.value ? 45000 : 8000;
    pollTimer = setInterval(pollNew, interval);
    window.addEventListener('keydown', onKey);
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
    if (typingTimer) clearTimeout(typingTimer);
    unsubscribeRealtime();
    window.removeEventListener('keydown', onKey);
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
});

watch(() => props.ticketId, async () => {
    unsubscribeRealtime();
    await load();
    subscribeRealtime();
});

const saveField = async (patch) => {
    const isAssignOnly = Object.keys(patch).length === 1 && 'user_ids' in patch;
    if (isAssignOnly) {
        if (!canAssign.value) return;
    } else {
        if (!canManage.value && !('estado_id' in patch && canChangeStatus.value)) return;
        if ('estado_id' in patch && !canChangeStatus.value) return;
        if (!('estado_id' in patch) && !canManage.value) return;
    }

    saving.value = true;
    try {
        const { data } = await axios.patch(`/tickets/${props.ticketId}/card`, patch, { headers: headers() });
        emit('updated', data.card);
        ticket.value.titulo = data.card.titulo;
        ticket.value.estado_id = data.card.estado_id;
        ticket.value.departamento_id = data.card.departamento_id;
        if (data.card.asignados) {
            ticket.value.asignados = data.card.asignados;
        }
        if (data.card.etiquetas) {
            ticket.value.etiquetas = data.card.etiquetas;
        }
        if (data.card.prioridad) {
            ticket.value.prioridad = data.card.prioridad;
        }
        if (patch.fecha_entrega !== undefined) ticket.value.fecha_entrega = patch.fecha_entrega;
        if (patch.estado_id) {
            ticket.value.estado = props.estados.find((e) => e.id === Number(patch.estado_id)) || ticket.value.estado;
        }
    } catch {
        toast.error('No se pudo guardar');
    } finally {
        saving.value = false;
    }
};

const addMember = async (user) => {
    if (!canAssign.value || assignedIds.value.has(user.id)) return;
    const next = [...(ticket.value.asignados || []).map((a) => a.id), user.id];
    ticket.value.asignados = [...(ticket.value.asignados || []), { id: user.id, name: user.name, username: user.username }];
    memberQuery.value = '';
    await saveField({ user_ids: next });
    toast.success(`${user.name} asignado`);
};

const removeMember = async (userId) => {
    if (!canAssign.value) return;
    const next = (ticket.value.asignados || []).filter((a) => a.id !== userId).map((a) => a.id);
    const removed = ticket.value.asignados.find((a) => a.id === userId);
    ticket.value.asignados = ticket.value.asignados.filter((a) => a.id !== userId);
    await saveField({ user_ids: next });
    if (removed) toast.message(`${removed.name} quitado`);
};

const toggleEtiqueta = async (etiqueta) => {
    if (!canManage.value) return;
    const current = [...(ticket.value.etiquetas || [])];
    const exists = current.some((e) => e.id === etiqueta.id);
    const next = exists
        ? current.filter((e) => e.id !== etiqueta.id)
        : [...current, etiqueta];
    ticket.value.etiquetas = next;
    await saveField({ etiqueta_ids: next.map((e) => e.id) });
};

const clearImage = () => {
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
    pendingImage.value = null;
    imagePreview.value = null;
};

const setImageFile = (file) => {
    if (!file || !file.type.startsWith('image/')) return;
    if (file.size > 5 * 1024 * 1024) {
        toast.error('La imagen supera 5 MB');
        return;
    }
    clearImage();
    pendingImage.value = file;
    imagePreview.value = URL.createObjectURL(file);
};

const onPaste = (e) => {
    const items = e.clipboardData?.items;
    if (!items) return;
    for (const item of items) {
        if (item.type.startsWith('image/')) {
            e.preventDefault();
            setImageFile(item.getAsFile());
            toast.message('Captura lista para enviar');
            return;
        }
    }
};

const onFilePick = (e) => {
    const file = e.target.files?.[0];
    if (file) setImageFile(file);
    e.target.value = '';
};

const detectMention = () => {
    pingTyping();
    const el = composerRef.value;
    if (!el) return;
    const pos = el.selectionStart ?? comment.value.length;
    const before = comment.value.slice(0, pos);
    const match = before.match(/@([a-zA-Z0-9._-]*)$/);
    if (match) {
        mentionOpen.value = true;
        mentionQuery.value = match[1];
        mentionStart.value = pos - match[0].length;
        mentionIndex.value = 0;
    } else {
        mentionOpen.value = false;
        mentionQuery.value = '';
        mentionStart.value = -1;
    }
};

const insertMention = (user) => {
    if (!user?.username) return;
    const start = mentionStart.value;
    const el = composerRef.value;
    const pos = el?.selectionStart ?? comment.value.length;
    const before = comment.value.slice(0, start);
    const after = comment.value.slice(pos);
    comment.value = `${before}@${user.username} ${after}`;
    mentionOpen.value = false;
    nextTick(() => {
        const caret = before.length + user.username.length + 2;
        el?.focus();
        el?.setSelectionRange(caret, caret);
    });
};

const QUICK_EMOJIS = ['👍', '✅', '👀', '🔥', '🙏', '😊', '🎉', '⚠️'];

const insertEmoji = (emoji) => {
    const el = composerRef.value;
    const pos = el?.selectionStart ?? comment.value.length;
    const before = comment.value.slice(0, pos);
    const after = comment.value.slice(pos);
    comment.value = `${before}${emoji}${after}`;
    nextTick(() => {
        const caret = before.length + emoji.length;
        el?.focus();
        el?.setSelectionRange(caret, caret);
        detectMention();
    });
};

const onComposerKeydown = (e) => {
    if (mentionOpen.value && mentionCandidates.value.length) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            mentionIndex.value = (mentionIndex.value + 1) % mentionCandidates.value.length;
            return;
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            mentionIndex.value =
                (mentionIndex.value - 1 + mentionCandidates.value.length) % mentionCandidates.value.length;
            return;
        }
        if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            insertMention(mentionCandidates.value[mentionIndex.value]);
            return;
        }
        if (e.key === 'Escape') {
            mentionOpen.value = false;
            return;
        }
    }
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendComment();
    }
};

const renderMentions = (text) => {
    if (!text) return '';
    const escaped = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    return escaped.replace(
        /@([a-zA-Z0-9._-]+)/g,
        '<span class="rounded bg-[#579DFF]/25 px-1 font-semibold text-[#85B8FF]">@$1</span>',
    );
};

const sendComment = async () => {
    if (sending.value) return;
    const text = comment.value.trim();
    if (!text && !pendingImage.value) return;

    sending.value = true;
    const form = new FormData();
    if (text) form.append('contenido', text);
    if (pendingImage.value) form.append('imagen', pendingImage.value);

    const tempId = `tmp-${Date.now()}`;
    const optimistic = {
        id: tempId,
        contenido: text || null,
        imagen_url: imagePreview.value,
        user: { id: page.props.auth.user.id, name: page.props.auth.user.name },
        created_at: new Date().toISOString(),
        mine: true,
        pending: true,
    };
    ticket.value.comentarios.push(optimistic);
    comment.value = '';
    mentionOpen.value = false;
    const keptPreview = imagePreview.value;
    const keptFile = pendingImage.value;
    pendingImage.value = null;
    imagePreview.value = null;
    await scrollBottom({ force: true });

    try {
        const { data } = await axios.post(`/tickets/${props.ticketId}/comentarios`, form, {
            headers: {
                ...headers(),
                'Content-Type': 'multipart/form-data',
            },
        });
        const idx = ticket.value.comentarios.findIndex((c) => c.id === tempId);
        if (idx !== -1) ticket.value.comentarios.splice(idx, 1, data.comentario);
        if (data.asignados) {
            ticket.value.asignados = data.asignados;
            emit('updated', {
                id: Number(ticket.value.id),
                estado_id: ticket.value.estado_id,
                asignados: data.asignados,
            });
        }
        if (keptPreview) URL.revokeObjectURL(keptPreview);
    } catch {
        ticket.value.comentarios = ticket.value.comentarios.filter((c) => c.id !== tempId);
        comment.value = text;
        if (keptFile) {
            pendingImage.value = keptFile;
            imagePreview.value = keptPreview;
        }
        toast.error('No se pudo enviar el mensaje');
    } finally {
        sending.value = false;
    }
};

const remove = async () => {
    try {
        await ElMessageBox.confirm('Se eliminará el ticket', '¿Eliminar tarjeta?', {
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            type: 'warning',
        });
        await axios.delete(`/tickets/${props.ticketId}`, { headers: headers() });
        emit('deleted', props.ticketId);
        toast.success('Tarjeta eliminada');
    } catch {
        // cancelado o error
    }
};

const onKey = (e) => {
    if (e.key === 'Escape') {
        if (memberMenuOpen.value) {
            memberMenuOpen.value = false;
            return;
        }
        if (mentionOpen.value) {
            mentionOpen.value = false;
            return;
        }
        emit('close');
    }
};
</script>

<template>
    <div
        class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-black/70 p-3 backdrop-blur-sm sm:p-8"
        @click.self="emit('close')"
    >
        <div class="relative my-2 flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-[#1D2125] shadow-2xl ring-1 ring-white/10">
            <el-button
                circle
                text
                class="!absolute !right-3 !top-3 !z-10 !text-white/60"
                @click="emit('close')"
            >
                <el-icon :size="18"><Close /></el-icon>
            </el-button>

            <div v-if="loading" class="p-12 text-center text-sm text-white/50">Cargando tarjeta…</div>

            <div v-else-if="ticket" class="grid min-h-0 flex-1 grid-rows-[1fr] md:grid-cols-[1fr_240px]">
                <div class="flex min-h-0 flex-col p-5 md:p-6">
                    <div class="shrink-0 pr-8">
                        <el-input
                            v-model="ticket.titulo"
                            size="large"
                            class="!font-display title-input"
                            :readonly="!canManage"
                            @blur="canManage && saveField({ titulo: ticket.titulo })"
                        />
                        <p class="mt-1 px-1 text-xs text-white/45">
                            en lista
                            <span class="font-semibold text-white/70">{{ ticket.estado?.nombre }}</span>
                        </p>
                    </div>

                    <div class="mt-4 shrink-0">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-white/45">Descripción</h3>
                        <el-input
                            v-model="ticket.descripcion"
                            type="textarea"
                            :rows="3"
                            :readonly="!canManage"
                            @blur="canManage && saveField({ descripcion: ticket.descripcion })"
                        />
                    </div>

                    <div class="mt-4 flex min-h-0 flex-1 flex-col overflow-hidden rounded-[12px] border border-[#2a3340]">
                        <div class="flex shrink-0 items-center gap-2 border-b border-[#2a3340] bg-[#12171d] px-3 py-2">
                            <el-icon :size="14" class="text-[#8B9AAB]"><ChatDotRound /></el-icon>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-[#8B9AAB]">
                                Conversación
                            </h3>
                            <span class="text-[11px] font-normal normal-case tracking-normal text-[#5a6a7a]">
                                Estilo chat · visible para participantes
                            </span>
                        </div>

                        <div
                            ref="threadRef"
                            class="chat-thread min-h-[200px] flex-1 space-y-2 overflow-y-auto px-3 py-3"
                        >
                            <div
                                v-for="c in ticket.comentarios"
                                :key="c.id"
                                class="flex gap-2"
                                :class="[
                                    isMine(c) ? 'flex-row-reverse' : 'flex-row',
                                    c.pending ? 'opacity-60' : '',
                                ]"
                            >
                                <img
                                    v-if="!isMine(c)"
                                    :src="avatar(c.user?.name)"
                                    class="mt-1 h-7 w-7 shrink-0 rounded-full"
                                    alt=""
                                />
                                <div
                                    class="chat-bubble max-w-[78%] px-3 py-2 text-sm text-[#E8EEF4]"
                                    :class="isMine(c) ? 'chat-bubble--mine' : 'chat-bubble--theirs'"
                                >
                                    <p
                                        v-if="!isMine(c)"
                                        class="mb-0.5 text-[11px] font-semibold text-[#85B8FF]"
                                    >
                                        {{ c.user?.name }}
                                    </p>
                                    <p
                                        v-if="c.contenido"
                                        class="whitespace-pre-wrap leading-relaxed"
                                        v-html="renderMentions(c.contenido)"
                                    />
                                    <a
                                        v-if="c.imagen_url"
                                        :href="c.imagen_url"
                                        target="_blank"
                                        class="mt-1.5 block"
                                    >
                                        <img
                                            :src="c.imagen_url"
                                            alt="Captura"
                                            class="max-h-52 max-w-full rounded-lg object-contain"
                                        />
                                    </a>
                                    <p
                                        class="mt-1 text-right text-[10px] tabular-nums"
                                        :class="isMine(c) ? 'text-white/50' : 'text-[#8B9AAB]'"
                                    >
                                        {{ msgTime(c.created_at) }}
                                        <span v-if="c.pending"> · enviando</span>
                                    </p>
                                </div>
                            </div>
                            <p v-if="!ticket.comentarios.length" class="py-10 text-center text-sm text-[#8B9AAB]">
                                Sin mensajes. Escribe el primero…
                            </p>
                        </div>

                        <p v-if="typingLabel" class="h-5 px-3 text-xs italic text-[#85B8FF]">
                            {{ typingLabel }}
                        </p>
                        <p v-else class="h-2" />

                        <div v-if="canComment" class="relative shrink-0 border-t border-[#2a3340] bg-[#12171d] px-2 py-2">
                            <div
                                v-if="mentionOpen && mentionCandidates.length"
                                class="absolute bottom-full left-3 z-20 mb-2 max-h-48 w-64 overflow-y-auto rounded-[10px] border border-[#2a3340] bg-[#1a222c] py-1 shadow-xl"
                            >
                                <button
                                    v-for="(u, i) in mentionCandidates"
                                    :key="u.id"
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-white/10"
                                    :class="{ 'bg-[#579DFF]/20': i === mentionIndex }"
                                    @mousedown.prevent="insertMention(u)"
                                >
                                    <img :src="avatar(u.name)" class="h-6 w-6 rounded-full" alt="" />
                                    <span class="min-w-0 flex-1 truncate text-white/90">{{ u.name }}</span>
                                    <span class="text-xs text-white/40">@{{ u.username }}</span>
                                </button>
                            </div>

                            <div v-if="imagePreview" class="relative mb-2 inline-block px-1">
                                <img :src="imagePreview" class="max-h-24 rounded-[10px] border border-[#2a3340]" alt="Preview" />
                                <button
                                    type="button"
                                    class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-[#C4554D] text-white"
                                    @click="clearImage"
                                >
                                    <el-icon :size="12"><Close /></el-icon>
                                </button>
                            </div>

                            <div class="flex items-end gap-1.5 rounded-[24px] bg-[#1a222c] px-1.5 py-1 ring-1 ring-[#2a3340]">
                                <label
                                    class="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-full text-[#8B9AAB] transition hover:bg-white/5 hover:text-white"
                                    title="Adjuntar imagen"
                                >
                                    <el-icon :size="18"><Paperclip /></el-icon>
                                    <input type="file" accept="image/*" class="hidden" @change="onFilePick" />
                                </label>

                                <textarea
                                    ref="composerRef"
                                    v-model="comment"
                                    rows="1"
                                    class="max-h-28 min-h-[36px] flex-1 resize-none bg-transparent py-2 text-sm text-white placeholder:text-[#8B9AAB] focus:outline-none"
                                    placeholder="Mensaje"
                                    @paste="onPaste"
                                    @input="detectMention"
                                    @keydown="onComposerKeydown"
                                    @click="detectMention"
                                />

                                <el-dropdown trigger="click" @command="insertEmoji">
                                    <button
                                        type="button"
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg leading-none text-[#8B9AAB] transition hover:bg-white/5"
                                        title="Emoji"
                                    >
                                        😊
                                    </button>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <div class="grid grid-cols-4 gap-1 px-2 py-1">
                                                <el-dropdown-item
                                                    v-for="em in QUICK_EMOJIS"
                                                    :key="em"
                                                    :command="em"
                                                    class="!flex !justify-center !px-2 !text-lg"
                                                >
                                                    {{ em }}
                                                </el-dropdown-item>
                                            </div>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>

                                <button
                                    type="button"
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#3D7A5F] text-white transition hover:bg-[#4a9472] disabled:opacity-50"
                                    :disabled="sending || (!comment.trim() && !pendingImage)"
                                    title="Enviar"
                                    @click="sendComment"
                                >
                                    <el-icon :size="16"><Promotion /></el-icon>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="space-y-4 border-t border-white/10 bg-[#22272B] p-4 md:border-l md:border-t-0">
                    <div>
                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-white/40">Prioridad</p>
                        <el-select
                            v-model="ticket.prioridad"
                            class="w-full"
                            :disabled="!canManage"
                            @change="saveField({ prioridad: ticket.prioridad })"
                        >
                            <el-option
                                v-for="p in priorityCatalog"
                                :key="p.value"
                                :label="priorityLabel(p)"
                                :value="p.value"
                            />
                        </el-select>
                    </div>

                    <div>
                        <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-white/40">Etiquetas</p>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="et in labelCatalog"
                                :key="et.id"
                                type="button"
                                class="label-chip label-chip--filter"
                                :class="selectedEtiquetaIds.has(et.id) ? 'label-chip--active' : 'label-chip--muted'"
                                :style="chipVars(et.color)"
                                :disabled="!canManage"
                                @click="toggleEtiqueta(et)"
                            >
                                {{ et.nombre }}
                            </button>
                        </div>
                        <p v-if="!labelCatalog.length" class="text-xs text-white/35">Sin etiquetas en el catálogo</p>
                    </div>

                    <div>
                        <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-white/40">Estado</p>
                        <el-select
                            v-model="ticket.estado_id"
                            class="w-full"
                            :disabled="!canChangeStatus"
                            @change="saveField({ estado_id: ticket.estado_id })"
                        >
                            <el-option v-for="e in estados" :key="e.id" :label="e.nombre" :value="e.id" />
                        </el-select>
                    </div>
                    <div>
                        <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-white/40">Departamento</p>
                        <el-select
                            v-model="ticket.departamento_id"
                            class="w-full"
                            :disabled="!canManage"
                            @change="saveField({ departamento_id: ticket.departamento_id })"
                        >
                            <el-option v-for="d in departamentos" :key="d.id" :label="d.nombre" :value="d.id" />
                        </el-select>
                    </div>
                    <div>
                        <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-white/40">
                            <el-icon class="align-middle"><Calendar /></el-icon>
                            Entrega
                        </p>
                        <el-date-picker
                            v-model="ticket.fecha_entrega"
                            type="date"
                            value-format="YYYY-MM-DD"
                            class="!w-full"
                            :disabled="!canManage"
                            placeholder="Fecha"
                            @change="saveField({ fecha_entrega: ticket.fecha_entrega })"
                        />
                    </div>

                    <div>
                        <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-white/40">Miembros</p>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <div
                                v-for="m in ticket.asignados"
                                :key="m.id"
                                class="group relative"
                                :title="`${m.name} (@${m.username || '—'})`"
                            >
                                <el-avatar :size="32" :src="avatar(m.name)" />
                                <button
                                    v-if="canAssign"
                                    type="button"
                                    class="absolute -right-1 -top-1 hidden h-4 w-4 items-center justify-center rounded-full bg-[#1D2125] text-[10px] text-white ring-1 ring-white/30 group-hover:flex"
                                    @click="removeMember(m.id)"
                                >
                                    ×
                                </button>
                            </div>
                            <el-button
                                v-if="canAssign"
                                circle
                                class="!border-dashed"
                                title="Añadir miembro"
                                @click="memberMenuOpen = !memberMenuOpen"
                            >
                                <el-icon><User /></el-icon>
                            </el-button>
                        </div>
                        <p v-if="!ticket.asignados?.length" class="mt-2 text-xs text-white/35">
                            Nadie asignado aún
                        </p>

                        <div
                            v-if="memberMenuOpen && canAssign"
                            class="mt-2 overflow-hidden rounded-lg border border-white/10 bg-[#22272B]"
                        >
                            <div class="border-b border-white/10 p-2">
                                <el-input
                                    v-model="memberQuery"
                                    size="small"
                                    clearable
                                    placeholder="Buscar persona…"
                                    autofocus
                                />
                            </div>
                            <div class="max-h-40 overflow-y-auto py-1">
                                <button
                                    v-for="u in availableMembers"
                                    :key="u.id"
                                    type="button"
                                    class="flex w-full items-center gap-2 px-2 py-1.5 text-left text-sm hover:bg-white/10"
                                    @click="addMember(u)"
                                >
                                    <el-avatar :size="28" :src="avatar(u.name)" />
                                    <span class="min-w-0 flex-1 truncate text-white/90">{{ u.name }}</span>
                                    <el-icon class="text-white/40"><Plus /></el-icon>
                                </button>
                                <p v-if="!availableMembers.length" class="px-3 py-3 text-center text-xs text-white/40">
                                    No hay más personas
                                </p>
                            </div>
                        </div>
                    </div>

                    <p class="text-[10px] text-white/35">Creada {{ formatDate(ticket.created_at) }}</p>
                    <el-button
                        v-if="canManage"
                        type="danger"
                        plain
                        class="!w-full"
                        @click="remove"
                    >
                        <el-icon class="mr-1"><Delete /></el-icon>
                        Eliminar
                    </el-button>
                    <p v-if="saving" class="text-center text-[10px] text-white/40">Guardando…</p>
                </aside>
            </div>
        </div>
    </div>
</template>

<style scoped>
.title-input :deep(.el-input__wrapper) {
    box-shadow: none !important;
    background: transparent !important;
    padding-left: 0.25rem;
}
.title-input :deep(.el-input__inner) {
    font-size: 1.25rem;
    font-weight: 600;
}
</style>
