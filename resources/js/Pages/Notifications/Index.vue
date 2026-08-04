<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { timeAgo } from '@/Composables/useDate';

const props = defineProps({
    notifications: Array,
    filter: { type: String, default: 'all' },
});

const tab = ref(props.filter || 'all');

watch(tab, (v) => {
    router.get('/notificaciones', { filter: v === 'all' ? undefined : v }, {
        preserveState: true,
        replace: true,
    });
});

const label = (n) => {
    const d = n.data || {};
    if (d.message) return d.message;
    if (d.type === 'ticket_moved') return `${d.by || 'Alguien'} movió una tarjeta`;
    if (d.type === 'ticket_message') return `Nuevo mensaje · ${d.ticket_title}`;
    if (d.type === 'ticket_mentioned') return `${d.mentioned_by || d.by} te mencionó`;
    if (d.type === 'ticket_assigned') return `${d.assigned_by || d.by} te asignó un ticket`;
    if (d.type === 'ticket_created') return `Nueva solicitud · ${d.ticket_title}`;
    return d.ticket_title || 'Notificación';
};

const open = async (n) => {
    try {
        if (!n.read_at) {
            await axios.post(`/notifications/${n.id}/mark-as-read`);
        }
    } catch { /* */ }
    const id = n.data?.ticket_id;
    if (id) router.visit(`/tickets/board?card=${id}`);
    else router.reload({ only: ['notifications'] });
};

const markAll = async () => {
    try {
        await axios.post('/notifications/mark-all-read');
        router.reload({ only: ['notifications'] });
    } catch { /* */ }
};

const unread = computed(() => (props.notifications || []).filter((n) => !n.read_at).length);
</script>

<template>
    <Head title="Notificaciones" />
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-display text-2xl font-semibold text-white">Notificaciones</h2>
            <p class="text-sm text-[#8B9AAB]">
                {{ unread }} sin leer · historial de asignaciones, movimientos y mensajes
            </p>
        </div>
        <el-button v-if="unread" @click="markAll">Marcar todas leídas</el-button>
    </div>

    <el-radio-group v-model="tab" size="small" class="mb-4">
        <el-radio-button value="all">Todas</el-radio-button>
        <el-radio-button value="unread">Sin leer</el-radio-button>
        <el-radio-button value="read">Leídas</el-radio-button>
    </el-radio-group>

    <div class="overflow-hidden rounded-[10px] border border-[#2a3340]">
        <button
            v-for="n in notifications"
            :key="n.id"
            type="button"
            class="flex w-full items-start gap-3 border-b border-[#2a3340]/80 px-4 py-3.5 text-left transition hover:bg-white/5 last:border-0"
            :class="!n.read_at ? 'bg-[#579DFF]/5' : ''"
            @click="open(n)"
        >
            <span
                class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                :class="n.read_at ? 'bg-[#2a3340]' : 'bg-[#579DFF]'"
            />
            <div class="min-w-0 flex-1">
                <p class="font-medium text-[#E8EEF4]">{{ label(n) }}</p>
                <p v-if="n.data?.excerpt" class="mt-0.5 truncate text-sm text-[#8B9AAB]">{{ n.data.excerpt }}</p>
                <p v-else-if="n.data?.ticket_title" class="mt-0.5 truncate text-sm text-[#8B9AAB]">
                    {{ n.data.ticket_title }}
                </p>
                <p class="mt-1 text-[11px] text-[#8B9AAB]">{{ timeAgo(n.created_at) }}</p>
            </div>
        </button>
        <p v-if="!notifications?.length" class="px-4 py-16 text-center text-sm text-[#8B9AAB]">
            No hay notificaciones en este filtro.
        </p>
    </div>

    <p class="mt-4 text-center text-xs text-[#8B9AAB]">
        <Link href="/portal" class="text-[#85B8FF] hover:underline">Ir al portal</Link>
    </p>
</template>
