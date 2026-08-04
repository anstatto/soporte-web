<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { usePermissions } from '@/Composables/usePermissions';
import FileViewer from '@/Components/Files/FileViewer.vue';

const open = ref(false);
const page = usePage();
const { can, isSoporte } = usePermissions();

const catalog = computed(() => page.props.catalog || {});
const departamentos = computed(() => catalog.value.departamentos || []);
const agentes = computed(() => catalog.value.agentes || []);

const pendingFiles = ref([]);
const viewerOpen = ref(false);
const viewerFile = ref(null);

const form = useForm({
    titulo: '',
    descripcion: '',
    departamento_id: null,
    user_ids: [],
    adjuntos: [],
});

const ACCEPTED = '.jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx';

const kindOf = (file) => {
    const n = (file.name || '').toLowerCase();
    const t = file.type || '';
    if (t.startsWith('image/') || /\.(jpe?g|png|gif|webp)$/.test(n)) return 'image';
    if (t === 'application/pdf' || n.endsWith('.pdf')) return 'pdf';
    if (n.endsWith('.doc') || n.endsWith('.docx') || t.includes('word')) return 'word';
    return 'other';
};

const formatSize = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const reset = () => {
    form.reset();
    form.clearErrors();
    form.titulo = '';
    form.descripcion = '';
    form.departamento_id = page.props.auth?.user?.departamento_id
        || departamentos.value[0]?.id
        || null;
    form.user_ids = [];
    form.adjuntos = [];
    pendingFiles.value.forEach((f) => f.previewUrl && URL.revokeObjectURL(f.previewUrl));
    pendingFiles.value = [];
};

const show = () => {
    if (!can('create tickets')) return;
    reset();
    open.value = true;
};

watch(departamentos, (list) => {
    if (!form.departamento_id && list.length) {
        form.departamento_id = page.props.auth?.user?.departamento_id || list[0].id;
    }
});

const onPickFiles = (e) => {
    const list = [...(e.target.files || [])];
    e.target.value = '';
    addFiles(list);
};

const addFiles = (list) => {
    const room = 8 - pendingFiles.value.length;
    list.slice(0, room).forEach((file) => {
        const kind = kindOf(file);
        const previewUrl = kind === 'image' ? URL.createObjectURL(file) : null;
        pendingFiles.value.push({
            id: `${file.name}-${file.size}-${Date.now()}-${Math.random()}`,
            file,
            kind,
            previewUrl,
        });
    });
    form.adjuntos = pendingFiles.value.map((p) => p.file);
};

const removeFile = (id) => {
    const idx = pendingFiles.value.findIndex((p) => p.id === id);
    if (idx < 0) return;
    const [removed] = pendingFiles.value.splice(idx, 1);
    if (removed.previewUrl) URL.revokeObjectURL(removed.previewUrl);
    form.adjuntos = pendingFiles.value.map((p) => p.file);
};

const previewPending = (item) => {
    viewerFile.value = {
        url: item.previewUrl || (item.kind === 'image' ? null : URL.createObjectURL(item.file)),
        nombre: item.file.name,
        kind: item.kind,
        mime: item.file.type,
        _revoke: !item.previewUrl,
    };
    viewerOpen.value = true;
};

watch(viewerOpen, (v) => {
    if (!v && viewerFile.value?._revoke && viewerFile.value.url) {
        URL.revokeObjectURL(viewerFile.value.url);
        viewerFile.value = null;
    }
});

const onDrop = (e) => {
    e.preventDefault();
    addFiles([...(e.dataTransfer?.files || [])]);
};

const submit = () => {
    form.adjuntos = pendingFiles.value.map((p) => p.file);
    form
        .transform((data) => ({
            ...data,
            user_ids: isSoporte.value ? data.user_ids : undefined,
        }))
        .post('/tickets', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
                reset();
            },
        });
};

const onExternalOpen = () => show();

onMounted(() => {
    window.addEventListener('soporte:open-create-ticket', onExternalOpen);
});
onUnmounted(() => {
    window.removeEventListener('soporte:open-create-ticket', onExternalOpen);
    reset();
});

defineExpose({ show });
</script>

