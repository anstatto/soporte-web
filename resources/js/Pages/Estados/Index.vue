<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ElMessageBox } from 'element-plus';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ estados: Object, filters: Object });
const { can } = usePermissions();
const search = ref(props.filters?.search || '');
watch(search, useDebounceFn(() => {
    router.get('/estados', { search: search.value || undefined }, { preserveState: true, replace: true });
}, 300));

const PRESETS = ['#579DFF', '#57D9A3', '#F5CD47', '#F87168', '#9F8FEF', '#E56910', '#94A3B8', '#EF5C48'];

const dialogOpen = ref(false);
const editing = ref(null);
const form = useForm({ nombre: '', color: '#579DFF' });

const openCreate = () => {
    editing.value = null;
    form.clearErrors();
    form.nombre = '';
    form.color = '#579DFF';
    dialogOpen.value = true;
};

const openEdit = (row) => {
    editing.value = row;
    form.clearErrors();
    form.nombre = row.nombre;
    form.color = row.color || '#579DFF';
    dialogOpen.value = true;
};

const submit = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => { dialogOpen.value = false; form.reset(); },
    };
    if (editing.value) {
        form.put(`/estados/${editing.value.id}`, opts);
    } else {
        form.post('/estados', opts);
    }
};

const destroy = async (id) => {
    try {
        await ElMessageBox.confirm(
            'Si hay tickets en este estado, no se podrá eliminar o fallará la operación.',
            'Eliminar estado',
            { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' },
        );
        router.delete(`/estados/${id}`, { preserveScroll: true });
    } catch { /* */ }
};
</script>

<template>
    <Head title="Estados" />
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-display text-2xl font-semibold text-white">Estados del tablero</h2>
            <p class="text-sm text-[#8B9AAB]">
                Columnas del Kanban. Nombre + color para identificar el flujo.
            </p>
        </div>
        <el-button v-if="can('create estado')" type="primary" @click="openCreate">
            <el-icon class="mr-1"><Plus /></el-icon>
            Nuevo estado
        </el-button>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2 rounded-[10px] border border-[#2a3340] bg-[#1a222c] px-3 py-2.5">
        <el-input
            v-model="search"
            clearable
            placeholder="Buscar estado…"
            class="w-full max-w-sm"
        >
            <template #prefix>
                <el-icon><Search /></el-icon>
            </template>
        </el-input>
    </div>

    <div
        v-if="!estados.data?.length"
        class="rounded-[12px] border border-dashed border-[#2a3340] bg-[#12171d] px-6 py-12 text-center"
    >
        <el-icon :size="28" class="text-[#8B9AAB]"><CollectionTag /></el-icon>
        <p class="mt-3 font-medium text-[#E8EEF4]">Sin estados configurados</p>
        <p class="mt-1 text-sm text-[#8B9AAB]">Define el flujo: Nuevo → En progreso → Resuelto…</p>
        <el-button v-if="can('create estado')" class="mt-4" type="primary" @click="openCreate">
            Crear estado
        </el-button>
    </div>

    <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <div
            v-for="row in estados.data"
            :key="row.id"
            class="group rounded-[12px] border border-[#2a3340] bg-[#12171d] p-4 transition-colors hover:border-[#3d4a5c]"
        >
            <div class="flex items-start justify-between gap-2">
                <div class="flex min-w-0 items-center gap-3">
                    <span
                        class="h-10 w-10 shrink-0 rounded-lg shadow-inner"
                        :style="{ backgroundColor: row.color }"
                    />
                    <div class="min-w-0">
                        <p class="truncate font-medium text-white">{{ row.nombre }}</p>
                        <p class="font-mono text-[11px] text-[#8B9AAB]">{{ row.color }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 opacity-80 group-hover:opacity-100">
                    <el-button v-if="can('edit estado')" text type="primary" size="small" @click="openEdit(row)">
                        Editar
                    </el-button>
                    <el-button v-if="can('delete estado')" text type="danger" size="small" @click="destroy(row.id)">
                        Eliminar
                    </el-button>
                </div>
            </div>
            <div class="mt-3">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                    :style="{ backgroundColor: row.color }"
                >
                    {{ row.nombre }}
                </span>
            </div>
        </div>
    </div>

    <el-dialog
        v-model="dialogOpen"
        :title="editing ? 'Editar estado' : 'Nuevo estado'"
        width="440px"
        destroy-on-close
        align-center
    >
        <el-form label-position="top" @submit.prevent="submit">
            <el-form-item label="Nombre" :error="form.errors.nombre">
                <el-input v-model="form.nombre" placeholder="Ej. En progreso" autofocus />
            </el-form-item>
            <el-form-item label="Color" :error="form.errors.color">
                <div class="flex flex-wrap items-center gap-2">
                    <el-color-picker v-model="form.color" />
                    <button
                        v-for="c in PRESETS"
                        :key="c"
                        type="button"
                        class="h-7 w-7 rounded-md border-2 transition-transform hover:scale-110"
                        :class="form.color === c ? 'border-white' : 'border-transparent'"
                        :style="{ backgroundColor: c }"
                        :title="c"
                        @click="form.color = c"
                    />
                </div>
            </el-form-item>
            <div class="rounded-[10px] border border-[#2a3340] bg-[#0f1419] px-3 py-3">
                <p class="mb-2 text-[11px] uppercase tracking-wide text-[#8B9AAB]">Vista previa</p>
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                    :style="{ backgroundColor: form.color || '#579DFF' }"
                >
                    {{ form.nombre || 'Nombre del estado' }}
                </span>
            </div>
        </el-form>
        <template #footer>
            <el-button @click="dialogOpen = false">Cancelar</el-button>
            <el-button type="primary" :loading="form.processing" @click="submit">Guardar</el-button>
        </template>
    </el-dialog>
</template>
