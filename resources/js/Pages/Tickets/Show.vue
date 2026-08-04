<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import axios from 'axios';
import { ElMessageBox } from 'element-plus';
import { formatDate, timeAgo } from '@/Composables/useDate';

const props = defineProps({
    ticket: Object,
    inbox: Array,
    estados: Array,
    usuarios: Array,
    canManage: Boolean,
    canChangeStatus: Boolean,
    canComment: Boolean,
});

const comments = ref([...props.ticket.comentarios]);
const threadRef = ref(null);
const estadoId = ref(props.ticket.estado?.id);
const assignedIds = ref(props.ticket.asignados?.map((u) => u.id) || []);
const commentText = ref('');
const pendingImage = ref(null);
const imagePreview = ref(null);
const sending = ref(false);

const scrollBottom = async () => {
    await nextTick();
    if (threadRef.value) {
        threadRef.value.scrollTop = threadRef.value.scrollHeight;
    }
};

onMounted(scrollBottom);
watch(comments, scrollBottom, { deep: true });

const jsonHeaders = () => ({
    'X-Requested-With': 'XMLHttpRequest',
    Accept: 'application/json',
});

const clearImage = () => {
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
    pendingImage.value = null;
    imagePreview.value = null;
};

const onPaste = (e) => {
    const items = e.clipboardData?.items;
    if (!items) return;
    for (const item of items) {
        if (item.type.startsWith('image/')) {
            e.preventDefault();
            const file = item.getAsFile();
            clearImage();
            pendingImage.value = file;
            imagePreview.value = URL.createObjectURL(file);
            return;
        }
    }
};

const send = async () => {
    const text = commentText.value.trim();
    if ((!text && !pendingImage.value) || sending.value) return;
    sending.value = true;
    const form = new FormData();
    if (text) form.append('contenido', text);
    if (pendingImage.value) form.append('imagen', pendingImage.value);
    try {
        const { data } = await axios.post(`/tickets/${props.ticket.id}/comentarios`, form, {
            headers: { ...jsonHeaders(), 'Content-Type': 'multipart/form-data' },
        });
        comments.value.push(data.comentario);
        commentText.value = '';
        clearImage();
    } catch {
        // ignore
    } finally {
        sending.value = false;
    }
};

const lastId = () => comments.value[comments.value.length - 1]?.id || 0;

useIntervalFn(async () => {
    try {
        const { data } = await axios.get(`/tickets/${props.ticket.id}/comentarios/poll`, {
            params: { after: lastId() },
            headers: jsonHeaders(),
        });
        if (data.comentarios?.length) {
            const ids = new Set(comments.value.map((c) => c.id));
            comments.value.push(...data.comentarios.filter((c) => !ids.has(c.id)));
        }
    } catch {
        // ignore
    }
}, 5000);

watch(
    () => props.ticket.comentarios,
    (val) => {
        comments.value = [...val];
    },
);

const changeEstado = () => {
    router.patch(`/tickets/${props.ticket.id}/estado`, { estado_id: estadoId.value }, { preserveScroll: true });
};

const saveAssign = () => {
    router.put(`/tickets/${props.ticket.id}`, {
        titulo: props.ticket.titulo,
        descripcion: props.ticket.descripcion,
        departamento_id: props.ticket.departamento?.id,
        estado_id: estadoId.value,
        fecha_entrega: props.ticket.fecha_entrega,
        recordatorio: props.ticket.recordatorio,
        user_ids: assignedIds.value,
    }, { preserveScroll: true });
};

const removeTicket = async () => {
    try {
        await ElMessageBox.confirm('Esta acción no se puede deshacer', '¿Eliminar ticket?', {
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            type: 'warning',
        });
        router.delete(`/tickets/${props.ticket.id}`);
    } catch {
        // cancelado
    }
};
</script>

