<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ElMessageBox } from 'element-plus';

const props = defineProps({
    workspaces: Array,
    allUsers: Array,
});

const dialogOpen = ref(false);
const editing = ref(null);
const form = useForm({
    name: '',
    description: '',
    is_active: true,
    user_ids: [],
});

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.name = '';
    form.description = '';
    form.is_active = true;
    form.user_ids = [];
    dialogOpen.value = true;
};

const openEdit = (ws) => {
    editing.value = ws;
    form.clearErrors();
    form.name = ws.name;
    form.description = ws.description || '';
    form.is_active = ws.is_active;
    form.user_ids = [...(ws.user_ids || [])];
    dialogOpen.value = true;
};

const submit = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => { dialogOpen.value = false; form.reset(); },
    };
    if (editing.value) {
        form.put(`/workspaces/${editing.value.id}`, opts);
    } else {
        form.post('/workspaces', opts);
    }
};

const destroy = async (ws) => {
    try {
        await ElMessageBox.confirm(`¿Eliminar el área «${ws.name}»?`, 'Confirmar', {
            type: 'warning',
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
        });
        router.delete(`/workspaces/${ws.id}`, { preserveScroll: true });
    } catch { /* */ }
};
</script>

<template>
    <Head title="Áreas de trabajo" />
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-display text-2xl font-semibold text-white">Áreas de trabajo</h2>
            <p class="text-sm text-[#8B9AAB]">
                Cada área aísla usuarios e incidencias. Cambia de área desde el selector del menú.
            </p>
        </div>
        <el-button type="primary" @click="openCreate">
            <el-icon class="mr-1"><Plus /></el-icon>
            Nueva área
        </el-button>
    </div>

    <div
        v-if="!workspaces?.length"
        class="rounded-[12px] border border-dashed border-[#2a3340] bg-[#12171d] px-6 py-12 text-center"
    >
        <el-icon :size="28" class="text-[#8B9AAB]"><FolderOpened /></el-icon>
        <p class="mt-3 font-medium text-[#E8EEF4]">No hay áreas todavía</p>
        <p class="mt-1 text-sm text-[#8B9AAB]">Crea la primera para separar equipos o sedes.</p>
        <el-button class="mt-4" type="primary" @click="openCreate">Crear área</el-button>
    </div>

    <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <button
            v-for="ws in workspaces"
            :key="ws.id"
            type="button"
            class="rounded-[12px] border border-[#2a3340] bg-[#12171d] p-4 text-left transition-colors hover:border-[#3d4a5c] hover:bg-[#151b22]"
            @click="openEdit(ws)"
        >
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h3 class="font-display text-lg font-semibold text-white">{{ ws.name }}</h3>
                    <p class="text-xs text-[#8B9AAB]">{{ ws.description || 'Sin descripción' }}</p>
                </div>
                <el-tag size="small" :type="ws.is_active ? 'success' : 'info'" effect="dark">
                    {{ ws.is_active ? 'Activa' : 'Inactiva' }}
                </el-tag>
            </div>
            <p class="mt-3 text-sm text-[#8B9AAB]">
                {{ ws.users_count }} usuarios · {{ ws.tickets_count }} tickets
            </p>
            <div class="mt-3 flex flex-wrap gap-1">
                <el-tag
                    v-for="u in (ws.users || []).slice(0, 5)"
                    :key="u.id"
                    size="small"
                    effect="dark"
                    type="info"
                >
                    {{ u.name }}
                </el-tag>
                <span v-if="(ws.users || []).length > 5" class="text-xs text-[#8B9AAB]">
                    +{{ ws.users.length - 5 }}
                </span>
            </div>
            <div class="mt-4 flex gap-2" @click.stop>
                <el-button text type="primary" size="small" @click="openEdit(ws)">Editar</el-button>
                <el-button text type="danger" size="small" @click="destroy(ws)">Eliminar</el-button>
            </div>
        </button>
    </div>

    <el-dialog
        v-model="dialogOpen"
        :title="editing ? 'Editar área' : 'Nueva área de trabajo'"
        width="520px"
        destroy-on-close
        align-center
    >
        <el-form label-position="top" @submit.prevent="submit">
            <el-form-item label="Nombre" :error="form.errors.name">
                <el-input v-model="form.name" placeholder="Ej. Planta Norte" />
            </el-form-item>
            <el-form-item label="Descripción" :error="form.errors.description">
                <el-input v-model="form.description" type="textarea" :rows="2" />
            </el-form-item>
            <el-form-item v-if="editing" label="Estado">
                <el-switch v-model="form.is_active" active-text="Activa" inactive-text="Inactiva" />
            </el-form-item>
            <el-form-item label="Miembros" :error="form.errors.user_ids">
                <el-select
                    v-model="form.user_ids"
                    multiple
                    filterable
                    collapse-tags
                    collapse-tags-tooltip
                    class="w-full"
                    placeholder="Usuarios del área"
                >
                    <el-option
                        v-for="u in allUsers"
                        :key="u.id"
                        :label="`${u.name} (@${u.username})`"
                        :value="u.id"
                    />
                </el-select>
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="dialogOpen = false">Cancelar</el-button>
            <el-button type="primary" :loading="form.processing" @click="submit">Guardar</el-button>
        </template>
    </el-dialog>
</template>
