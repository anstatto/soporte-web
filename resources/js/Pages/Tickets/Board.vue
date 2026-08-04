<script setup>
import { computed, inject, nextTick, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { VueDraggable } from 'vue-draggable-plus';
import { useLocalStorage } from '@vueuse/core';
import axios from 'axios';
import { toast } from 'vue-sonner';
import CardModal from '@/Components/Board/CardModal.vue';
import { timeAgo } from '@/Composables/useDate';

const props = defineProps({
    estados: Array,
    columns: Object,
    departamentos: Array,
    etiquetas: { type: Array, default: () => [] },
    prioridades: { type: Array, default: () => [] },
    usuarios: Array,
    filters: Object,
    canDrag: Boolean,
    canCreate: Boolean,
    canManageUsers: Boolean,
    defaultDepartamentoId: [Number, String],
});

const board = reactive({});
const search = ref(props.filters?.q || '');
const memberFilter = ref(null);
const labelFilter = ref(null);
const priorityFilter = ref(null);
const addingTo = ref(null);
const draftTitle = ref('');
const selectedId = ref(null);
const dragging = ref(false);
const page = usePage();
const appNotifications = inject('appNotifications', ref([]));

const firstEstadoId = computed(() => props.estados?.[0]?.id ?? null);
const hasFilter = computed(() =>
    !!(search.value.trim() || memberFilter.value || labelFilter.value || priorityFilter.value),
);
const dragEnabled = computed(() => props.canDrag && !hasFilter.value);

const csrfHeaders = () => ({
    'X-Requested-With': 'XMLHttpRequest',
    Accept: 'application/json',
});

const syncBoard = (cols) => {
    props.estados.forEach((e) => {
        board[e.id] = [...(cols?.[e.id] || cols?.[String(e.id)] || [])];
    });
};
syncBoard(props.columns);
watch(() => props.columns, syncBoard, { deep: true });

const allCards = computed(() =>
    (props.estados || []).flatMap((e) => board[e.id] || []),
);

const estadoNombre = (estadoId) =>
    (props.estados || []).find((e) => e.id === estadoId)?.nombre?.toLowerCase() || '';

const stats = computed(() => {
    const cards = allCards.value;
    return {
        total: cards.length,
        open: cards.filter((c) => estadoNombre(c.estado_id) !== 'cerrado').length,
        overdue: cards.filter((c) => c.overdue).length,
        urgent: cards.filter((c) => c.prioridad === 'urgente' || c.prioridad === 'alta').length,
    };
});

const prioritySummary = computed(() => {
    const cards = allCards.value;
    const total = cards.length || 1;
    const groups = [
        { key: 'alta', label: 'Alta / urgente', color: '#C4554D', count: 0 },
        { key: 'media', label: 'Media', color: '#579DFF', count: 0 },
        { key: 'baja', label: 'Baja', color: '#3D7A5F', count: 0 },
    ];
    cards.forEach((c) => {
        if (c.prioridad === 'alta' || c.prioridad === 'urgente') groups[0].count += 1;
        else if (c.prioridad === 'baja') groups[2].count += 1;
        else groups[1].count += 1;
    });
    return groups.map((g) => ({
        ...g,
        pct: Math.round((g.count / total) * 100),
    }));
});

const donutStyle = computed(() => {
    const items = prioritySummary.value.filter((g) => g.count > 0);
    if (!items.length) {
        return { background: 'conic-gradient(#2a3340 0 100%)' };
    }
    let acc = 0;
    const parts = items.map((g) => {
        const start = acc;
        acc += g.pct;
        return `${g.color} ${start}% ${acc}%`;
    });
    return { background: `conic-gradient(${parts.join(', ')})` };
});

const recentActivity = computed(() => {
    const list = (appNotifications?.value || []).slice(0, 8);
    return list.map((n) => {
        const d = n.data || {};
        const type = d.type || n.type || '';
        let icon = 'Ticket';
        let tone = 'blue';
        let title = d.message || d.ticket_title || 'Actividad';
        if (type.includes('message') || type.includes('comment')) {
            icon = 'ChatDotRound';
            tone = 'purple';
            title = 'Comentario añadido';
        } else if (type.includes('moved') || type.includes('ticket_moved')) {
            icon = 'Rank';
            tone = 'blue';
            title = 'Tarjeta movida';
        } else if (type.includes('assign')) {
            icon = 'User';
            tone = 'blue';
            title = 'Asignación';
        } else if (type.includes('created') || type.includes('ticket_created')) {
            icon = 'Plus';
            tone = 'green';
            title = 'Nueva incidencia';
        } else if ((d.ticket_title || '').toLowerCase().includes('cerr')) {
            icon = 'CircleCheck';
            tone = 'green';
            title = 'Incidencia cerrada';
        }
        return {
            id: n.id,
            title,
            detail: d.ticket_title || d.excerpt || '',
            by: d.by || d.assigned_by || d.mentioned_by || '',
            when: timeAgo(n.created_at),
            ticketId: d.ticket_id,
            icon,
            tone,
        };
    });
});

const startAdd = async (estadoId) => {
    if (!props.canCreate) return;
    addingTo.value = estadoId;
    draftTitle.value = '';
    await nextTick();
    document.getElementById(`quick-add-${estadoId}`)?.focus();
};

const startNewIncidencia = () => {
    if (!props.canCreate) return;
    window.dispatchEvent(new CustomEvent('soporte:open-create-ticket'));
};

const clearCardQuery = () => {
    const path = window.location.pathname;
    const params = new URLSearchParams(window.location.search);
    if (!params.has('card') && !params.has('new')) return;
    params.delete('card');
    params.delete('new');
    const next = params.toString() ? `${path}?${params}` : path;
    window.history.replaceState(window.history.state, '', next);
};

const dismissedQueryCard = ref(null);

const openFromQuery = () => {
    const browserParams = new URLSearchParams(window.location.search);
    const browserCard = browserParams.get('card');
    const q = browserParams.get('q') || new URLSearchParams(page.url.split('?')[1] || '').get('q');
    const wantNew = browserParams.get('new') === '1';

    if (browserCard) {
        const id = Number(browserCard) || browserCard;
        if (Number(dismissedQueryCard.value) === Number(id)) {
            clearCardQuery();
            return;
        }
        selectedId.value = id;
        clearCardQuery();
    }
    if (q) search.value = q;
    if (wantNew && props.canCreate) {
        startNewIncidencia();
        clearCardQuery();
    }
};
openFromQuery();
watch(() => page.url, () => {
    // Solo reaccionar si el browser todavía tiene ?card=
    if (new URLSearchParams(window.location.search).has('card')
        || new URLSearchParams(window.location.search).has('new')) {
        openFromQuery();
    }
});

const closeCard = () => {
    if (selectedId.value != null) {
        dismissedQueryCard.value = Number(selectedId.value);
    }
    selectedId.value = null;
    clearCardQuery();
};

const matches = (card) => {
    const q = search.value.trim().toLowerCase();
    if (q) {
        const hit =
            card.titulo.toLowerCase().includes(q) ||
            (card.departamento || '').toLowerCase().includes(q) ||
            (card.user || '').toLowerCase().includes(q) ||
            (card.etiquetas || []).some((e) => e.nombre.toLowerCase().includes(q)) ||
            (card.asignados || []).some((a) => (a.name || '').toLowerCase().includes(q));
        if (!hit) return false;
    }
    if (memberFilter.value) {
        const ids = (card.asignados || []).map((a) => a.id);
        const creatorMatch = String(card.user || '').toLowerCase() === String(memberFilter.value.name || '').toLowerCase();
        if (!ids.includes(memberFilter.value.id) && !creatorMatch) return false;
    }
    if (labelFilter.value) {
        if (!(card.etiquetas || []).some((e) => e.id === labelFilter.value.id)) return false;
    }
    if (priorityFilter.value) {
        if (card.prioridad !== priorityFilter.value) return false;
    }
    return true;
};

const persistColumn = async (estadoId) => {
    if (!props.canDrag) return;
    const ids = (board[estadoId] || []).map((c) => c.id);
    (board[estadoId] || []).forEach((c, i) => {
        c.estado_id = Number(estadoId);
        c.position = i;
    });
    try {
        await axios.patch('/tickets/board/reorder', {
            estado_id: estadoId,
            ticket_ids: ids,
        }, { headers: csrfHeaders() });
    } catch {
        toast.error('No se pudo guardar el orden');
        syncBoard(props.columns);
    }
};

const onDragChange = async (estadoId) => {
    if (!dragEnabled.value) return;
    await persistColumn(estadoId);
};

const cancelAdd = () => {
    addingTo.value = null;
    draftTitle.value = '';
};

const submitQuick = async (estadoId) => {
    const titulo = draftTitle.value.trim();
    if (!titulo) return;
    try {
        const { data } = await axios.post('/tickets/quick', {
            titulo,
            estado_id: estadoId,
            departamento_id: props.defaultDepartamentoId,
        }, { headers: csrfHeaders() });
        board[estadoId] = [data.card, ...(board[estadoId] || [])];
        draftTitle.value = '';
        addingTo.value = null;
        toast.success('Incidencia creada');
    } catch (e) {
        toast.error(e.response?.data?.message || 'No se pudo crear');
    }
};

const openCard = (id) => {
    if (dragging.value) return;
    dismissedQueryCard.value = null;
    selectedId.value = id;
};

const onCardUpdated = (card) => {
    let existing = null;
    props.estados.forEach((e) => {
        const found = (board[e.id] || []).find((c) => c.id === card.id);
        if (found) existing = { ...found };
    });
    const merged = { ...existing, ...card };
    const col = Number(merged.estado_id ?? existing?.estado_id);
    props.estados.forEach((e) => {
        board[e.id] = (board[e.id] || []).filter((c) => c.id !== card.id);
    });
    if (col && board[col] !== undefined) {
        board[col] = [merged, ...(board[col] || [])];
    }
};

const onCardDeleted = (id) => {
    props.estados.forEach((e) => {
        board[e.id] = (board[e.id] || []).filter((c) => c.id !== id);
    });
    closeCard();
};

const visibleCards = (estadoId) => (board[estadoId] || []).filter(matches);
const visibleCount = (estadoId) => visibleCards(estadoId).length;

const clearFilters = () => {
    search.value = '';
    memberFilter.value = null;
    labelFilter.value = null;
    priorityFilter.value = null;
};

const onMemberFilter = (id) => {
    if (!id && id !== 0) {
        memberFilter.value = null;
        return;
    }
    memberFilter.value = props.usuarios?.find((u) => u.id === id) || null;
};
const onLabelFilter = (id) => {
    if (!id && id !== 0) {
        labelFilter.value = null;
        return;
    }
    labelFilter.value = props.etiquetas?.find((e) => e.id === id) || null;
};
const onPriorityFilter = (value) => {
    priorityFilter.value = value || null;
};

const onListCommand = (cmd, estado) => {
    if (cmd === 'add' && props.canCreate) {
        startAdd(estado.id);
        return;
    }
    if (cmd === 'copy') {
        const titles = visibleCards(estado.id).map((c) => `• ${c.titulo}`).join('\n');
        navigator.clipboard?.writeText(`${estado.nombre}\n${titles || '(vacía)'}`).then(
            () => toast.success('Lista copiada'),
            () => toast.error('No se pudo copiar'),
        );
    }
};

const chipVars = (color) => ({
    '--chip-fg': color || '#8B9AAB',
    '--chip-bg': `${color || '#8B9AAB'}22`,
    '--chip-border': `${color || '#8B9AAB'}40`,
});

const cardOwner = (card) => card.asignados?.[0]?.name || card.user || 'Sin asignar';
const avatarFor = (name) =>
    `https://ui-avatars.com/api/?name=${encodeURIComponent(name || '?')}&background=3D4F61&color=fff&size=64`;

const toneClass = {
    blue: 'bg-[#579DFF]/15 text-[#85B8FF]',
    green: 'bg-[#3D7A5F]/25 text-[#6BCB9A]',
    purple: 'bg-[#7C6BA8]/25 text-[#B8A8E0]',
    red: 'bg-[#C4554D]/20 text-[#E08A84]',
};

/** Panel derecho del tablero: abierto / cerrado a voluntad */
const activityPanelOpen = useLocalStorage('soporte-board-activity-panel', true);
const activityExpanded = useLocalStorage('soporte-board-activity-section', true);
const priorityExpanded = useLocalStorage('soporte-board-priority-section', true);
</script>

<template>
    <Head title="Tablero" />

        <div class="flex min-h-0 flex-1 overflow-hidden bg-[#0f1419]">
            <!-- Columna principal -->
            <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
                <!-- Título + CTA -->
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 pb-2 pt-4 sm:px-5">
                    <div>
                        <h1 class="font-display text-xl font-semibold tracking-tight text-white">
                            Soporte interno
                        </h1>
                        <p class="mt-0.5 text-xs text-[#8B9AAB]">
                            Tablero de incidencias del equipo
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <el-button
                            class="!m-0 !hidden xl:!inline-flex"
                            :type="activityPanelOpen ? 'primary' : 'default'"
                            plain
                            @click="activityPanelOpen = !activityPanelOpen"
                        >
                            <el-icon class="mr-1"><Bell /></el-icon>
                            {{ activityPanelOpen ? 'Ocultar actividad' : 'Ver actividad' }}
                        </el-button>
                        <el-button
                            v-if="canCreate"
                            type="primary"
                            class="!m-0 !h-9 !rounded-[10px] !border-none !bg-[#579DFF] !px-4 !font-medium"
                            @click="startNewIncidencia"
                        >
                            <el-icon class="mr-1"><Plus /></el-icon>
                            Nueva incidencia
                        </el-button>
                    </div>
                </div>

                <!-- Stats con iconos -->
                <div class="grid grid-cols-2 gap-2 px-4 py-3 sm:grid-cols-4 sm:gap-3 sm:px-5">
                    <div class="flex items-center gap-3 rounded-[10px] border border-[#2a3340] bg-[#1a222c] px-3 py-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#579DFF]/15 text-[#85B8FF]">
                            <el-icon :size="18"><Ticket /></el-icon>
                        </span>
                        <div>
                            <p class="text-[11px] text-[#8B9AAB]">Total</p>
                            <p class="text-lg font-semibold tabular-nums text-white">{{ stats.total }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-[10px] border border-[#2a3340] bg-[#1a222c] px-3 py-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#B7791F]/20 text-[#E0B45A]">
                            <el-icon :size="18"><FolderOpened /></el-icon>
                        </span>
                        <div>
                            <p class="text-[11px] text-[#8B9AAB]">Abiertas</p>
                            <p class="text-lg font-semibold tabular-nums text-white">{{ stats.open }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-[10px] border border-[#2a3340] bg-[#1a222c] px-3 py-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#C4554D]/20 text-[#E08A84]">
                            <el-icon :size="18"><Warning /></el-icon>
                        </span>
                        <div>
                            <p class="text-[11px] text-[#8B9AAB]">Vencidas</p>
                            <p class="text-lg font-semibold tabular-nums text-white">{{ stats.overdue }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-[10px] border border-[#2a3340] bg-[#1a222c] px-3 py-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#7C6BA8]/25 text-[#B8A8E0]">
                            <el-icon :size="18"><Flag /></el-icon>
                        </span>
                        <div>
                            <p class="text-[11px] text-[#8B9AAB]">Alta / urgente</p>
                            <p class="text-lg font-semibold tabular-nums text-white">{{ stats.urgent }}</p>
                        </div>
                    </div>
                </div>

                <!-- Filtros (estilo mockup: label + Todos/Todas) -->
                <div class="board-filters mx-4 mb-3 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-[10px] border border-[#2a3340] bg-[#1a222c] px-3 py-2.5 sm:mx-5">
                    <el-input
                        v-model="search"
                        size="small"
                        clearable
                        placeholder="Buscar en el tablero…"
                        class="board-filter-search w-full max-w-[200px] sm:max-w-[240px]"
                    >
                        <template #prefix>
                            <el-icon class="text-[#8B9AAB]"><Search /></el-icon>
                        </template>
                    </el-input>

                    <div class="hidden h-6 w-px bg-[#2a3340] sm:block" />

                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                        <div class="flex items-center gap-1.5">
                            <span class="shrink-0 text-xs text-[#8B9AAB]">Responsable</span>
                            <el-select
                                :model-value="memberFilter?.id"
                                size="small"
                                clearable
                                placeholder="Todos"
                                class="board-filter-select !w-[128px]"
                                @update:model-value="onMemberFilter"
                            >
                                <el-option
                                    v-for="u in usuarios"
                                    :key="u.id"
                                    :label="u.name"
                                    :value="u.id"
                                />
                            </el-select>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <span class="shrink-0 text-xs text-[#8B9AAB]">Etiqueta</span>
                            <el-select
                                :model-value="labelFilter?.id"
                                size="small"
                                clearable
                                placeholder="Todas"
                                class="board-filter-select !w-[120px]"
                                @update:model-value="onLabelFilter"
                            >
                                <el-option
                                    v-for="et in etiquetas"
                                    :key="et.id"
                                    :label="et.nombre"
                                    :value="et.id"
                                />
                            </el-select>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <span class="shrink-0 text-xs text-[#8B9AAB]">Prioridad</span>
                            <el-select
                                :model-value="priorityFilter"
                                size="small"
                                clearable
                                placeholder="Todas"
                                class="board-filter-select !w-[112px]"
                                @update:model-value="onPriorityFilter"
                            >
                                <el-option
                                    v-for="p in prioridades"
                                    :key="p.value"
                                    :label="p.label"
                                    :value="p.value"
                                />
                            </el-select>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="ml-auto inline-flex items-center gap-1.5 rounded-[8px] border border-[#2a3340] px-2.5 py-1.5 text-xs font-medium text-[#8B9AAB] transition-colors hover:border-[#3d4a58] hover:bg-white/5 hover:text-[#E8EEF4] disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="!hasFilter"
                        @click="clearFilters"
                    >
                        <el-icon :size="14"><Filter /></el-icon>
                        Limpiar filtros
                    </button>
                </div>
                <p v-if="hasFilter && canDrag" class="mb-2 px-4 text-[11px] text-[#B8A06A] sm:px-5">
                    Quita filtros para arrastrar
                </p>

                <!-- Kanban -->
                <div class="board-columns min-h-0 flex-1 overflow-auto px-4 pb-2 sm:px-5">
                    <section
                        v-for="estado in estados"
                        :key="estado.id"
                        class="board-column flex min-h-0 flex-col rounded-[10px] border border-[#2a3340] bg-[#1a222c]"
                    >
                        <header class="flex items-center gap-2 px-3 py-3">
                            <span class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: estado.color }" />
                            <h2 class="min-w-0 flex-1 truncate text-sm font-medium text-white">{{ estado.nombre }}</h2>
                            <span class="rounded-md bg-white/5 px-1.5 py-0.5 text-[11px] tabular-nums text-[#8B9AAB]">
                                {{ visibleCount(estado.id) }}
                            </span>
                            <el-dropdown trigger="click" @command="(c) => onListCommand(c, estado)">
                                <el-button text circle size="small" class="!text-[#8B9AAB]">
                                    <el-icon><MoreFilled /></el-icon>
                                </el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item v-if="canCreate" command="add">Nueva tarjeta</el-dropdown-item>
                                        <el-dropdown-item command="copy">Copiar títulos</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </header>

                        <div class="flex min-h-0 flex-1 flex-col overflow-y-auto px-2 pb-2">
                            <VueDraggable
                                v-model="board[estado.id]"
                                group="trello-cards"
                                :disabled="!dragEnabled"
                                class="flex min-h-[40px] flex-col gap-2"
                                ghost-class="trello-ghost"
                                drag-class="trello-drag"
                                :animation="160"
                                :empty-insert-threshold="40"
                                @start="dragging = true"
                                @end="dragging = false"
                                @change="() => onDragChange(estado.id)"
                            >
                                <article
                                    v-for="card in board[estado.id]"
                                    v-show="matches(card)"
                                    :key="card.id"
                                    class="board-card cursor-pointer border border-[#2a3340] bg-[#222a33] p-3 transition-colors duration-150 hover:border-[#3d4a58] hover:bg-[#28313c]"
                                    :class="{ 'cursor-grab active:cursor-grabbing': dragEnabled }"
                                    :style="{ borderLeftColor: card.prioridad_color || '#5B6B7C' }"
                                    @click="openCard(card.id)"
                                >
                                    <p class="text-sm font-medium leading-snug text-white">{{ card.titulo }}</p>

                                    <div v-if="card.etiquetas?.length" class="mt-2 flex flex-wrap gap-1">
                                        <span
                                            v-for="et in card.etiquetas"
                                            :key="et.id"
                                            class="label-chip"
                                            :style="chipVars(et.color)"
                                        >
                                            {{ et.nombre }}
                                        </span>
                                    </div>

                                    <div class="mt-2.5 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[11px] text-[#8B9AAB]">
                                        <span class="inline-flex items-center gap-1.5">
                                            <span
                                                class="h-1.5 w-1.5 rounded-full"
                                                :style="{ backgroundColor: card.label_color || '#579DFF' }"
                                            />
                                            {{ card.departamento || 'Sin área' }}
                                        </span>
                                        <span
                                            v-if="card.fecha_entrega_label"
                                            class="inline-flex items-center gap-1"
                                            :class="card.overdue ? 'text-[#E08A84]' : ''"
                                        >
                                            <el-icon :size="12"><Calendar /></el-icon>
                                            {{ card.fecha_entrega_label }}
                                        </span>
                                        <div class="ml-auto flex items-center gap-2">
                                            <el-avatar :size="18" :src="avatarFor(cardOwner(card))" />
                                            <span
                                                v-if="card.comentarios_count"
                                                class="inline-flex items-center gap-1"
                                            >
                                                <el-icon :size="12"><ChatDotRound /></el-icon>
                                                {{ card.comentarios_count }}
                                            </span>
                                        </div>
                                    </div>
                                </article>
                            </VueDraggable>

                            <div
                            v-if="!visibleCount(estado.id) && addingTo !== estado.id"
                            class="mt-1 flex max-h-[130px] flex-col items-center justify-center px-3 py-5 text-center"
                        >
                            <el-icon :size="22" class="mb-2 text-[#3d4a58]"><Tickets /></el-icon>
                            <p class="text-xs font-medium text-[#8B9AAB]">Sin tarjetas</p>
                            <p class="mt-0.5 text-[11px] text-[#5a6a7a]">Esta columna está vacía</p>
                            <button
                                v-if="canCreate"
                                type="button"
                                class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-[#579DFF] hover:underline"
                                @click="startAdd(estado.id)"
                            >
                                <el-icon :size="12"><Plus /></el-icon>
                                Nueva tarjeta
                            </button>
                        </div>

                            <div v-if="addingTo === estado.id" class="mt-2 space-y-2">
                                <textarea
                                    :id="`quick-add-${estado.id}`"
                                    v-model="draftTitle"
                                    rows="2"
                                    class="w-full resize-none rounded-[10px] border border-[#2a3340] bg-[#222a33] p-2 text-sm text-white placeholder:text-[#8B9AAB]/70 focus:outline-none focus:ring-1 focus:ring-[#579DFF]"
                                    placeholder="Título de la incidencia…"
                                    @keydown.enter.prevent="submitQuick(estado.id)"
                                    @keydown.esc="cancelAdd"
                                />
                                <div class="flex items-center gap-2">
                                    <el-button type="primary" size="small" @click="submitQuick(estado.id)">Crear</el-button>
                                    <el-button text circle size="small" class="!text-[#8B9AAB]" @click="cancelAdd">
                                        <el-icon><Close /></el-icon>
                                    </el-button>
                                </div>
                            </div>

                            <button
                                v-else-if="canCreate && visibleCount(estado.id)"
                                type="button"
                                class="mt-2 flex w-full items-center gap-2 rounded-[10px] px-2 py-2 text-sm text-[#8B9AAB] transition-colors hover:bg-[#222a33] hover:text-white"
                                @click="startAdd(estado.id)"
                            >
                                <el-icon><Plus /></el-icon>
                                Nueva tarjeta
                            </button>
                        </div>
                    </section>
                </div>

                <p class="shrink-0 px-4 py-2 text-center text-[11px] text-[#5a6a7a] sm:px-5">
                    Arrastra tarjetas entre columnas para actualizar su estado
                </p>
            </div>

            <!-- Panel derecho: actividad (desplegable) -->
            <aside
                class="hidden shrink-0 flex-col border-l border-[#2a3340] bg-[#12171d] transition-[width] duration-200 ease-out xl:flex"
                :class="activityPanelOpen ? 'w-[280px]' : 'w-11'"
            >
                <!-- Rail cuando está oculto -->
                <button
                    v-if="!activityPanelOpen"
                    type="button"
                    class="flex h-full w-full flex-col items-center gap-3 py-4 text-[#8B9AAB] transition-colors hover:bg-white/5 hover:text-[#85B8FF]"
                    title="Mostrar actividad reciente"
                    @click="activityPanelOpen = true"
                >
                    <el-icon :size="18"><DArrowLeft /></el-icon>
                    <span
                        class="text-[10px] font-semibold uppercase tracking-wider"
                        style="writing-mode: vertical-rl; transform: rotate(180deg)"
                    >
                        Actividad
                    </span>
                    <span
                        v-if="recentActivity.length"
                        class="flex h-5 min-w-5 items-center justify-center rounded-full bg-[#579DFF]/20 px-1 text-[10px] font-semibold text-[#85B8FF]"
                    >
                        {{ recentActivity.length }}
                    </span>
                </button>

                <div v-else class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#8B9AAB]">Panel</p>
                        <button
                            type="button"
                            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-[#8B9AAB] transition-colors hover:bg-white/5 hover:text-white"
                            title="Ocultar panel"
                            @click="activityPanelOpen = false"
                        >
                            <el-icon :size="16"><DArrowRight /></el-icon>
                        </button>
                    </div>

                    <!-- Actividad reciente (acordeón) -->
                    <section class="rounded-[10px] border border-[#2a3340] bg-[#1a222c]">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-3 py-2.5 text-left"
                            @click="activityExpanded = !activityExpanded"
                        >
                            <el-icon
                                :size="14"
                                class="shrink-0 text-[#8B9AAB] transition-transform duration-200"
                                :class="activityExpanded ? 'rotate-90' : ''"
                            >
                                <ArrowRight />
                            </el-icon>
                            <h3 class="min-w-0 flex-1 text-sm font-semibold text-white">Actividad reciente</h3>
                            <span
                                v-if="recentActivity.length"
                                class="rounded-full bg-[#579DFF]/15 px-1.5 py-0.5 text-[10px] font-medium text-[#85B8FF]"
                            >
                                {{ recentActivity.length }}
                            </span>
                        </button>
                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="opacity-0 -translate-y-1"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition duration-100 ease-in"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <div v-if="activityExpanded" class="border-t border-[#2a3340] px-3 pb-3 pt-2">
                                <div class="mb-2 flex justify-end">
                                    <button
                                        type="button"
                                        class="text-[11px] text-[#85B8FF] hover:underline"
                                        @click="router.visit('/tickets')"
                                    >
                                        Ver todo
                                    </button>
                                </div>
                                <ul v-if="recentActivity.length" class="space-y-3">
                                    <li
                                        v-for="item in recentActivity"
                                        :key="item.id"
                                        class="flex gap-2.5"
                                    >
                                        <span
                                            class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
                                            :class="toneClass[item.tone] || toneClass.blue"
                                        >
                                            <el-icon :size="14"><component :is="item.icon" /></el-icon>
                                        </span>
                                        <button
                                            type="button"
                                            class="min-w-0 flex-1 text-left"
                                            :disabled="!item.ticketId"
                                            @click="item.ticketId && openCard(item.ticketId)"
                                        >
                                            <p class="truncate text-xs font-medium text-white">{{ item.title }}</p>
                                            <p v-if="item.detail" class="truncate text-[11px] text-[#8B9AAB]">{{ item.detail }}</p>
                                            <p class="text-[10px] text-[#5a6a7a]">
                                                <span v-if="item.by">{{ item.by }} · </span>{{ item.when }}
                                            </p>
                                        </button>
                                    </li>
                                </ul>
                                <p
                                    v-else
                                    class="rounded-[8px] border border-dashed border-[#2a3340] px-3 py-5 text-center text-xs text-[#8B9AAB]"
                                >
                                    Sin actividad reciente
                                </p>
                            </div>
                        </Transition>
                    </section>

                    <!-- Resumen por prioridad (acordeón) -->
                    <section class="rounded-[10px] border border-[#2a3340] bg-[#1a222c]">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-3 py-2.5 text-left"
                            @click="priorityExpanded = !priorityExpanded"
                        >
                            <el-icon
                                :size="14"
                                class="shrink-0 text-[#8B9AAB] transition-transform duration-200"
                                :class="priorityExpanded ? 'rotate-90' : ''"
                            >
                                <ArrowRight />
                            </el-icon>
                            <h3 class="flex-1 text-sm font-semibold text-white">Resumen por prioridad</h3>
                        </button>
                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="opacity-0 -translate-y-1"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition duration-100 ease-in"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <div v-if="priorityExpanded" class="border-t border-[#2a3340] px-3 pb-3 pt-3">
                                <div class="flex items-center gap-4">
                                    <div class="relative h-20 w-20 shrink-0 rounded-full" :style="donutStyle">
                                        <div class="absolute inset-3 rounded-full bg-[#1a222c]" />
                                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                                            <span class="text-sm font-semibold text-white">{{ stats.total }}</span>
                                            <span class="text-[9px] text-[#8B9AAB]">total</span>
                                        </div>
                                    </div>
                                    <ul class="min-w-0 flex-1 space-y-2">
                                        <li
                                            v-for="g in prioritySummary"
                                            :key="g.key"
                                            class="flex items-center justify-between gap-2 text-[11px]"
                                        >
                                            <span class="flex items-center gap-1.5 text-[#8B9AAB]">
                                                <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: g.color }" />
                                                {{ g.label }}
                                            </span>
                                            <span class="tabular-nums text-white">{{ g.count }} · {{ g.pct }}%</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </Transition>
                    </section>
                </div>
            </aside>
        </div>

        <CardModal
            v-if="selectedId"
            :ticket-id="selectedId"
            :estados="estados"
            :departamentos="departamentos"
            :usuarios="usuarios"
            :etiquetas="etiquetas"
            :prioridades="prioridades"
            @close="closeCard"
            @updated="onCardUpdated"
            @deleted="onCardDeleted"
        />
</template>