<template>
    <Head :title="ticket.titulo" />

        <div class="flex h-[calc(100vh-8rem)] overflow-hidden rounded-lg border border-brand-border bg-brand-card">
            <!-- Inbox side -->
            <aside class="hidden w-72 shrink-0 flex-col border-r border-brand-border md:flex">
                <div class="border-b border-brand-border px-3 py-3 text-sm font-semibold">Conversaciones</div>
                <div class="flex-1 overflow-y-auto">
                    <Link
                        v-for="item in inbox"
                        :key="item.id"
                        :href="`/tickets/${item.id}`"
                        class="block border-b border-brand-border px-3 py-3 text-sm hover:bg-brand-surface"
                        :class="{ 'bg-chat-mine': item.id === ticket.id }"
                    >
                        <p class="truncate font-medium">{{ item.titulo }}</p>
                        <p class="text-xs text-brand-muted">{{ item.user?.name }} · {{ timeAgo(item.updated_at) }}</p>
                    </Link>
                </div>
            </aside>

            <!-- Thread -->
            <section class="flex min-w-0 flex-1 flex-col">
                <header class="flex items-start justify-between gap-3 border-b border-brand-border px-4 py-3">
                    <div>
                        <h2 class="font-display text-lg font-semibold">{{ ticket.titulo }}</h2>
                        <p class="text-xs text-brand-muted">
                            {{ ticket.user?.name }} · {{ ticket.departamento?.nombre }} · {{ formatDate(ticket.created_at) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-semibold text-white"
                            :style="{ backgroundColor: ticket.estado?.color || '#1E4E79' }"
                        >
                            {{ ticket.estado?.nombre }}
                        </span>
                        <Link v-if="canManage" :href="`/tickets/${ticket.id}/edit`">
                            <el-button size="small">Editar</el-button>
                        </Link>
                        <el-button v-if="canManage" circle text type="danger" @click="removeTicket">
                            <el-icon><Delete /></el-icon>
                        </el-button>
                    </div>
                </header>

                <div ref="threadRef" class="flex-1 space-y-3 overflow-y-auto bg-brand-surface/60 p-4">
                    <div
                        v-for="c in comments"
                        :key="c.id"
                        class="flex"
                        :class="c.mine ? 'justify-end' : 'justify-start'"
                    >
                        <div
                            class="max-w-[80%] rounded-2xl border px-4 py-2 shadow-sm"
                            :class="c.mine ? 'rounded-br-md border-brand-accent/20 bg-chat-mine' : 'rounded-bl-md border-brand-border bg-chat-other'"
                        >
                            <p class="text-xs font-semibold text-[#85B8FF]">{{ c.user?.name }}</p>
                            <p v-if="c.contenido" class="whitespace-pre-wrap text-sm text-white">{{ c.contenido }}</p>
                            <a v-if="c.imagen_url" :href="c.imagen_url" target="_blank" class="mt-2 block">
                                <img :src="c.imagen_url" alt="Captura" class="max-h-48 max-w-full rounded-md object-contain" />
                            </a>
                            <p class="mt-1 text-[10px] text-brand-muted">{{ formatDate(c.created_at) }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="canComment" class="space-y-2 border-t border-brand-border p-3">
                    <div v-if="imagePreview" class="relative inline-block">
                        <img :src="imagePreview" class="max-h-24 rounded border" alt="" />
                        <button type="button" class="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-[#C4554D] text-white" @click="clearImage">
                            <el-icon :size="12"><Close /></el-icon>
                        </button>
                    </div>
                    <form class="flex gap-2" @submit.prevent="send">
                        <el-input
                            v-model="commentText"
                            placeholder="Mensaje… (Ctrl+V pega captura)"
                            class="flex-1"
                            @paste="onPaste"
                        />
                        <el-button type="primary" :loading="sending" native-type="submit">
                            <el-icon><Promotion /></el-icon>
                        </el-button>
                    </form>
                </div>
            </section>

            <!-- Meta -->
            <aside class="hidden w-64 shrink-0 flex-col gap-4 border-l border-[#2a3340] p-4 lg:flex">
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-[#8B9AAB]">Estado</p>
                    <el-select
                        v-if="canChangeStatus"
                        v-model="estadoId"
                        class="w-full"
                        @change="changeEstado"
                    >
                        <el-option v-for="e in estados" :key="e.id" :label="e.nombre" :value="e.id" />
                    </el-select>
                    <p v-else class="mt-1 text-sm text-white">{{ ticket.estado?.nombre }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#8B9AAB]">Entrega</p>
                    <p class="mt-1 text-sm text-white">{{ formatDate(ticket.fecha_entrega, 'DD/MM/YYYY') }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#8B9AAB]">Recordatorio</p>
                    <p class="mt-1 text-sm text-white">{{ formatDate(ticket.recordatorio, 'DD/MM/YYYY') }}</p>
                </div>
                <div v-if="canChangeStatus">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-[#8B9AAB]">Asignados</p>
                    <el-select v-model="assignedIds" multiple filterable collapse-tags class="w-full">
                        <el-option v-for="u in usuarios" :key="u.id" :label="u.name" :value="u.id" />
                    </el-select>
                    <el-button class="mt-2 w-full" size="small" @click="saveAssign">Guardar asignación</el-button>
                </div>
                <div v-else>
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#8B9AAB]">Asignados</p>
                    <p class="mt-1 text-sm text-white">{{ ticket.asignados?.map(a => a.name).join(', ') || '—' }}</p>
                </div>
            </aside>
        </div>
</template>