<template>
    <el-dialog
        v-model="open"
        :title="isSoporte ? 'Nueva incidencia' : 'Reportar un problema'"
        width="560px"
        destroy-on-close
        append-to-body
        align-center
    >
        <p class="mb-4 text-sm text-[#8B9AAB]">
            {{ isSoporte
                ? 'Describe el caso, adjunta evidencias y déjala sin asignar si quieres tomarla después.'
                : 'Cuéntanos el problema con detalle. Puedes adjuntar imágenes, PDF o Word; soporte responderá en el chat.' }}
        </p>
        <el-form label-position="top" @submit.prevent="submit">
            <el-form-item label="Asunto" :error="form.errors.titulo">
                <el-input v-model="form.titulo" maxlength="255" placeholder="¿Qué está pasando?" autofocus />
            </el-form-item>
            <el-form-item label="Detalles del problema" :error="form.errors.descripcion">
                <el-input
                    v-model="form.descripcion"
                    type="textarea"
                    :rows="6"
                    maxlength="5000"
                    show-word-limit
                    placeholder="Qué pasó, desde cuándo, pasos para reproducirlo, mensaje de error…"
                />
            </el-form-item>
            <el-form-item label="Departamento" :error="form.errors.departamento_id">
                <el-select v-model="form.departamento_id" class="w-full" filterable placeholder="Área">
                    <el-option
                        v-for="d in departamentos"
                        :key="d.id"
                        :label="d.nombre"
                        :value="d.id"
                    />
                </el-select>
            </el-form-item>
            <el-form-item
                v-if="isSoporte"
                label="Asignar (opcional)"
                :error="form.errors.user_ids"
            >
                <el-select
                    v-model="form.user_ids"
                    multiple
                    filterable
                    collapse-tags
                    clearable
                    class="w-full"
                    placeholder="Vacío = sin asignar"
                >
                    <el-option
                        v-for="u in agentes"
                        :key="u.id"
                        :label="u.name"
                        :value="u.id"
                    />
                </el-select>
            </el-form-item>

            <el-form-item label="Adjuntos" :error="form.errors.adjuntos || form.errors['adjuntos.0']">
                <div
                    class="w-full rounded-[10px] border border-dashed border-[#2a3340] bg-[#0f1419] px-3 py-4 text-center transition-colors hover:border-[#579DFF]/50"
                    @dragover.prevent
                    @drop="onDrop"
                >
                    <el-icon :size="22" class="text-[#8B9AAB]"><Paperclip /></el-icon>
                    <p class="mt-1 text-xs text-[#8B9AAB]">
                        Arrastra archivos o elige · imagen, PDF, Word · máx. 8 · 10 MB c/u
                    </p>
                    <label class="mt-2 inline-flex cursor-pointer">
                        <span class="rounded-md bg-[#1a222c] px-3 py-1.5 text-xs text-[#85B8FF] ring-1 ring-[#2a3340]">
                            Seleccionar archivos
                        </span>
                        <input
                            type="file"
                            class="hidden"
                            multiple
                            :accept="ACCEPTED"
                            @change="onPickFiles"
                        />
                    </label>
                </div>

                <ul v-if="pendingFiles.length" class="mt-3 w-full space-y-2">
                    <li
                        v-for="item in pendingFiles"
                        :key="item.id"
                        class="flex items-center gap-2 rounded-[10px] border border-[#2a3340] bg-[#1a222c] px-2.5 py-2"
                    >
                        <img
                            v-if="item.previewUrl"
                            :src="item.previewUrl"
                            class="h-10 w-10 rounded object-cover"
                            alt=""
                        />
                        <span
                            v-else
                            class="flex h-10 w-10 items-center justify-center rounded bg-[#579DFF]/15 text-[10px] font-semibold uppercase text-[#85B8FF]"
                        >
                            {{ item.kind }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-medium text-white">{{ item.file.name }}</p>
                            <p class="text-[10px] text-[#8B9AAB]">{{ formatSize(item.file.size) }}</p>
                        </div>
                        <el-button text type="primary" size="small" @click="previewPending(item)">
                            Ver
                        </el-button>
                        <el-button text type="danger" size="small" @click="removeFile(item.id)">
                            Quitar
                        </el-button>
                    </li>
                </ul>
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="open = false">Cancelar</el-button>
            <el-button type="primary" :loading="form.processing" @click="submit">
                {{ isSoporte ? 'Registrar' : 'Enviar reporte' }}
            </el-button>
        </template>
    </el-dialog>

    <FileViewer v-model="viewerOpen" :file="viewerFile" />
</template>
