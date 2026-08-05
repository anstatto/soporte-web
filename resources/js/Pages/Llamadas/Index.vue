<script setup>
import { computed, inject } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

const props = defineProps({
    livekit: Object,
    canManage: Boolean,
    directory: { type: Array, default: () => [] },
    canChat: Boolean,
});

const appCall = inject('appCall', null);
const ready = computed(() => !!props.livekit?.enabled);
const allowVideo = computed(() => !!props.livekit?.allow_video);

const avatar = (name) =>
    `https://ui-avatars.com/api/?name=${encodeURIComponent(name || '?')}&background=579DFF&color=fff`;

const callUser = (userId, video = false) => {
    if (!ready.value) {
        toast.error('Las llamadas aún no están activas. Un admin debe configurar LiveKit en Ajustes.');
        return;
    }
    if (video && !allowVideo.value) {
        toast.message('Video desactivado (modo económico). Solo audio.');
        video = false;
    }
    if (!appCall) return;
    appCall.start({ calleeId: userId, video, context: null });
};
</script>

<template>
    <Head title="Llamadas" />

    <div class="mx-auto max-w-4xl space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-semibold text-white">Llamadas y videollamadas</h2>
                <p class="mt-1 text-sm text-[#8B9AAB]">
                    Llamadas 1 a 1 por audio o video dentro del chat. No hay salas de conferencia grupales todavía.
                </p>
            </div>
            <el-tag :type="ready ? 'success' : 'danger'" effect="dark" size="large">
                {{ ready ? 'Listo para llamar' : 'Pendiente de configurar' }}
            </el-tag>
        </div>

        <section class="rounded-[10px] border border-[#2a3340] bg-[#12171d] p-5">
            <div class="mb-3 flex items-center gap-2 text-white">
                <el-icon :size="18" class="text-[#57D9A3]"><Coin /></el-icon>
                <h3 class="font-medium">Plan gratis (Build)</h3>
            </div>
            <p class="text-sm text-[#8B9AAB]">
                {{ livekit.plan_hint || 'LiveKit Cloud Build: 0$/mes.' }}
                No usamos agentes IA ni telefonía SIP (eso gasta otros cupos).
                Preferimos <strong class="text-[#E8EEF4]">audio</strong>
                <template v-if="!allowVideo"> (video apagado ahora)</template>
                <template v-else> · video calidad {{ livekit.video_quality || 'low' }}</template>.
                <template v-if="livekit.max_call_minutes">
                    Corte automático a {{ livekit.max_call_minutes }} min por llamada.
                </template>
            </p>
        </section>

        <!-- Estado / setup -->
        <section class="rounded-[10px] border border-[#2a3340] bg-[#12171d] p-5">
            <div class="mb-3 flex items-center gap-2 text-white">
                <el-icon :size="18" class="text-[#579DFF]"><Phone /></el-icon>
                <h3 class="font-medium">Estado del servicio</h3>
            </div>

            <ul class="space-y-2 text-sm text-[#E8EEF4]">
                <li class="flex items-center gap-2">
                    <el-icon :class="livekit.flag ? 'text-[#57D9A3]' : 'text-[#EF5C48]'">
                        <component :is="livekit.flag ? 'CircleCheck' : 'CircleClose'" />
                    </el-icon>
                    Activado
                </li>
                <li class="flex items-center gap-2">
                    <el-icon :class="livekit.has_url ? 'text-[#57D9A3]' : 'text-[#EF5C48]'">
                        <component :is="livekit.has_url ? 'CircleCheck' : 'CircleClose'" />
                    </el-icon>
                    URL LiveKit
                    <span v-if="livekit.url_host" class="text-[#8B9AAB]">{{ livekit.url_host }}</span>
                </li>
                <li class="flex items-center gap-2">
                    <el-icon :class="livekit.has_key ? 'text-[#57D9A3]' : 'text-[#EF5C48]'">
                        <component :is="livekit.has_key ? 'CircleCheck' : 'CircleClose'" />
                    </el-icon>
                    API Key
                </li>
                <li class="flex items-center gap-2">
                    <el-icon :class="livekit.has_secret ? 'text-[#57D9A3]' : 'text-[#EF5C48]'">
                        <component :is="livekit.has_secret ? 'CircleCheck' : 'CircleClose'" />
                    </el-icon>
                    API Secret
                </li>
            </ul>

            <div v-if="!ready" class="mt-4 rounded-[10px] border border-[#EF5C48]/30 bg-[#EF5C48]/10 p-3 text-sm text-[#E8EEF4]">
                <p class="font-medium text-[#EF5C48]">Aún no puedes llamar</p>
                <p class="mt-1 text-[#8B9AAB]">
                    Un administrador debe ir a
                    <Link v-if="canManage" href="/ajustes" class="text-[#579DFF] underline">Configuración → Ajustes</Link>
                    <span v-else>Configuración → Ajustes</span>
                    y pegar las keys de
                    <a href="https://cloud.livekit.io" target="_blank" rel="noopener" class="text-[#579DFF] underline">LiveKit Cloud</a>
                    (plan gratis).
                </p>
                <el-button v-if="canManage" type="primary" class="mt-3" @click="router.visit('/ajustes')">
                    Ir a configurar llamadas
                </el-button>
            </div>
            <p v-else class="mt-4 text-sm text-[#8B9AAB]">
                Timeout de ringing: {{ livekit.ring_timeout }}s · Fuente: {{ livekit.source === 'database' ? 'Ajustes' : '.env' }}
            </p>
        </section>

        <!-- Cómo usar -->
        <section class="rounded-[10px] border border-[#2a3340] bg-[#12171d] p-5">
            <div class="mb-3 flex items-center gap-2 text-white">
                <el-icon :size="18" class="text-[#57D9A3]"><InfoFilled /></el-icon>
                <h3 class="font-medium">Dónde salen los botones</h3>
            </div>
            <ol class="list-decimal space-y-2 pl-5 text-sm text-[#E8EEF4]">
                <li>
                    <strong>Bandeja</strong> → abre un chat (incidencia o persona) → iconos
                    <el-icon class="mx-0.5 align-middle"><Phone /></el-icon>
                    y
                    <el-icon class="mx-0.5 align-middle"><VideoCamera /></el-icon>
                    junto al menú ⋮
                </li>
                <li>
                    <strong>Tablero</strong> → abre una tarjeta → sección Miembros → mismos iconos
                </li>
                <li>
                    Aquí abajo puedes iniciar una llamada directa si tienes permiso de chat
                </li>
            </ol>
            <div class="mt-4 flex flex-wrap gap-2">
                <el-button @click="router.visit('/tickets')">
                    <el-icon class="mr-1"><Message /></el-icon>
                    Ir a Bandeja
                </el-button>
                <el-button @click="router.visit('/tickets/board')">
                    <el-icon class="mr-1"><Grid /></el-icon>
                    Ir a Tablero
                </el-button>
            </div>
        </section>

        <!-- Directorio rápido -->
        <section v-if="directory.length" class="rounded-[10px] border border-[#2a3340] bg-[#12171d] p-5">
            <div class="mb-3 flex items-center gap-2 text-white">
                <el-icon :size="18" class="text-[#9F8FEF]"><User /></el-icon>
                <h3 class="font-medium">Llamar a alguien</h3>
            </div>
            <div class="divide-y divide-[#2a3340]">
                <div
                    v-for="u in directory"
                    :key="u.id"
                    class="flex items-center gap-3 py-2.5"
                >
                    <el-avatar :size="36" :src="avatar(u.name)" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-white">{{ u.name }}</p>
                        <p class="truncate text-[11px] text-[#8B9AAB]">@{{ u.username }}</p>
                    </div>
                    <el-button
                        circle
                        size="small"
                        :disabled="!ready"
                        title="Llamar"
                        @click="callUser(u.id, false)"
                    >
                        <el-icon><Phone /></el-icon>
                    </el-button>
                    <el-button
                        v-if="allowVideo"
                        circle
                        size="small"
                        :disabled="!ready"
                        title="Videollamada"
                        @click="callUser(u.id, true)"
                    >
                        <el-icon><VideoCamera /></el-icon>
                    </el-button>
                </div>
            </div>
        </section>

        <section v-else class="rounded-[10px] border border-[#2a3340] bg-[#12171d] p-5 text-sm text-[#8B9AAB]">
            No hay personas en el directorio de este espacio de trabajo, o no tienes permiso de chat.
            Usa la Bandeja para abrir un chat y llamar desde ahí.
        </section>
    </div>
</template>
