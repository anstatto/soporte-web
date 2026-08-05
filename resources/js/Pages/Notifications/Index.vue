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

const typeMeta = (n) => {
    const type = n.data?.type || n.type || '';
    if (String(type).includes('message')) return { label: 'Mensaje', color: '#25D366' };
    if (String(type).includes('mentioned')) return { label: 'Mención', color: '#F5C518' };
    if (String(type).includes('assigned')) return { label: 'Asignación', color: '#579DFF' };
    if (String(type).includes('moved')) return { label: 'Movimiento', color: '#9F8FEF' };
    if (String(type).includes('created')) return { label: 'Nuevo', color: '#57D9A3' };
    return { label: 'Aviso', color: '#8B9AAB' };
};

const open = async (n) => {
    try {
        if (!n.read_at) {
            await axios.post(`/notifications/${n.id}/mark-as-read`);
        }
    } catch { /* */ }
    const id = n.data?.ticket_id;
    const type = n.data?.type || n.type || '';
    if (!id) {
        router.reload({ only: ['notifications'] });
        return;
    }
    const toInbox = ['ticket_message', 'ticket_mentioned', 'ticket_created'].some(
        (t) => String(type).includes(t),
    );
    router.visit(toInbox ? `/tickets?chat=${id}` : `/tickets/board?card=${id}`);
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
                <span v-if="unread" class="font-semibold text-[#579DFF]">{{ unread }} sin leer</span>
                <span v-else>Sin pendientes</span>
                · historial de asignaciones, movimientos y mensajes
            </p>
        </div>
        <el-button v-if="unread" type="primary" @click="markAll">Marcar todas leídas</el-button>
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
            :class="!n.read_at ? 'notif-row--unread' : ''"
            @click="open(n)"
        >
            <span
                class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full"
                :style="{ backgroundColor: n.read_at ? '#2a3340' : typeMeta(n).color }"
            />
            <div class="min-w-0 flex-1">
                <div class="mb-1 flex flex-wrap items-center gap-1.5">
                    <span
                        class="status-pill"
                        :style="{ backgroundColor: typeMeta(n).color }"
                    >
                        {{ typeMeta(n).label }}
                    </span>
                    <span
                        v-if="!n.read_at"
                        class="rounded-full bg-[#579DFF] px-1.5 py-0.5 text-[10px] font-bold text-white"
                    >
                        Nuevo
                    </span>
                </div>
                <p :class="!n.read_at ? 'font-semibold text-white' : 'font-medium text-[#E8EEF4]'">
                    {{ label(n) }}
                </p>
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
