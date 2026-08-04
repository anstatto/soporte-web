<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ElMessageBox } from 'element-plus';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ departamentos: Object, filters: Object });
const { can } = usePermissions();
const search = ref(props.filters?.search || '');
watch(search, useDebounceFn(() => {
    router.get('/departamentos', { search: search.value || undefined }, { preserveState: true, replace: true });
}, 300));

const dialogOpen = ref(false);
const editing = ref(null);
const form = useForm({ nombre: '' });

const openCreate = () => {
    editing.value = null;
    form.clearErrors();
    form.nombre = '';
    dialogOpen.value = true;
};

const openEdit = (row) => {
    editing.value = row;
    form.clearErrors();
    form.nombre = row.nombre;
    dialogOpen.value = true;
};

const submit = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => { dialogOpen.value = false; form.reset(); },
    };
    if (editing.value) {
        form.put(`/departamentos/${editing.value.id}`, opts);
    } else {
        form.post('/departamentos', opts);
    }
};

const destroy = async (id) => {
    try {
        await ElMessageBox.confirm(
            'Se eliminará el departamento. Los usuarios asociados quedarán sin depto.',
            'Eliminar departamento',
            { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' },
        );
        router.delete(`/departamentos/${id}`, { preserveScroll: true });
    } catch { /* */ }
};
</script>

<template>
    <Head title="Departamentos" />
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-display text-2xl font-semibold text-white">Departamentos</h2>
            <p class="text-sm text-[#8B9AAB]">
                Organiza usuarios e incidencias por área funcional (Sistemas, RRHH…).
            </p>
        </div>
        <el-button v-if="can('create departamento')" type="primary" @click="openCreate">
            <el-icon class="mr-1"><Plus /></el-icon>
            Nuevo departamento
        </el-button>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2 rounded-[10px] border border-[#2a3340] bg-[#1a222c] px-3 py-2.5">
        <el-input
            v-model="search"
            clearable
            placeholder="Buscar departamento…"
            class="w-full max-w-sm"
        >
            <template #prefix>
                <el-icon><Search /></el-icon>
            </template>
        </el-input>
    </div>

    <div
        v-if="!departamentos.data?.length"
        class="rounded-[12px] border border-dashed border-[#2a3340] bg-[#12171d] px-6 py-12 text-center"
    >
        <el-icon :size="28" class="text-[#8B9AAB]"><OfficeBuilding /></el-icon>
        <p class="mt-3 font-medium text-[#E8EEF4]">Aún no hay departamentos</p>
        <p class="mt-1 text-sm text-[#8B9AAB]">Crea el primero para clasificar tickets y usuarios.</p>
        <el-button
            v-if="can('create departamento')"
            class="mt-4"
            type="primary"
            @click="openCreate"
        >
            Crear departamento
        </el-button>
    </div>

    <div v-else class="overflow-hidden rounded-[10px] border border-[#2a3340]">
        <el-table :data="departamentos.data" class="w-full">
            <el-table-column label="Nombre" min-width="240">
                <template #default="{ row }">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#579DFF]/15 text-xs font-semibold text-[#85B8FF]"
                        >
                            {{ (row.nombre || '?').slice(0, 2).toUpperCase() }}
                        </span>
                        <span class="font-medium text-[#E8EEF4]">{{ row.nombre }}</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column label="" width="180" align="right">
                <template #default="{ row }">
                    <el-button
                        v-if="can('edit departamento')"
                        text
                        type="primary"
                        @click="openEdit(row)"
                    >
                        Editar
                    </el-button>
                    <el-button
                        v-if="can('delete departamento')"
                        text
                        type="danger"
                        @click="destroy(row.id)"
                    >
                        Eliminar
                    </el-button>
                </template>
            </el-table-column>
        </el-table>
    </div>

    <el-dialog
        v-model="dialogOpen"
        :title="editing ? 'Editar departamento' : 'Nuevo departamento'"
        width="420px"
        destroy-on-close
        align-center
    >
        <el-form label-position="top" @submit.prevent="submit">
            <el-form-item label="Nombre" :error="form.errors.nombre">
                <el-input
                    v-model="form.nombre"
                    placeholder="Ej. Sistemas, Contabilidad…"
                    maxlength="80"
                    show-word-limit
                    autofocus
                    @keyup.enter="submit"
                />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="dialogOpen = false">Cancelar</el-button>
            <el-button type="primary" :loading="form.processing" @click="submit">Guardar</el-button>
        </template>
    </el-dialog>
</template>
