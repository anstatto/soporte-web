<script setup>
import { computed, inject, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { timeAgo } from '@/Composables/useDate';
import { usePermissions } from '@/Composables/usePermissions';
import FileViewer from '@/Components/Files/FileViewer.vue';
import AudioMessage from '@/Components/Chat/AudioMessage.vue';
import VoiceRecorder from '@/Components/Chat/VoiceRecorder.vue';

const props = defineProps({
    tickets: Object,
    filters: Object,
    departamentos: Array,
    estados: Array,
    activeChat: { type: Object, default: null },
    isSoporte: { type: Boolean, default: false },
    canCreate: { type: Boolean, default: false },
    mentionUsers: { type: Array, default: () => [] },
    tab: { type: String, default: 'incidencias' },
    canDm: { type: Boolean, default: false },
    dmConversations: { type: Array, default: () => [] },
    dmDirectory: { type: Array, default: () => [] },
    activeDm: { type: Object, default: null },
    inboxFilter: { type: String, default: 'all' },
});

const page = usePage();
const { can } = usePermissions();
const appCall = inject('appCall', null);
const livekitEnabled = computed(() => !!page.props.livekit?.enabled);
const allowVideo = computed(() => !!page.props.livekit?.allow_video);
const canUseCalls = computed(() => can('use calls') || !!page.props.auth?.user?.is_admin);

const form = reactive({
    titulo: props.filters?.titulo || '',
    departamento_id: props.filters?.departamento_id || '',
    estado_id: props.filters?.estado_id || '',
    sin_asignar: props.filters?.sin_asignar ?? '',
});

const chat = ref(props.activeChat ? JSON.parse(JSON.stringify(props.activeChat)) : null);
const dmChat = ref(props.activeDm ? JSON.parse(JSON.stringify(props.activeDm)) : null);
const comment = ref('');
const dmComment = ref('');
const sending = ref(false);
const dmSending = ref(false);
const pendingImage = ref(null);
const dmPendingFile = ref(null);
const imagePreview = ref(null);
const dmFilePreview = ref(null);
const threadRef = ref(null);
const dmThreadRef = ref(null);
const composerRef = ref(null);
const dmComposerRef = ref(null);
const typingUsers = ref([]);
const dmTypingUsers = ref([]);
const realtimeActive = ref(false);
const dmRealtimeActive = ref(false);
const mentionOpen = ref(false);
const mentionQuery = ref('');
const mentionIndex = ref(0);
const mentionStart = ref(-1);
const dmSearch = ref('');
const viewerOpen = ref(false);
const viewerFile = ref(null);

let pollTimer = null;
let typingTimers = {};
let dmTypingTimers = {};
let lastTypingSent = 0;
let lastDmTypingSent = 0;
let echoChannel = null;
let echoDmChannel = null;
let subscribedTicketId = null;
let subscribedDmId = null;

const cloneChat = (v) => (v ? JSON.parse(JSON.stringify(v)) : null);

const headers = () => ({
    'X-Requested-With': 'XMLHttpRequest',
    Accept: 'application/json',
});

const meId = computed(() => Number(page.props.auth?.user?.id));
const isPersonasTab = computed(() => props.tab === 'personas');
const isIncidenciasTab = computed(() => !isPersonasTab.value);
const showThreadMobile = computed(() => !!(isPersonasTab.value ? dmChat.value : chat.value));

const filterPayload = () => ({
    titulo: form.titulo || undefined,
    departamento_id: form.departamento_id || undefined,
    estado_id: form.estado_id || undefined,
    sin_asignar: form.sin_asignar || undefined,
    tab: props.tab || 'incidencias',
    inbox: props.inboxFilter && props.inboxFilter !== 'all' ? props.inboxFilter : undefined,
});

const matchesInbox = (row) => {
    const meta = row.meta || {};
    const filter = props.inboxFilter || 'all';
    if (filter === 'unread') return Number(row.unread || 0) > 0 || !!meta.marked_unread;
    if (filter === 'starred') return !!meta.starred;
    if (filter === 'archived') return !!meta.archived;
    return !meta.archived;
};

const sortInbox = (rows) =>
    [...rows].sort((a, b) => {
        const ap = a.meta?.pinned ? 1 : 0;
        const bp = b.meta?.pinned ? 1 : 0;
        if (ap !== bp) return bp - ap;
        const at = new Date(a.last_activity || a.last_message?.created_at || a.updated_at || a.created_at || 0).getTime();
        const bt = new Date(b.last_activity || b.last_message?.created_at || b.updated_at || b.created_at || 0).getTime();
        return bt - at;
    });

const ticketRows = computed(() => sortInbox((props.tickets?.data || []).filter(matchesInbox)));

const filteredDmConversations = computed(() => {
    const q = dmSearch.value.toLowerCase().trim();
    const rows = (props.dmConversations || []).filter((c) => {
        if (!matchesInbox(c)) return false;
        if (!q) return true;
        const peer = c.peer || {};
        return (
            (peer.name || '').toLowerCase().includes(q) ||
            (peer.username || '').toLowerCase().includes(q)
        );
    });
    return sortInbox(rows);
});

const setInboxFilter = (inbox) => {
    router.get(
        '/tickets',
        { ...filterPayload(), inbox: inbox === 'all' ? undefined : inbox },
        { preserveState: true, replace: true, preserveScroll: true },
    );
};

const applyFilters = useDebounceFn(() => {
    if (isPersonasTab.value) return;
    const payload = { ...filterPayload(), chat: chat.value?.id || undefined };
    router.get('/tickets', payload, { preserveState: true, replace: true, preserveScroll: true });
}, 350);

const chatStateAction = async (chatType, chatId, action) => {
    try {
        const { data } = await axios.post(
            '/chat-state',
            { chat_type: chatType, chat_id: chatId, action },
            { headers: headers() },
        );
        const only = chatType === 'ticket' ? ['tickets', 'activeChat'] : ['dmConversations', 'activeDm'];
        router.reload({ only, preserveScroll: true, preserveState: true });
        const labels = {
            unread: 'Marcado como no leído',
            read: 'Marcado como leído',
            star: 'Guardado para más tarde',
            unstar: 'Quitado de más tarde',
            pin: 'Chat fijado',
            unpin: 'Chat desfijado',
            mute: 'Notificaciones silenciadas',
            unmute: 'Notificaciones activadas',
            archive: 'Chat archivado',
            unarchive: 'Chat desarchivado',
        };
        if (labels[action]) toast.success(labels[action]);
        return data?.meta;
    } catch {
        toast.error('No se pudo actualizar el chat');
        return null;
    }
};

const onRowMenu = (chatType, row, command) => {
    const meta = row.meta || {};
    const map = {
        unread: meta.marked_unread || Number(row.unread || 0) > 0 ? 'read' : 'unread',
        star: meta.starred ? 'unstar' : 'star',
        pin: meta.pinned ? 'unpin' : 'pin',
        mute: meta.muted ? 'unmute' : 'mute',
        archive: meta.archived ? 'unarchive' : 'archive',
    };
    const action = map[command] || command;
    chatStateAction(chatType, row.id, action);
};

const dmStartUsers = computed(() => {
    const openPeerIds = new Set(
        (props.dmConversations || []).map((c) => c.peer?.id).filter(Boolean),
    );
    const q = dmSearch.value.toLowerCase().trim();
    return (props.dmDirectory || []).filter((u) => {
        if (openPeerIds.has(u.id)) return false;
        if (!q) return true;
        return (
            (u.name || '').toLowerCase().includes(q) ||
            (u.username || '').toLowerCase().includes(q)
        );
    });
});

const dmTypingLabel = computed(() => {
    const names = dmTypingUsers.value.map((u) => u.name).filter(Boolean);
    if (!names.length) return '';
    // DM 1:1 → estilo WhatsApp
    return 'escribiendo';
});

const typingLabel = computed(() => {
    const names = typingUsers.value.map((u) => u.name).filter(Boolean);
    if (!names.length) return '';
    if (names.length === 1) return `${names[0]} está escribiendo`;
    if (names.length === 2) return `${names[0]} y ${names[1]} están escribiendo`;
    return 'Varias personas están escribiendo';
});

const typingHeaderSubtitle = computed(() => {
    if (isPersonasTab.value) {
        return dmTypingLabel.value ? 'escribiendo' : null;
    }
    return typingLabel.value || null;
});

watch(form, applyFilters, { deep: true });

watch(
    () => props.activeChat,
    (v) => {
        if (isPersonasTab.value) return;
        const nextId = v?.id ?? null;
        if (subscribedTicketId && subscribedTicketId !== nextId) {
            unsubscribeRealtime();
        }
        chat.value = cloneChat(v);
        if (v) {
            nextTick(() => scrollBottom({ force: true }));
            subscribeRealtime(v.id);
            axios.post('/notifications/mark-ticket-read', { ticket_id: v.id }, { headers: headers() }).catch(() => {});
        } else {
            unsubscribeRealtime();
        }
    },
);

watch(
    () => props.activeDm,
    (v) => {
        if (!isPersonasTab.value) return;
        const nextId = v?.id ?? null;
        if (subscribedDmId && subscribedDmId !== nextId) {
            unsubscribeDmRealtime();
        }
        dmChat.value = cloneChat(v);
        if (v) {
            nextTick(() => scrollDmBottom({ force: true }));
            subscribeDmRealtime(v.id);
        } else {
            unsubscribeDmRealtime();
        }
    },
);

watch(isPersonasTab, (personas) => {
    if (personas) {
        unsubscribeRealtime();
        typingUsers.value = [];
    } else {
        unsubscribeDmRealtime();
        dmTypingUsers.value = [];
    }
});

watch(
    () => typingUsers.value.length,
    (n, prev) => {
        if (n > (prev || 0)) scrollBottom({ force: true });
    },
);

watch(
    () => dmTypingUsers.value.length,
    (n, prev) => {
        if (n > (prev || 0)) scrollDmBottom({ force: true });
    },
);

const avatar = (name) =>
    `https://ui-avatars.com/api/?name=${encodeURIComponent(name || '?')}&background=579DFF&color=fff`;

const isMine = (c) =>
    c.mine === true || Number(c.user_id ?? c.user?.id) === meId.value;

/** Inserta separador “Mensajes nuevos” antes del primer mensaje ajeno no leído */
const withUnreadDivider = (messages, thread) => {
    const list = messages || [];
    if (!thread?.had_unread || !list.length) {
        return list.map((m) => ({ kind: 'msg', msg: m }));
    }
    const after = thread.highlight_after ? new Date(thread.highlight_after).getTime() : null;
    let dividerAt = -1;
    for (let i = 0; i < list.length; i++) {
        const m = list[i];
        if (isMine(m)) continue;
        const t = m.created_at ? new Date(m.created_at).getTime() : 0;
        if (after == null || t > after) {
            dividerAt = i;
            break;
        }
    }
    if (dividerAt < 0) {
        return list.map((m) => ({ kind: 'msg', msg: m }));
    }
    const out = [];
    list.forEach((m, i) => {
        if (i === dividerAt) out.push({ kind: 'divider', id: 'unread-divider' });
        out.push({ kind: 'msg', msg: m });
    });
    return out;
};

const ticketThreadItems = computed(() => withUnreadDivider(chat.value?.comentarios, chat.value));
const dmThreadItems = computed(() => withUnreadDivider(dmChat.value?.mensajes, dmChat.value));

const msgTime = (iso) => {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
    } catch {
        return timeAgo(iso);
    }
};

