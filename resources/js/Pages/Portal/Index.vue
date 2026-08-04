<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { timeAgo } from '@/Composables/useDate';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    conversations: Array,
    flow: Array,
    unassignedCount: Number,
    isSoporte: Boolean,
});

const { can } = usePermissions();
const openCreate = () => window.dispatchEvent(new CustomEvent('soporte:open-create-ticket'));
const openCard = (id) => {
    if (props.isSoporte) {
        router.visit(`/tickets/board?card=${id}`);
    } else {
        router.visit(`/tickets?chat=${id}`);
    }
};
</script>

<template>
    <Head :title="isSoporte ? 'Portal de soporte' : 'Portal'" />

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="font-display text-2xl font-semibold text-white">
                {{ isSoporte ? 'Portal de soporte' : 'Tu portal' }}
            </h2>
            <p class="mt-1 max-w-xl text-sm text-[#8B9AAB]">
                {{ isSoporte
                    ? 'Flujo del equipo, cola sin asignar y conversaciones recientes.'
                    : 'Describe tu problema, síguelo por etapas y chatea con soporte.' }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <el-button v-if="can('create tickets')" type="primary" @click="openCreate">
                <el-icon class="mr-1"><EditPen /></el-icon>
                {{ isSoporte ? 'Nueva incidencia' : 'Reportar problema' }}
            </el-button>
            <Link href="/tickets">
                <el-button>{{ isSoporte ? 'Bandeja' : 'Mis chats' }}</el-button>
            </Link>
            <Link v-if="isSoporte" href="/tickets/board">
                <el-button>Tablero</el-button>
            </Link>
        </div>
    </div>

    <!-- Flujo de trabajo -->
    <section class="mb-6">
        <div class="mb-2 flex items-center justify-between">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-[#8B9AAB]">Flujo de trabajo</h3>
            <Link
                v-if="isSoporte && unassignedCount > 0"
                href="/tickets?sin_asignar=1"
                class="text-xs text-[#85B8FF] hover:underline"
            >
                {{ unassignedCount }} sin asignar →
            </Link>
        </div>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="(step, idx) in flow"
                :key="step.id"
                class="relative overflow-hidden rounded-[10px] border border-[#2a3340] bg-[#12171d] px-4 py-3"
            >
                <div class="absolute inset-x-0 top-0 h-0.5" :style="{ background: step.color || '#579DFF' }" />
                <p class="text-[11px] text-[#8B9AAB]">{{ idx + 1 }}. {{ step.nombre }}</p>
                <p class="mt-1 font-display text-2xl font-semibold text-white">{{ step.total }}</p>
            </div>
        </div>
    </section>

    <!-- Conversaciones / chats -->
    <section>
        <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-[#8B9AAB]">
            {{ isSoporte ? 'Conversaciones recientes' : 'Tus conversaciones con soporte' }}
        </h3>
        <div class="overflow-hidden rounded-[10px] border border-[#2a3340]">
            <button
                v-for="c in conversations"
                :key="c.id"
                type="button"
                class="flex w-full items-start gap-3 border-b border-[#2a3340]/80 px-4 py-3.5 text-left transition hover:bg-white/5 last:border-0"
                @click="openCard(c.id)"
            >
                <el-avatar
                    :size="36"
                    :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(c.user?.name || '?')}&background=579DFF&color=fff`"
                />
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <p class="truncate font-medium text-[#E8EEF4]">{{ c.titulo }}</p>
                        <span class="shrink-0 text-[11px] text-[#8B9AAB]">{{ timeAgo(c.last_activity) }}</span>
                    </div>
                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                        <el-tag size="small" :color="c.estado?.color" effect="dark" class="!border-0">
                            {{ c.estado?.nombre }}
                        </el-tag>
                        <el-tag v-if="c.sin_asignar" size="small" type="warning" effect="dark">Sin asignar</el-tag>
                        <span v-else class="text-[11px] text-[#8B9AAB]">
                            {{ c.asignados?.map((a) => a.name).join(', ') || '—' }}
                        </span>
                        <span class="text-[11px] text-[#8B9AAB]">· {{ c.comentarios_count }} msgs</span>
                    </div>
                </div>
                <el-icon class="mt-2 shrink-0 text-[#8B9AAB]"><ChatDotRound /></el-icon>
            </button>
            <div v-if="!conversations?.length" class="px-4 py-14 text-center">
                <p class="text-sm text-[#8B9AAB]">Aún no hay conversaciones.</p>
                <el-button v-if="can('create tickets')" class="mt-3" type="primary" @click="openCreate">
                    Escribir el primer problema
                </el-button>
            </div>
        </div>
    </section>
</template>
