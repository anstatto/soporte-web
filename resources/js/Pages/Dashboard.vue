<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Doughnut, Bar, Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    ArcElement,
    CategoryScale,
    LinearScale,
    BarElement,
    PointElement,
    LineElement,
    Tooltip,
    Legend,
} from 'chart.js';
import { timeAgo } from '@/Composables/useDate';

ChartJS.register(ArcElement, CategoryScale, LinearScale, BarElement, PointElement, LineElement, Tooltip, Legend);

const props = defineProps({
    kpis: Object,
    byEstado: Array,
    byDepartamento: Array,
    trend: Array,
    recent: Array,
    isSoporte: Boolean,
});

const recentOpen = ref(true);

const estadoChart = computed(() => ({
    labels: props.byEstado.map((i) => i.estado),
    datasets: [{
        data: props.byEstado.map((i) => i.total),
        backgroundColor: props.byEstado.map((i) => i.color || '#579DFF'),
    }],
}));

const deptoChart = computed(() => ({
    labels: props.byDepartamento.map((i) => i.departamento),
    datasets: [{
        label: 'Tickets',
        data: props.byDepartamento.map((i) => i.total),
        backgroundColor: '#579DFF',
    }],
}));

const trendChart = computed(() => ({
    labels: props.trend.map((i) => i.day),
    datasets: [{
        label: 'Creados',
        data: props.trend.map((i) => i.total),
        borderColor: '#85B8FF',
        backgroundColor: 'rgba(87,157,255,0.15)',
        fill: true,
        tension: 0.3,
    }],
}));

const chartOpts = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: { color: '#8B9AAB' },
        },
    },
};
</script>

<template>
    <Head title="Inicio" />

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-display text-2xl font-semibold text-white">
                {{ isSoporte ? 'Panel de soporte' : 'Mis solicitudes' }}
            </h2>
            <p class="text-sm text-[#8B9AAB]">Resumen operativo de la mensajería interna</p>
        </div>
        <Link href="/tickets/create">
            <el-button type="primary">
                <el-icon class="mr-1"><Plus /></el-icon>
                Nueva solicitud
            </el-button>
        </Link>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-[10px] border border-[#2a3340] bg-[#12171d] p-4">
            <p class="text-sm text-[#8B9AAB]">Total</p>
            <p class="mt-1 font-display text-3xl font-bold text-[#85B8FF]">{{ kpis.total }}</p>
        </div>
        <div class="rounded-[10px] border border-[#2a3340] bg-[#12171d] p-4">
            <p class="text-sm text-[#8B9AAB]">Abiertos</p>
            <p class="mt-1 font-display text-3xl font-bold text-[#E0B45A]">{{ kpis.abiertos }}</p>
        </div>
        <div class="rounded-[10px] border border-[#2a3340] bg-[#12171d] p-4">
            <p class="text-sm text-[#8B9AAB]">Míos</p>
            <p class="mt-1 font-display text-3xl font-bold text-white">{{ kpis.mios }}</p>
        </div>
        <div class="rounded-[10px] border border-[#2a3340] bg-[#12171d] p-4">
            <p class="text-sm text-[#8B9AAB]">Mis mensajes</p>
            <p class="mt-1 font-display text-3xl font-bold text-[#85B8FF]">{{ kpis.comentarios }}</p>
        </div>
    </div>

    <div class="mt-6 grid gap-3 lg:grid-cols-3">
        <div class="rounded-[10px] border border-[#2a3340] bg-[#12171d] p-4">
            <h3 class="mb-3 font-display font-semibold text-white">Por estado</h3>
            <div class="h-56">
                <Doughnut v-if="byEstado.length" :data="estadoChart" :options="chartOpts" />
                <p v-else class="py-16 text-center text-sm text-[#8B9AAB]">Sin datos</p>
            </div>
        </div>
        <div class="rounded-[10px] border border-[#2a3340] bg-[#12171d] p-4">
            <h3 class="mb-3 font-display font-semibold text-white">Por departamento</h3>
            <div class="h-56">
                <Bar v-if="byDepartamento.length" :data="deptoChart" :options="{ ...chartOpts, plugins: { legend: { display: false } } }" />
                <p v-else class="py-16 text-center text-sm text-[#8B9AAB]">Sin datos</p>
            </div>
        </div>
        <div class="rounded-[10px] border border-[#2a3340] bg-[#12171d] p-4">
            <h3 class="mb-3 font-display font-semibold text-white">Últimos 30 días</h3>
            <div class="h-56">
                <Line v-if="trend.length" :data="trendChart" :options="{ ...chartOpts, plugins: { legend: { display: false } } }" />
                <p v-else class="py-16 text-center text-sm text-[#8B9AAB]">Sin datos</p>
            </div>
        </div>
    </div>

    <div class="mt-6 overflow-hidden rounded-[10px] border border-[#2a3340] bg-[#12171d]">
        <button
            type="button"
            class="flex w-full items-center gap-2 border-b border-[#2a3340] px-4 py-3 text-left hover:bg-white/[0.03]"
            @click="recentOpen = !recentOpen"
        >
            <el-icon
                :size="14"
                class="text-[#8B9AAB] transition-transform duration-200"
                :class="recentOpen ? 'rotate-90' : ''"
            >
                <ArrowRight />
            </el-icon>
            <h3 class="flex-1 font-display font-semibold text-white">Actividad reciente</h3>
            <span class="text-xs text-[#8B9AAB]">{{ recent.length }}</span>
        </button>
        <ul v-show="recentOpen">
            <li v-for="t in recent" :key="t.id" class="border-b border-[#2a3340]/80 last:border-0">
                <Link
                    :href="`/tickets/board?card=${t.id}`"
                    class="flex items-center gap-4 px-4 py-3 transition hover:bg-white/5"
                >
                    <span
                        class="h-2.5 w-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: t.estado_color || '#579DFF' }"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-white">{{ t.titulo }}</p>
                        <p class="text-xs text-[#8B9AAB]">
                            {{ t.user }} · {{ t.departamento }} · {{ t.comentarios_count }} msgs
                        </p>
                    </div>
                    <span class="text-xs text-[#8B9AAB]">{{ timeAgo(t.created_at) }}</span>
                </Link>
            </li>
            <li v-if="!recent.length" class="px-4 py-10 text-center text-sm text-[#8B9AAB]">
                No hay mensajes todavía.
            </li>
        </ul>
    </div>
</template>