const listTime = (iso) => {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        const now = new Date();
        if (d.toDateString() === now.toDateString()) {
            return d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
        }
        return d.toLocaleDateString('es', { day: '2-digit', month: 'short' });
    } catch {
        return timeAgo(iso);
    }
};

const previewText = (t) => {
    if (t.last_message?.preview) {
        const who = t.last_message.by && Number(t.peer?.id) !== meId.value ? `${t.last_message.by}: ` : '';
        return who + t.last_message.preview;
    }
    return t.descripcion || 'Sin mensajes';
};

const dmPreviewText = (c) => {
    if (c.last_message?.preview) {
        const who = c.last_message.by && Number(c.peer?.id) !== meId.value ? `${c.last_message.by}: ` : '';
        return who + c.last_message.preview;
    }
    return 'Sin mensajes';
};

const mentionCandidates = computed(() => {
    const q = mentionQuery.value.toLowerCase();
    return (props.mentionUsers || [])
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
    const nearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 100;
    if (force || nearBottom) el.scrollTop = el.scrollHeight;
};

const scrollDmBottom = async ({ force = false } = {}) => {
    await nextTick();
    const el = dmThreadRef.value;
    if (!el) return;
    const nearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 100;
    if (force || nearBottom) el.scrollTop = el.scrollHeight;
};

const switchTab = (tab) => {
    router.get('/tickets', { ...filterPayload(), tab }, { preserveState: true, replace: true, preserveScroll: true });
};

const openChat = (id) => {
    router.get(
        '/tickets',
        { ...filterPayload(), tab: 'incidencias', chat: id },
        { preserveState: true, replace: true, preserveScroll: true },
    );
};

const openDm = (id) => {
    router.get(
        '/tickets',
        { ...filterPayload(), tab: 'personas', dm: id },
        { preserveState: true, replace: true, preserveScroll: true },
    );
};

const openDmWithUser = (userId) => {
    router.post('/chats/open', { user_id: userId }, { preserveScroll: true });
};

const closeChatMobile = () => {
    router.get('/tickets', filterPayload(), { preserveState: true, replace: true, preserveScroll: true });
};

const openCreate = () => window.dispatchEvent(new CustomEvent('soporte:open-create-ticket'));

const openBoard = () => {
    if (!chat.value) return;
    router.visit(`/tickets/board?card=${chat.value.id}`);
};

const ticketCallTargets = computed(() =>
    (chat.value?.participantes || []).filter((p) => Number(p.id) !== meId.value),
);

const startDmCall = (video = false) => {
    if (!livekitEnabled.value) {
        toast.error('Llamadas desactivadas. Configúralas en Ajustes (LiveKit Cloud gratis).');
        return;
    }
    if (video && !allowVideo.value) {
        toast.message('Video desactivado (modo económico). Usa audio o actívalo en Ajustes.');
        video = false;
    }
    const peerId = dmChat.value?.peer?.id;
    if (!peerId || !appCall) return;
    appCall.start({
        calleeId: peerId,
        video,
        context: { type: 'dm', id: dmChat.value.id },
    });
};

const startTicketCall = (userId, video = false) => {
    if (!livekitEnabled.value) {
        toast.error('Llamadas desactivadas. Configúralas en Ajustes (LiveKit Cloud gratis).');
        return;
    }
    if (video && !allowVideo.value) {
        toast.message('Video desactivado (modo económico). Usa audio o actívalo en Ajustes.');
        video = false;
    }
    if (!chat.value || !appCall) return;
    appCall.start({
        calleeId: userId,
        video,
        context: { type: 'ticket', id: chat.value.id },
    });
};

const appendComment = async (raw, { forceScroll = false } = {}) => {
    if (!chat.value?.comentarios) return;
    const incoming = {
        ...raw,
        mine: Number(raw.user_id ?? raw.user?.id) === meId.value,
    };
    if (chat.value.comentarios.some((c) => c.id === incoming.id)) return;
    chat.value.comentarios.push(incoming);
    await scrollBottom({ force: forceScroll });
};

const clearTypingUser = (userId) => {
    typingUsers.value = typingUsers.value.filter((u) => u.id !== userId);
    if (typingTimers[userId]) {
        clearTimeout(typingTimers[userId]);
        delete typingTimers[userId];
    }
};

const setTypingUser = (user) => {
    if (!user?.id || Number(user.id) === meId.value) return;
    const rest = typingUsers.value.filter((u) => u.id !== user.id);
    typingUsers.value = [...rest, { id: user.id, name: user.name }];
    if (typingTimers[user.id]) clearTimeout(typingTimers[user.id]);
    typingTimers[user.id] = setTimeout(() => clearTypingUser(user.id), 4500);
};

const clearDmTypingUser = (userId) => {
    dmTypingUsers.value = dmTypingUsers.value.filter((u) => u.id !== userId);
    if (dmTypingTimers[userId]) {
        clearTimeout(dmTypingTimers[userId]);
        delete dmTypingTimers[userId];
    }
};

const setDmTypingUser = (user) => {
    if (!user?.id || Number(user.id) === meId.value) return;
    const rest = dmTypingUsers.value.filter((u) => u.id !== user.id);
    dmTypingUsers.value = [...rest, { id: user.id, name: user.name }];
    if (dmTypingTimers[user.id]) clearTimeout(dmTypingTimers[user.id]);
    dmTypingTimers[user.id] = setTimeout(() => clearDmTypingUser(user.id), 4500);
};

const subscribeRealtime = (ticketId) => {
    unsubscribeRealtime();
    if (!window.Echo || !ticketId) {
        realtimeActive.value = false;
        return;
    }
    try {
        echoChannel = window.Echo.private(`ticket.${ticketId}`);
        echoChannel
            .listen('.comentario.creado', async (payload) => {
                await appendComment(payload.comentario, { forceScroll: true });
                clearTypingUser(payload.comentario?.user_id ?? payload.comentario?.user?.id);
            })
            .listen('.usuario.escribiendo', (payload) => {
                setTypingUser(payload.user);
            });
        subscribedTicketId = ticketId;
        realtimeActive.value = true;
    } catch {
        realtimeActive.value = false;
    }
};

const unsubscribeRealtime = () => {
    if (window.Echo && subscribedTicketId) {
        window.Echo.leave(`ticket.${subscribedTicketId}`);
    }
    echoChannel = null;
    subscribedTicketId = null;
    realtimeActive.value = false;
};

const appendDmMessage = async (raw, { forceScroll = false } = {}) => {
    if (!dmChat.value?.mensajes) return;
    const incoming = {
        ...raw,
        mine: raw.mine === true || Number(raw.user_id ?? raw.user?.id) === meId.value,
    };
    if (dmChat.value.mensajes.some((m) => m.id === incoming.id)) return;
    dmChat.value.mensajes.push(incoming);
    await scrollDmBottom({ force: forceScroll });
};

const subscribeDmRealtime = (dmId) => {
    unsubscribeDmRealtime();
    if (!window.Echo || !dmId) {
        dmRealtimeActive.value = false;
        return;
    }
    try {
        echoDmChannel = window.Echo.private(`conversacion.${dmId}`);
        echoDmChannel
            .listen('.mensaje.creado', async (payload) => {
                await appendDmMessage(payload.mensaje, { forceScroll: true });
                clearDmTypingUser(payload.mensaje?.user_id ?? payload.mensaje?.user?.id);
            })
            .listen('.usuario.escribiendo', (payload) => {
                setDmTypingUser(payload.user);
            });
        subscribedDmId = dmId;
        dmRealtimeActive.value = true;
    } catch {
        dmRealtimeActive.value = false;
    }
};

const unsubscribeDmRealtime = () => {
    if (window.Echo && subscribedDmId) {
        window.Echo.leave(`conversacion.${subscribedDmId}`);
    }
    echoDmChannel = null;
    subscribedDmId = null;
    dmRealtimeActive.value = false;
};

const pollDmNew = async () => {
    if (!dmChat.value) return;
    const after = dmChat.value.mensajes?.at(-1)?.id || 0;
    if (typeof after === 'string') return;
    try {
        const { data } = await axios.get(`/chats/${dmChat.value.id}/poll`, {
            params: { after },
            headers: headers(),
        });
        if (data.mensajes?.length) {
            for (const m of data.mensajes) await appendDmMessage(m);
        }
        if (!dmRealtimeActive.value) dmTypingUsers.value = data.typing || [];
    } catch {
        /* silent */
    }
};

const pingDmTyping = () => {
    if (!dmChat.value) return;
    const now = Date.now();
    if (now - lastDmTypingSent < 1500) return;
    lastDmTypingSent = now;
    axios.post(`/chats/${dmChat.value.id}/typing`, {}, { headers: headers() }).catch(() => {});
};

const pollNew = async () => {
    if (isPersonasTab.value) {
        await pollDmNew();
        return;
    }
    if (!chat.value) return;
    const after = chat.value.comentarios?.at(-1)?.id || 0;
    if (typeof after === 'string') return;
    try {
        const { data } = await axios.get(`/tickets/${chat.value.id}/comentarios/poll`, {
            params: { after },
            headers: headers(),
        });
        if (data.comentarios?.length) {
            for (const c of data.comentarios) await appendComment(c);
        }
        if (!realtimeActive.value) typingUsers.value = data.typing || [];
    } catch {
        /* silent */
    }
};

const pingTyping = () => {
    if (!chat.value) return;
    const now = Date.now();
    if (now - lastTypingSent < 1500) return;
    lastTypingSent = now;
    axios.post(`/tickets/${chat.value.id}/typing`, {}, { headers: headers() }).catch(() => {});
};

const renderMentions = (text) => {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/@([a-zA-Z0-9._-]+)/g, '<span class="text-[#85B8FF] font-medium">@$1</span>');
};

const detectMention = () => {
    pingTyping();
    const el = composerRef.value;
    if (!el) return;
    const pos = el.selectionStart ?? comment.value.length;
    const before = comment.value.slice(0, pos);
    const m = before.match(/@([a-zA-Z0-9._-]*)$/);
    if (m) {
        mentionOpen.value = true;
        mentionQuery.value = m[1] || '';
        mentionStart.value = pos - m[0].length;
        mentionIndex.value = 0;
    } else {
        mentionOpen.value = false;
        mentionStart.value = -1;
    }
};

const insertMention = (u) => {
    if (mentionStart.value < 0) return;
    const el = composerRef.value;
    const pos = el?.selectionStart ?? comment.value.length;
    const before = comment.value.slice(0, mentionStart.value);
    const after = comment.value.slice(pos);
    comment.value = `${before}@${u.username} ${after}`;
    mentionOpen.value = false;
    nextTick(() => {
        const p = before.length + u.username.length + 2;
        el?.focus();
        el?.setSelectionRange(p, p);
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

const openViewer = (file) => {
    if (!file?.url) return;
    viewerFile.value = file;
    viewerOpen.value = true;
};

const formatSize = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const kindLabel = (kind) => ({
    image: 'IMG',
    pdf: 'PDF',
    word: 'DOC',
    audio: 'AUDIO',
    other: 'FILE',
}[kind] || 'FILE');

const isAudioFile = (file) => {
    if (!file) return false;
    const t = (file.type || '').toLowerCase();
    const n = (file.name || '').toLowerCase();
    return t.startsWith('audio/') || t === 'video/webm' || /\.(webm|ogg|oga|mp3|m4a|wav|aac|opus)$/.test(n);
};

const onVoiceRecorded = async (file) => {
    clearImage();
    pendingImage.value = file;
    imagePreview.value = null;
    await sendComment();
};

const onDmVoiceRecorded = async (file) => {
    clearDmFile();
    dmPendingFile.value = file;
    dmFilePreview.value = null;
    await sendDmMessage();
};

const onFilePick = (e) => {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    pendingImage.value = file;
    if (file.type.startsWith('image/')) {
        imagePreview.value = URL.createObjectURL(file);
    } else {
        imagePreview.value = null;
    }
};

const clearImage = () => {
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
    pendingImage.value = null;
    imagePreview.value = null;
};

const onPaste = (e) => {
    const item = [...(e.clipboardData?.items || [])].find((i) => i.type.startsWith('image/'));
    if (!item) return;
    const file = item.getAsFile();
    if (!file) return;
    e.preventDefault();
    pendingImage.value = file;
    imagePreview.value = URL.createObjectURL(file);
};

const sendComment = async () => {
    if (sending.value || !chat.value?.canComment) return;
    const text = comment.value.trim();
    if (!text && !pendingImage.value) return;

    sending.value = true;
    const fd = new FormData();
    if (text) fd.append('contenido', text);
    if (pendingImage.value) {
        const f = pendingImage.value;
        if (f.type.startsWith('image/')) fd.append('imagen', f);
        else fd.append('archivo', f);
    }

    const tempId = `tmp-${Date.now()}`;
    const optimisticAdjunto = pendingImage.value && !pendingImage.value.type.startsWith('image/')
        ? {
            id: tempId,
            nombre: pendingImage.value.name,
            kind: isAudioFile(pendingImage.value) ? 'audio' : (pendingImage.value.name.toLowerCase().endsWith('.pdf') ? 'pdf' : 'word'),
            url: isAudioFile(pendingImage.value) ? URL.createObjectURL(pendingImage.value) : null,
            pending: true,
        }
        : null;

    chat.value.comentarios.push({
        id: tempId,
        contenido: text || null,
        imagen_url: imagePreview.value,
        adjunto: optimisticAdjunto,
        user: { id: page.props.auth.user.id, name: page.props.auth.user.name },
        created_at: new Date().toISOString(),
        mine: true,
        pending: true,
    });
    comment.value = '';
    mentionOpen.value = false;
    const keptFile = pendingImage.value;
    pendingImage.value = null;
    imagePreview.value = null;
    await scrollBottom({ force: true });

    try {
        const { data } = await axios.post(`/tickets/${chat.value.id}/comentarios`, fd, {
            headers: { ...headers(), 'Content-Type': 'multipart/form-data' },
        });
        const idx = chat.value.comentarios.findIndex((c) => c.id === tempId);
        if (idx >= 0) {
            chat.value.comentarios[idx] = { ...data.comentario, mine: true, pending: false };
        } else {
            await appendComment({ ...data.comentario, mine: true });
        }
        if (data.asignados) chat.value.asignados = data.asignados;
        if (data.adjunto) {
            chat.value.adjuntos = [...(chat.value.adjuntos || []), data.adjunto];
        }
        router.reload({ only: ['tickets'], preserveScroll: true, preserveState: true });
    } catch {
        chat.value.comentarios = chat.value.comentarios.filter((c) => c.id !== tempId);
        comment.value = text;
        if (keptFile) {
            pendingImage.value = keptFile;
            if (keptFile.type.startsWith('image/')) {
                imagePreview.value = URL.createObjectURL(keptFile);
            }
        }
        toast.error('No se pudo enviar el mensaje');
    } finally {
        sending.value = false;
    }
};

const onDmFilePick = (e) => {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    dmPendingFile.value = file;
    if (file.type.startsWith('image/')) {
        dmFilePreview.value = URL.createObjectURL(file);
    } else {
        dmFilePreview.value = null;
    }
};

const clearDmFile = () => {
    if (dmFilePreview.value) URL.revokeObjectURL(dmFilePreview.value);
    dmPendingFile.value = null;
    dmFilePreview.value = null;
};

const onDmPaste = (e) => {
    const item = [...(e.clipboardData?.items || [])].find((i) => i.type.startsWith('image/'));
    if (!item) return;
    const file = item.getAsFile();
    if (!file) return;
    e.preventDefault();
    dmPendingFile.value = file;
    dmFilePreview.value = URL.createObjectURL(file);
};

const sendDmMessage = async () => {
    if (dmSending.value || !dmChat.value?.canMessage) return;
    const text = dmComment.value.trim();
    if (!text && !dmPendingFile.value) return;

    dmSending.value = true;
    const fd = new FormData();
    if (text) fd.append('contenido', text);
    if (dmPendingFile.value) fd.append('archivo', dmPendingFile.value);

    const tempId = `tmp-${Date.now()}`;
    const file = dmPendingFile.value;
    const isImage = file?.type.startsWith('image/');
    const isAudio = isAudioFile(file);

    dmChat.value.mensajes.push({
        id: tempId,
        contenido: text || null,
        url: isImage || isAudio ? (dmFilePreview.value || (file ? URL.createObjectURL(file) : null)) : null,
        nombre: file?.name || null,
        kind: isImage ? 'image' : isAudio ? 'audio' : file ? (file.name.toLowerCase().endsWith('.pdf') ? 'pdf' : 'word') : null,
        size: file?.size || null,
        user: { id: page.props.auth.user.id, name: page.props.auth.user.name },
        user_id: page.props.auth.user.id,
        created_at: new Date().toISOString(),
        mine: true,
        pending: true,
    });
    dmComment.value = '';
    const keptFile = dmPendingFile.value;
    dmPendingFile.value = null;
    dmFilePreview.value = null;
    await scrollDmBottom({ force: true });

    try {
        const { data } = await axios.post(`/chats/${dmChat.value.id}/mensajes`, fd, {
            headers: { ...headers(), 'Content-Type': 'multipart/form-data' },
        });
        const idx = dmChat.value.mensajes.findIndex((m) => m.id === tempId);
        if (idx >= 0) {
            dmChat.value.mensajes[idx] = { ...data.mensaje, mine: true, pending: false };
        } else {
            await appendDmMessage({ ...data.mensaje, mine: true });
        }
        router.reload({ only: ['dmConversations'], preserveScroll: true, preserveState: true });
    } catch {
        dmChat.value.mensajes = dmChat.value.mensajes.filter((m) => m.id !== tempId);
        dmComment.value = text;
        if (keptFile) {
            dmPendingFile.value = keptFile;
            if (keptFile.type.startsWith('image/')) {
                dmFilePreview.value = URL.createObjectURL(keptFile);
            }
        }
        toast.error('No se pudo enviar el mensaje');
    } finally {
        dmSending.value = false;
    }
};

const onDmComposerKeydown = (e) => {
    pingDmTyping();
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendDmMessage();
    }
};

onMounted(() => {
    if (chat.value && isIncidenciasTab.value) {
        scrollBottom({ force: true });
        subscribeRealtime(chat.value.id);
        axios
            .post('/notifications/mark-ticket-read', { ticket_id: chat.value.id }, { headers: headers() })
            .catch(() => {});
    }
    if (dmChat.value && isPersonasTab.value) {
        scrollDmBottom({ force: true });
        subscribeDmRealtime(dmChat.value.id);
    }
    pollTimer = setInterval(pollNew, 12000);
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
    Object.values(typingTimers).forEach(clearTimeout);
    Object.values(dmTypingTimers).forEach(clearTimeout);
    typingTimers = {};
    dmTypingTimers = {};
    unsubscribeRealtime();
    unsubscribeDmRealtime();
    clearImage();
    clearDmFile();
});
</script>

<template>
    <Head :title="isSoporte ? 'Bandeja' : 'Mis chats'" />

    <div class="flex h-full min-h-0 flex-1 overflow-hidden bg-[#0f1419]">
        <!-- Lista de conversaciones -->
        <aside
            class="flex w-full shrink-0 flex-col border-r border-[#2a3340] bg-[#12171d] md:w-[340px] lg:w-[360px]"
            :class="showThreadMobile ? 'hidden md:flex' : 'flex'"
        >
            <div class="shrink-0 border-b border-[#2a3340] px-3 py-3">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <div>
                        <h2 class="font-display text-lg font-semibold text-white">
                            {{ isSoporte ? 'Bandeja' : 'Mis chats' }}
                        </h2>
                        <p class="text-[11px] text-[#8B9AAB]">
                            {{
                                isPersonasTab
                                    ? 'Mensajes directos del equipo'
                                    : isSoporte
                                      ? 'Conversaciones del área'
                                      : 'Tus incidencias con soporte'
                            }}
                        </p>
                    </div>
                    <el-button
                        v-if="isIncidenciasTab && (canCreate || can('create tickets'))"
                        type="primary"
                        size="small"
                        circle
                        title="Reportar"
                        @click="openCreate"
                    >
                        <el-icon><Plus /></el-icon>
                    </el-button>
                </div>

                <div v-if="canDm" class="mb-2 flex gap-1 rounded-lg bg-[#1a222c] p-0.5">
                    <button
                        type="button"
                        class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors"
                        :class="
                            isIncidenciasTab
                                ? 'bg-[#579DFF] text-white'
                                : 'text-[#8B9AAB] hover:text-white'
                        "
                        @click="switchTab('incidencias')"
                    >
                        Incidencias
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors"
                        :class="
                            isPersonasTab
                                ? 'bg-[#579DFF] text-white'
                                : 'text-[#8B9AAB] hover:text-white'
                        "
                        @click="switchTab('personas')"
                    >
                        Personas
                    </button>
                </div>

                <el-input
                    v-if="isIncidenciasTab"
                    v-model="form.titulo"
                    clearable
                    size="small"
                    placeholder="Buscar conversación…"
                    class="mb-2"
                >
                    <template #prefix>
                        <el-icon><Search /></el-icon>
                    </template>
                </el-input>

                <el-input
                    v-else
                    v-model="dmSearch"
                    clearable
                    size="small"
                    placeholder="Buscar persona…"
                    class="mb-2"
                >
                    <template #prefix>
                        <el-icon><Search /></el-icon>
                    </template>
                </el-input>

                <div class="mb-2 flex flex-wrap gap-1">
                    <button
                        v-for="opt in [
                            { id: 'all', label: 'Todos', active: 'bg-[#579DFF] text-white' },
                            { id: 'unread', label: 'No leídos', active: 'bg-[#25D366] text-[#052e16]' },
                            { id: 'starred', label: 'Más tarde', active: 'bg-[#F5C518] text-[#1a1500]' },
                            { id: 'archived', label: 'Archivados', active: 'bg-[#8B9AAB] text-[#0f1419]' },
                        ]"
                        :key="opt.id"
                        type="button"
                        class="rounded-full px-2.5 py-1 text-[11px] font-semibold transition-colors"
                        :class="
                            (inboxFilter || 'all') === opt.id
                                ? opt.active
                                : 'bg-[#1a222c] text-[#8B9AAB] hover:text-white'
                        "
                        @click="setInboxFilter(opt.id)"
                    >
                        {{ opt.label }}
                    </button>
                </div>

                <div v-if="isIncidenciasTab && isSoporte" class="flex flex-wrap gap-1.5">
                    <el-select v-model="form.estado_id" clearable size="small" placeholder="Estado" class="!w-[110px]">
                        <el-option
                            v-for="e in estados"
                            :key="e.id"
                            :label="e.nombre"
                            :value="String(e.id)"
                        />
                    </el-select>
                    <el-select
                        v-model="form.sin_asignar"
                        clearable
                        size="small"
                        placeholder="Asignación"
                        class="!w-[120px]"
                    >
                        <el-option label="Sin asignar" value="1" />
                    </el-select>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <template v-if="isIncidenciasTab">
                <div
                    v-for="t in ticketRows"
                    :key="t.id"
                    class="group relative flex w-full items-start gap-3 border-b border-[#2a3340]/60 px-3 py-3 text-left transition-colors hover:bg-white/5"
                    :class="[
                        chat?.id === t.id ? 'inbox-row--active' : '',
                        t.unread ? 'inbox-row--unread' : '',
                    ]"
                >
                    <button type="button" class="flex min-w-0 flex-1 items-start gap-3 text-left" @click="openChat(t.id)">
                        <div class="relative shrink-0">
                            <el-avatar
                                :size="44"
                                :src="avatar(t.peer?.name || t.user?.name)"
                                :class="t.unread ? 'avatar-ring--unread' : ''"
                            />
                            <span
                                v-if="t.meta?.pinned"
                                class="absolute -bottom-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-[#22272B] text-[#85B8FF]"
                                title="Fijado"
                            >
                                <el-icon :size="10"><Top /></el-icon>
                            </span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline justify-between gap-2">
                                <p
                                    class="truncate text-sm"
                                    :class="t.unread ? 'font-bold text-white' : 'font-semibold text-[#E8EEF4]'"
                                >
                                    {{ t.peer?.name || t.user?.name || 'Chat' }}
                                </p>
                                <span
                                    class="shrink-0 text-[10px] tabular-nums"
                                    :class="t.unread ? 'font-bold text-[#25D366]' : 'text-[#8B9AAB]'"
                                >
                                    {{ listTime(t.last_activity || t.created_at) }}
                                </span>
                            </div>
                            <p
                                class="truncate text-xs"
                                :class="t.unread ? 'font-semibold text-[#85B8FF]' : 'text-[#85B8FF]/80'"
                            >
                                {{ t.titulo }}
                            </p>
                            <div class="mt-0.5 flex items-center gap-2">
                                <p
                                    class="min-w-0 flex-1 truncate text-xs"
                                    :class="t.unread ? 'font-semibold text-white' : 'text-[#8B9AAB]'"
                                >
                                    {{ previewText(t) }}
                                </p>
                                <span
                                    v-if="t.unread"
                                    class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-[#25D366] px-1.5 text-[10px] font-extrabold text-[#052e16] shadow-[0_0_8px_rgba(37,211,102,0.45)]"
                                >
                                    {{ t.unread > 99 ? '99+' : t.unread }}
                                </span>
                                <el-icon v-else-if="t.meta?.muted" class="shrink-0 text-[#8B9AAB]" :size="14"><MuteNotification /></el-icon>
                                <el-icon v-else-if="t.meta?.starred" class="shrink-0 text-[#F5C518]" :size="14"><StarFilled /></el-icon>
                            </div>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1">
                                <span
                                    v-if="t.estado"
                                    class="status-pill"
                                    :style="{ backgroundColor: t.estado.color || '#579DFF' }"
                                >
                                    {{ t.estado.nombre }}
                                </span>
                                <span
                                    v-if="t.prioridad"
                                    class="priority-pill"
                                    :style="{
                                        color: t.prioridad_color,
                                        borderColor: t.prioridad_color,
                                        backgroundColor: `${t.prioridad_color}22`,
                                    }"
                                >
                                    {{ t.prioridad_label || t.prioridad }}
                                </span>
                                <el-tag v-if="t.sin_asignar && isSoporte" size="small" type="warning" effect="dark">
                                    Sin asignar
                                </el-tag>
                            </div>
                        </div>
                    </button>
                    <el-dropdown
                        trigger="click"
                        class="absolute right-2 top-2 opacity-0 transition-opacity group-hover:opacity-100"
                        @command="(cmd) => onRowMenu('ticket', t, cmd)"
                    >
                        <button
                            type="button"
                            class="rounded-md p-1 text-[#8B9AAB] hover:bg-white/10 hover:text-white"
                            @click.stop
                        >
                            <el-icon :size="16"><MoreFilled /></el-icon>
                        </button>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item command="unread">
                                    {{ t.meta?.marked_unread || t.unread ? 'Marcar como leído' : 'Marcar como no leído' }}
                                </el-dropdown-item>
                                <el-dropdown-item command="star">
                                    {{ t.meta?.starred ? 'Quitar de más tarde' : 'Leer más tarde' }}
                                </el-dropdown-item>
                                <el-dropdown-item command="pin">
                                    {{ t.meta?.pinned ? 'Desfijar' : 'Fijar chat' }}
                                </el-dropdown-item>
                                <el-dropdown-item command="mute">
                                    {{ t.meta?.muted ? 'Activar notificaciones' : 'Silenciar' }}
                                </el-dropdown-item>
                                <el-dropdown-item divided command="archive">
                                    {{ t.meta?.archived ? 'Desarchivar' : 'Archivar' }}
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </div>

                <p v-if="!ticketRows.length" class="px-4 py-16 text-center text-sm text-[#8B9AAB]">
                    {{
                        inboxFilter === 'starred'
                            ? 'No hay chats para más tarde.'
                            : inboxFilter === 'unread'
                              ? 'No hay mensajes sin leer.'
                              : inboxFilter === 'archived'
                                ? 'No hay chats archivados.'
                                : 'No hay conversaciones.'
                    }}
                    <button
                        v-if="(canCreate || can('create tickets')) && (!inboxFilter || inboxFilter === 'all')"
                        type="button"
                        class="mt-2 block w-full text-[#85B8FF] hover:underline"
                        @click="openCreate"
                    >
                        Reportar un problema
                    </button>
                </p>
                </template>

                <template v-else>
                    <div
                        v-for="c in filteredDmConversations"
                        :key="c.id"
                        class="group relative flex w-full items-start gap-3 border-b border-[#2a3340]/60 px-3 py-3 text-left transition-colors hover:bg-white/5"
                        :class="[
                            dmChat?.id === c.id ? 'inbox-row--active' : '',
                            c.unread ? 'inbox-row--unread' : '',
                        ]"
                    >
                        <button type="button" class="flex min-w-0 flex-1 items-start gap-3 text-left" @click="openDm(c.id)">
                            <div class="relative shrink-0">
                                <el-avatar
                                    :size="44"
                                    :src="avatar(c.peer?.name)"
                                    :class="c.unread ? 'avatar-ring--unread' : ''"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-2">
                                    <p
                                        class="truncate text-sm"
                                        :class="c.unread ? 'font-bold text-white' : 'font-semibold text-[#E8EEF4]'"
                                    >
                                        {{ c.peer?.name || 'Usuario' }}
                                    </p>
                                    <span
                                        class="shrink-0 text-[10px] tabular-nums"
                                        :class="c.unread ? 'font-bold text-[#25D366]' : 'text-[#8B9AAB]'"
                                    >
                                        {{ listTime(c.last_message?.created_at || c.updated_at) }}
                                    </span>
                                </div>
                                <div class="mt-0.5 flex items-center gap-2">
                                    <p
                                        class="min-w-0 flex-1 truncate text-xs"
                                        :class="c.unread ? 'font-semibold text-white' : 'text-[#8B9AAB]'"
                                    >
                                        {{ dmPreviewText(c) }}
                                    </p>
                                    <span
                                        v-if="c.unread"
                                        class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-[#25D366] px-1.5 text-[10px] font-extrabold text-[#052e16] shadow-[0_0_8px_rgba(37,211,102,0.45)]"
                                    >
                                        {{ c.unread > 99 ? '99+' : c.unread }}
                                    </span>
                                    <el-icon v-else-if="c.meta?.muted" class="shrink-0 text-[#8B9AAB]" :size="14"><MuteNotification /></el-icon>
                                    <el-icon v-else-if="c.meta?.starred" class="shrink-0 text-[#F5C518]" :size="14"><StarFilled /></el-icon>
                                </div>
                            </div>
                        </button>
                        <el-dropdown
                            trigger="click"
                            class="absolute right-2 top-2 opacity-0 transition-opacity group-hover:opacity-100"
                            @command="(cmd) => onRowMenu('dm', c, cmd)"
                        >
                            <button
                                type="button"
                                class="rounded-md p-1 text-[#8B9AAB] hover:bg-white/10 hover:text-white"
                                @click.stop
                            >
                                <el-icon :size="16"><MoreFilled /></el-icon>
                            </button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="unread">
                                        {{ c.meta?.marked_unread || c.unread ? 'Marcar como leído' : 'Marcar como no leído' }}
                                    </el-dropdown-item>
                                    <el-dropdown-item command="star">
                                        {{ c.meta?.starred ? 'Quitar de más tarde' : 'Leer más tarde' }}
                                    </el-dropdown-item>
                                    <el-dropdown-item command="pin">
                                        {{ c.meta?.pinned ? 'Desfijar' : 'Fijar chat' }}
                                    </el-dropdown-item>
                                    <el-dropdown-item command="mute">
                                        {{ c.meta?.muted ? 'Activar notificaciones' : 'Silenciar' }}
                                    </el-dropdown-item>
                                    <el-dropdown-item divided command="archive">
                                        {{ c.meta?.archived ? 'Desarchivar' : 'Archivar' }}
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </div>

                    <p v-if="!filteredDmConversations.length && !dmStartUsers.length" class="px-4 py-10 text-center text-sm text-[#8B9AAB]">
                        {{
                            inboxFilter === 'starred'
                                ? 'No hay chats para más tarde.'
                                : inboxFilter === 'unread'
                                  ? 'No hay mensajes sin leer.'
                                  : inboxFilter === 'archived'
                                    ? 'No hay chats archivados.'
                                    : 'No hay chats directos.'
                        }}
                    </p>

                    <div v-if="dmStartUsers.length && (!inboxFilter || inboxFilter === 'all')" class="border-t border-[#2a3340] px-3 py-2">
                        <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wide text-[#5a6a7a]">
                            Iniciar chat
                        </p>
                        <button
                            v-for="u in dmStartUsers"
                            :key="u.id"
                            type="button"
                            class="flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left transition-colors hover:bg-white/5"
                            @click="openDmWithUser(u.id)"
                        >
                            <el-avatar :size="36" :src="avatar(u.name)" class="shrink-0" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-white">{{ u.name }}</p>
                                <p class="truncate text-[11px] text-[#8B9AAB]">@{{ u.username }}</p>
                            </div>
                            <el-icon class="text-[#8B9AAB]"><ChatDotRound /></el-icon>
                        </button>
                    </div>
                </template>
            </div>
        </aside>

        <!-- Hilo -->
        <section
            class="min-w-0 flex-1 flex-col bg-[#0b0f14]"
            :class="showThreadMobile ? 'flex' : 'hidden md:flex'"
        >
            <template v-if="isIncidenciasTab && chat">
                <header class="flex shrink-0 items-center gap-3 border-b border-[#2a3340] bg-[#12171d] px-3 py-2.5">
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full text-[#8B9AAB] hover:bg-white/5 md:hidden"
                        @click="closeChatMobile"
                    >
                        <el-icon :size="18"><ArrowLeft /></el-icon>
                    </button>

                    <div class="flex min-w-0 flex-1 items-center gap-2.5">
                        <div class="flex -space-x-2">
                            <el-avatar
                                v-for="p in (chat.participantes || []).slice(0, 3)"
                                :key="p.id"
                                :size="32"
                                :src="avatar(p.name)"
                                class="ring-2 ring-[#12171d]"
                            />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-white">{{ chat.titulo }}</p>
                            <p
                                v-if="typingHeaderSubtitle"
                                class="truncate text-[11px] font-medium text-[#25D366]"
                            >
                                {{ typingHeaderSubtitle }}
                                <span class="typing-dots" aria-hidden="true"><i /><i /><i /></span>
                            </p>
                            <p v-else class="truncate text-[11px] text-[#8B9AAB]">
                                {{
                                    (chat.participantes || [])
                                        .map((p) => p.name)
                                        .join(', ') || 'Participantes'
                                }}
                                <span v-if="chat.estado"> · {{ chat.estado.nombre }}</span>
                            </p>
                        </div>
                    </div>

                    <template v-if="canUseCalls && ticketCallTargets.length">
                        <el-dropdown
                            v-if="ticketCallTargets.length > 1"
                            trigger="click"
                            @command="(id) => startTicketCall(id, false)"
                        >
                            <el-button
                                size="small"
                                circle
                                :title="livekitEnabled ? 'Llamar' : 'Configura LiveKit en .env'"
                                :class="!livekitEnabled ? 'opacity-45' : ''"
                            >
                                <el-icon><Phone /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item
                                        v-for="p in ticketCallTargets"
                                        :key="p.id"
                                        :command="p.id"
                                    >
                                        Llamar a {{ p.name }}
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                        <el-button
                            v-else
                            size="small"
                            circle
                            :title="livekitEnabled ? 'Llamar' : 'Configura LiveKit en .env'"
                            :class="!livekitEnabled ? 'opacity-45' : ''"
                            @click="startTicketCall(ticketCallTargets[0].id, false)"
                        >
                            <el-icon><Phone /></el-icon>
                        </el-button>
                        <el-dropdown
                            v-if="allowVideo && ticketCallTargets.length > 1"
                            trigger="click"
                            @command="(id) => startTicketCall(id, true)"
                        >
                            <el-button
                                size="small"
                                circle
                                :title="livekitEnabled ? 'Videollamada' : 'Configura LiveKit en Ajustes'"
                                :class="!livekitEnabled ? 'opacity-45' : ''"
                            >
                                <el-icon><VideoCamera /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item
                                        v-for="p in ticketCallTargets"
                                        :key="'v-' + p.id"
                                        :command="p.id"
                                    >
                                        Video con {{ p.name }}
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                        <el-button
                            v-else-if="allowVideo"
                            size="small"
                            circle
                            :title="livekitEnabled ? 'Videollamada' : 'Configura LiveKit en Ajustes'"
                            :class="!livekitEnabled ? 'opacity-45' : ''"
                            @click="startTicketCall(ticketCallTargets[0].id, true)"
                        >
                            <el-icon><VideoCamera /></el-icon>
                        </el-button>
                    </template>

                    <el-dropdown
                        trigger="click"
                        @command="(cmd) => onRowMenu('ticket', { id: chat.id, unread: 0, meta: chat.meta || {} }, cmd)"
                    >
                        <el-button size="small" circle>
                            <el-icon><MoreFilled /></el-icon>
                        </el-button>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item command="unread">Marcar como no leído</el-dropdown-item>
                                <el-dropdown-item command="star">
                                    {{ chat.meta?.starred ? 'Quitar de más tarde' : 'Leer más tarde' }}
                                </el-dropdown-item>
                                <el-dropdown-item command="pin">
                                    {{ chat.meta?.pinned ? 'Desfijar' : 'Fijar chat' }}
                                </el-dropdown-item>
                                <el-dropdown-item command="mute">
                                    {{ chat.meta?.muted ? 'Activar notificaciones' : 'Silenciar' }}
                                </el-dropdown-item>
                                <el-dropdown-item divided command="archive">
                                    {{ chat.meta?.archived ? 'Desarchivar' : 'Archivar' }}
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>

                    <el-button
                        v-if="isSoporte"
                        size="small"
                        @click="openBoard"
                    >
                        <el-icon class="mr-1"><Grid /></el-icon>
                        Tablero
                    </el-button>
                </header>

                <div
                    v-if="chat.adjuntos?.length"
                    class="flex shrink-0 gap-2 overflow-x-auto border-b border-[#2a3340] bg-[#12171d] px-3 py-2"
                >
                    <button
                        v-for="a in chat.adjuntos"
                        :key="a.id"
                        type="button"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-[#2a3340] bg-[#1a222c] px-2.5 py-1 text-[11px] text-[#E8EEF4] hover:border-[#579DFF]/50"
                        @click="openViewer(a)"
                    >
                        <span class="font-semibold uppercase text-[#85B8FF]">{{ kindLabel(a.kind) }}</span>
                        <span class="max-w-[120px] truncate">{{ a.nombre }}</span>
                    </button>
                </div>

                <div
                    ref="threadRef"
                    class="chat-thread min-h-0 flex-1 space-y-2 overflow-y-auto px-3 py-4 sm:px-5"
                >
                    <div
                        v-if="chat.descripcion"
                        class="mx-auto mb-4 max-w-lg rounded-[12px] border border-[#2a3340] bg-[#12171d] px-3 py-2 text-center text-xs text-[#8B9AAB]"
                    >
                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-[#5a6a7a]">
                            Detalle del reporte
                        </p>
                        {{ chat.descripcion }}
                    </div>

                    <template v-for="item in ticketThreadItems" :key="item.kind === 'divider' ? item.id : item.msg.id">
                        <div
                            v-if="item.kind === 'divider'"
                            class="my-3 flex items-center gap-3"
                        >
                            <div class="h-px flex-1 bg-[#25D366]/70" />
                            <span class="unread-divider-pill shrink-0 rounded-full px-2.5 py-0.5 text-[10px] uppercase">
                                Mensajes nuevos
                            </span>
                            <div class="h-px flex-1 bg-[#25D366]/70" />
                        </div>
                        <div
                            v-else
                            class="flex gap-2"
                            :class="[isMine(item.msg) ? 'flex-row-reverse' : 'flex-row', item.msg.pending ? 'opacity-60' : '']"
                        >
                        <img
                            v-if="!isMine(item.msg)"
                            :src="avatar(item.msg.user?.name)"
                            class="mt-1 h-8 w-8 shrink-0 rounded-full"
                            alt=""
                        />
                        <div
                            class="chat-bubble max-w-[78%] px-3 py-2 text-sm text-[#E8EEF4] sm:max-w-[65%]"
                            :class="isMine(item.msg) ? 'chat-bubble--mine' : 'chat-bubble--theirs'"
                        >
                            <p
                                v-if="!isMine(item.msg)"
                                class="mb-0.5 text-[11px] font-semibold text-[#85B8FF]"
                            >
                                {{ item.msg.user?.name }}
                            </p>
                            <p
                                v-if="item.msg.contenido"
                                class="whitespace-pre-wrap leading-relaxed"
                                v-html="renderMentions(item.msg.contenido)"
                            />
                            <div
                                v-if="item.msg.adjunto?.kind === 'audio' && item.msg.adjunto?.url"
                                class="mt-1.5"
                            >
                                <AudioMessage
                                    :src="item.msg.adjunto.url"
                                    :mine="isMine(item.msg)"
                                    :name="item.msg.adjunto.nombre || 'Nota de voz'"
                                />
                            </div>
                            <button
                                v-if="item.msg.imagen_url"
                                type="button"
                                class="mt-1.5 block w-full text-left"
                                @click="openViewer({ url: item.msg.imagen_url, nombre: 'Imagen', kind: 'image' })"
                            >
                                <img
                                    :src="item.msg.imagen_url"
                                    alt="Captura"
                                    class="max-h-52 max-w-full rounded-lg object-contain"
                                />
                            </button>
                            <button
                                v-if="item.msg.adjunto && item.msg.adjunto.kind !== 'image' && item.msg.adjunto.kind !== 'audio'"
                                type="button"
                                class="mt-2 flex w-full items-center gap-2 rounded-lg border border-white/10 bg-black/20 px-2.5 py-2 text-left hover:bg-black/30"
                                @click="openViewer(item.msg.adjunto)"
                            >
                                <span class="rounded bg-[#579DFF]/25 px-1.5 py-0.5 text-[10px] font-bold uppercase text-[#85B8FF]">
                                    {{ kindLabel(item.msg.adjunto.kind) }}
                                </span>
                                <span class="min-w-0 flex-1 truncate text-xs">{{ item.msg.adjunto.nombre }}</span>
                            </button>
                            <p
                                class="mt-1 text-right text-[10px]"
                                :class="isMine(item.msg) ? 'text-white/50' : 'text-[#8B9AAB]'"
                            >
                                {{ msgTime(item.msg.created_at) }}
                            </p>
                        </div>
                        </div>
                    </template>

                    <p v-if="!chat.comentarios?.length && !typingUsers.length" class="py-16 text-center text-sm text-[#8B9AAB]">
                        Sin mensajes. Escribe el primero…
                    </p>

                    <div
                        v-for="u in typingUsers"
                        :key="'typing-' + u.id"
                        class="flex gap-2"
                    >
                        <img :src="avatar(u.name)" class="mt-1 h-8 w-8 shrink-0 rounded-full" alt="" />
                        <div class="typing-bubble">
                            <span class="typing-dots typing-dots--lg" aria-hidden="true"><i /><i /><i /></span>
                        </div>
                    </div>
                </div>

                <div class="h-2 shrink-0" />

                <div
                    v-if="chat.canComment"
                    class="relative shrink-0 border-t border-[#2a3340] bg-[#12171d] px-2 py-2 sm:px-3"
                >
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

                    <div v-if="imagePreview || pendingImage" class="relative mb-2 inline-flex items-center gap-2 px-1">
                        <img
                            v-if="imagePreview"
                            :src="imagePreview"
                            class="max-h-24 rounded-[10px] border border-[#2a3340]"
                            alt="Preview"
                        />
                        <div
                            v-else-if="pendingImage && isAudioFile(pendingImage)"
                            class="rounded-[10px] border border-[#25D366]/40 bg-[#25D366]/10 px-3 py-2 text-xs text-[#57D9A3]"
                        >
                            <el-icon class="mr-1 align-middle"><Microphone /></el-icon>
                            Nota de voz · {{ pendingImage.name }}
                        </div>
                        <span
                            v-else-if="pendingImage"
                            class="rounded-[10px] border border-[#2a3340] bg-[#1a222c] px-3 py-2 text-xs text-[#E8EEF4]"
                        >
                            {{ pendingImage.name }}
                        </span>
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
                            title="Adjuntar imagen, PDF, Word o audio"
                        >
                            <el-icon :size="18"><Paperclip /></el-icon>
                            <input
                                type="file"
                                accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.webm,.ogg,.mp3,.m4a,.wav,.aac,image/*,application/pdf,audio/*"
                                class="hidden"
                                @change="onFilePick"
                            />
                        </label>

                        <VoiceRecorder @recorded="onVoiceRecorded" />

                        <textarea
                            ref="composerRef"
                            v-model="comment"
                            rows="1"
                            class="max-h-28 min-h-[36px] flex-1 resize-none bg-transparent py-2 text-sm text-white placeholder:text-[#8B9AAB] focus:outline-none"
                            placeholder="Mensaje o nota de voz"
                            @paste="onPaste"
                            @input="detectMention"
                            @keydown="onComposerKeydown"
                            @click="detectMention"
                        />

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
                <div v-else class="shrink-0 border-t border-[#2a3340] px-4 py-3 text-center text-xs text-[#8B9AAB]">
                    No puedes escribir en este chat.
                </div>
            </template>

            <template v-else-if="isPersonasTab && dmChat">
                <header class="flex shrink-0 items-center gap-3 border-b border-[#2a3340] bg-[#12171d] px-3 py-2.5">
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full text-[#8B9AAB] hover:bg-white/5 md:hidden"
                        @click="closeChatMobile"
                    >
                        <el-icon :size="18"><ArrowLeft /></el-icon>
                    </button>

                    <div class="flex min-w-0 flex-1 items-center gap-2.5">
                        <el-avatar :size="32" :src="avatar(dmChat.peer?.name)" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-white">
                                {{ dmChat.peer?.name || 'Chat directo' }}
                            </p>
                            <p
                                v-if="typingHeaderSubtitle"
                                class="truncate text-[11px] font-medium text-[#25D366]"
                            >
                                escribiendo
                                <span class="typing-dots" aria-hidden="true"><i /><i /><i /></span>
                            </p>
                            <p v-else class="truncate text-[11px] text-[#8B9AAB]">
                                @{{ dmChat.peer?.username || 'usuario' }}
                            </p>
                        </div>
                    </div>

                    <template v-if="canUseCalls && dmChat.peer?.id">
                        <el-button
                            size="small"
                            circle
                            :title="livekitEnabled ? 'Llamar' : 'Configura LiveKit en .env'"
                            :class="!livekitEnabled ? 'opacity-45' : ''"
                            @click="startDmCall(false)"
                        >
                            <el-icon><Phone /></el-icon>
                        </el-button>
                        <el-button
                            v-if="allowVideo"
                            size="small"
                            circle
                            :title="livekitEnabled ? 'Videollamada' : 'Configura LiveKit en Ajustes'"
                            :class="!livekitEnabled ? 'opacity-45' : ''"
                            @click="startDmCall(true)"
                        >
                            <el-icon><VideoCamera /></el-icon>
                        </el-button>
                    </template>

                    <el-dropdown
                        trigger="click"
                        @command="(cmd) => onRowMenu('dm', { id: dmChat.id, unread: 0, meta: dmChat.meta || {} }, cmd)"
                    >
                        <el-button size="small" circle>
                            <el-icon><MoreFilled /></el-icon>
                        </el-button>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item command="unread">Marcar como no leído</el-dropdown-item>
                                <el-dropdown-item command="star">
                                    {{ dmChat.meta?.starred ? 'Quitar de más tarde' : 'Leer más tarde' }}
                                </el-dropdown-item>
                                <el-dropdown-item command="pin">
                                    {{ dmChat.meta?.pinned ? 'Desfijar' : 'Fijar chat' }}
                                </el-dropdown-item>
                                <el-dropdown-item command="mute">
                                    {{ dmChat.meta?.muted ? 'Activar notificaciones' : 'Silenciar' }}
                                </el-dropdown-item>
                                <el-dropdown-item divided command="archive">
                                    {{ dmChat.meta?.archived ? 'Desarchivar' : 'Archivar' }}
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </header>

                <div
                    ref="dmThreadRef"
                    class="chat-thread min-h-0 flex-1 space-y-2 overflow-y-auto px-3 py-4 sm:px-5"
                >
                    <template v-for="item in dmThreadItems" :key="item.kind === 'divider' ? item.id : item.msg.id">
                        <div
                            v-if="item.kind === 'divider'"
                            class="my-3 flex items-center gap-3"
                        >
                            <div class="h-px flex-1 bg-[#25D366]/70" />
                            <span class="unread-divider-pill shrink-0 rounded-full px-2.5 py-0.5 text-[10px] uppercase">
                                Mensajes nuevos
                            </span>
                            <div class="h-px flex-1 bg-[#25D366]/70" />
                        </div>
                        <div
                            v-else
                            class="flex gap-2"
                            :class="[isMine(item.msg) ? 'flex-row-reverse' : 'flex-row', item.msg.pending ? 'opacity-60' : '']"
                        >
                        <img
                            v-if="!isMine(item.msg)"
                            :src="avatar(item.msg.user?.name)"
                            class="mt-1 h-8 w-8 shrink-0 rounded-full"
                            alt=""
                        />
                        <div
                            class="chat-bubble max-w-[78%] px-3 py-2 text-sm text-[#E8EEF4] sm:max-w-[65%]"
                            :class="isMine(item.msg) ? 'chat-bubble--mine' : 'chat-bubble--theirs'"
                        >
                            <p
                                v-if="!isMine(item.msg)"
                                class="mb-0.5 text-[11px] font-semibold text-[#85B8FF]"
                            >
                                {{ item.msg.user?.name }}
                            </p>
                            <p v-if="item.msg.contenido" class="whitespace-pre-wrap leading-relaxed">
                                {{ item.msg.contenido }}
                            </p>
                            <div
                                v-if="item.msg.kind === 'audio' && item.msg.url"
                                class="mt-1.5"
                            >
                                <AudioMessage
                                    :src="item.msg.url"
                                    :mine="isMine(item.msg)"
                                    :name="item.msg.nombre || 'Nota de voz'"
                                />
                            </div>
                            <button
                                v-if="item.msg.kind === 'image' && item.msg.url"
                                type="button"
                                class="mt-1.5 block w-full text-left"
                                @click="openViewer({ url: item.msg.url, nombre: item.msg.nombre || 'Imagen', kind: 'image' })"
                            >
                                <img
                                    :src="item.msg.url"
                                    alt="Imagen"
                                    class="max-h-52 max-w-full rounded-lg object-contain"
                                />
                            </button>
                            <button
                                v-if="item.msg.url && item.msg.kind && item.msg.kind !== 'image' && item.msg.kind !== 'audio'"
                                type="button"
                                class="mt-2 flex w-full items-center gap-2 rounded-lg border border-white/10 bg-black/20 px-2.5 py-2 text-left hover:bg-black/30"
                                @click="openViewer({ url: item.msg.url, nombre: item.msg.nombre, kind: item.msg.kind, size: item.msg.size })"
                            >
                                <span class="rounded bg-[#579DFF]/25 px-1.5 py-0.5 text-[10px] font-bold uppercase text-[#85B8FF]">
                                    {{ kindLabel(item.msg.kind) }}
                                </span>
                                <span class="min-w-0 flex-1 truncate text-xs">{{ item.msg.nombre }}</span>
                                <span class="text-[10px] text-[#8B9AAB]">{{ formatSize(item.msg.size) }}</span>
                            </button>
                            <p
                                class="mt-1 text-right text-[10px] tabular-nums"
                                :class="isMine(item.msg) ? 'text-white/50' : 'text-[#8B9AAB]'"
                            >
                                {{ msgTime(item.msg.created_at) }}
                                <span v-if="item.msg.pending"> · enviando</span>
                            </p>
                        </div>
                        </div>
                    </template>

                    <p v-if="!dmChat.mensajes?.length && !dmTypingUsers.length" class="py-16 text-center text-sm text-[#8B9AAB]">
                        Sin mensajes. Escribe el primero…
                    </p>

                    <div
                        v-for="u in dmTypingUsers"
                        :key="'dm-typing-' + u.id"
                        class="flex gap-2"
                    >
                        <img :src="avatar(u.name)" class="mt-1 h-8 w-8 shrink-0 rounded-full" alt="" />
                        <div class="typing-bubble">
                            <span class="typing-dots typing-dots--lg" aria-hidden="true"><i /><i /><i /></span>
                        </div>
                    </div>
                </div>

                <div class="h-2 shrink-0" />

                <div
                    v-if="dmChat.canMessage"
                    class="relative shrink-0 border-t border-[#2a3340] bg-[#12171d] px-2 py-2 sm:px-3"
                >
                    <div v-if="dmFilePreview || dmPendingFile" class="relative mb-2 inline-flex items-center gap-2 px-1">
                        <img
                            v-if="dmFilePreview"
                            :src="dmFilePreview"
                            class="max-h-24 rounded-[10px] border border-[#2a3340]"
                            alt="Preview"
                        />
                        <div
                            v-else-if="dmPendingFile && isAudioFile(dmPendingFile)"
                            class="rounded-[10px] border border-[#25D366]/40 bg-[#25D366]/10 px-3 py-2 text-xs text-[#57D9A3]"
                        >
                            <el-icon class="mr-1 align-middle"><Microphone /></el-icon>
                            Nota de voz · {{ dmPendingFile.name }}
                        </div>
                        <span
                            v-else-if="dmPendingFile"
                            class="rounded-[10px] border border-[#2a3340] bg-[#1a222c] px-3 py-2 text-xs text-[#E8EEF4]"
                        >
                            {{ dmPendingFile.name }}
                        </span>
                        <button
                            type="button"
                            class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-[#C4554D] text-white"
                            @click="clearDmFile"
                        >
                            <el-icon :size="12"><Close /></el-icon>
                        </button>
                    </div>

                    <div class="flex items-end gap-1.5 rounded-[24px] bg-[#1a222c] px-1.5 py-1 ring-1 ring-[#2a3340]">
                        <label
                            class="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-full text-[#8B9AAB] transition hover:bg-white/5 hover:text-white"
                            title="Adjuntar imagen, PDF, Word o audio"
                        >
                            <el-icon :size="18"><Paperclip /></el-icon>
                            <input
                                type="file"
                                accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.webm,.ogg,.mp3,.m4a,.wav,.aac,image/*,application/pdf,audio/*"
                                class="hidden"
                                @change="onDmFilePick"
                            />
                        </label>

                        <VoiceRecorder @recorded="onDmVoiceRecorded" />

                        <textarea
                            ref="dmComposerRef"
                            v-model="dmComment"
                            rows="1"
                            class="max-h-28 min-h-[36px] flex-1 resize-none bg-transparent py-2 text-sm text-white placeholder:text-[#8B9AAB] focus:outline-none"
                            placeholder="Mensaje o nota de voz"
                            @paste="onDmPaste"
                            @input="pingDmTyping"
                            @keydown="onDmComposerKeydown"
                        />

                        <button
                            type="button"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#3D7A5F] text-white transition hover:bg-[#4a9472] disabled:opacity-50"
                            :disabled="dmSending || (!dmComment.trim() && !dmPendingFile)"
                            title="Enviar"
                            @click="sendDmMessage"
                        >
                            <el-icon :size="16"><Promotion /></el-icon>
                        </button>
                    </div>
                </div>
                <div v-else class="shrink-0 border-t border-[#2a3340] px-4 py-3 text-center text-xs text-[#8B9AAB]">
                    No puedes escribir en este chat.
                </div>
            </template>

            <div
                v-else
                class="flex flex-1 flex-col items-center justify-center gap-3 px-6 text-center"
            >
                <el-icon :size="48" class="text-[#2a3340]"><ChatDotRound /></el-icon>
                <p class="font-display text-lg font-semibold text-white">Selecciona una conversación</p>
                <p class="max-w-sm text-sm text-[#8B9AAB]">
                    <template v-if="isPersonasTab">
                        Elige un chat directo a la izquierda o inicia uno con alguien del equipo.
                    </template>
                    <template v-else>
                        Elige un chat a la izquierda para ver el hilo y responder.
                        {{ isSoporte ? '' : ' Cada incidencia es una conversación con soporte.' }}
                    </template>
                </p>
                <el-button
                    v-if="isIncidenciasTab && (canCreate || can('create tickets'))"
                    type="primary"
                    @click="openCreate"
                >
                    {{ isSoporte ? 'Nueva incidencia' : 'Reportar problema' }}
                </el-button>
            </div>
        </section>

        <FileViewer v-model="viewerOpen" :file="viewerFile" />
    </div>
</template>
