<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ElMessageBox } from 'element-plus';

const props = defineProps({
    roles: Array,
    permissions: Array,
});

const dialogOpen = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    permissions: [],
    is_agent: false,
});

const permissionGroups = computed(() => {
    const order = ['Tickets', 'Estados', 'Departamentos', 'Reportes', 'Usuarios', 'Dashboard', 'Otros'];
    const buckets = Object.fromEntries(order.map((k) => [k, []]));

    for (const p of props.permissions || []) {
        let g = 'Otros';
        if (p.includes('ticket')) g = 'Tickets';
        else if (p.includes('estado')) g = 'Estados';
        else if (p.includes('departamento')) g = 'Departamentos';
        else if (p.includes('report')) g = 'Reportes';
        else if (p.includes('user')) g = 'Usuarios';
        else if (p.includes('dashboard')) g = 'Dashboard';
        buckets[g].push(p);
    }

    return order.filter((k) => buckets[k].length).map((k) => ({ label: k, items: buckets[k] }));
});

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.name = '';
    form.permissions = [];
    form.is_agent = false;
    dialogOpen.value = true;
};

const openEdit = (role) => {
    editing.value = role;
    form.clearErrors();
    form.name = role.name;
    form.permissions = [...(role.permissions || [])];
    form.is_agent = !!role.is_agent;
    dialogOpen.value = true;
};

const closeDialog = () => {
    dialogOpen.value = false;
};

const submit = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            form.reset();
        },
    };
    if (editing.value) {
        form.put(`/roles/${editing.value.id}`, opts);
    } else {
        form.post('/roles', opts);
    }
};

const toggleGroup = (items, checked) => {
    const set = new Set(form.permissions);
    items.forEach((p) => (checked ? set.add(p) : set.delete(p)));
    form.permissions = [...set];
};

const groupChecked = (items) => items.every((p) => form.permissions.includes(p));
const groupIndeterminate = (items) => {
    const n = items.filter((p) => form.permissions.includes(p)).length;
    return n > 0 && n < items.length;
};

const destroy = async (role) => {
    try {
        await ElMessageBox.confirm(`¿Eliminar el rol «${role.name}»?`, 'Confirmar', {
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            type: 'warning',
        });
        router.delete(`/roles/${role.id}`, { preserveScroll: true });
    } catch {
        // cancelado
    }
};

const isSystem = (name) => ['admin', 'soporte', 'solicitante'].includes(name);

/** Plantillas rápidas al crear/editar rol */
const ROLE_TEMPLATES = {
    solicitante: {
        label: 'Solicitante',
        is_agent: false,
        permissions: ['create tickets', 'view tickets', 'comment on tickets', 'chat with users', 'dashboard actividad'],
    },
    agente: {
        label: 'Agente',
        is_agent: true,
        permissions: [
            'create tickets',
            'edit tickets',
            'delete tickets',
            'view tickets',
            'assign tickets',
            'comment on tickets',
            'chat with users',
            'view estado',
            'view departamento',
            'dashboard resumen',
            'dashboard estadistica',
            'dashboard actividad',
            'view reports',
            'generate reports',
        ],
    },
    reportes: {
        label: 'Solo reportes',
        is_agent: false,
        permissions: ['view reports', 'generate reports', 'dashboard resumen', 'dashboard estadistica'],
    },
};

const applyTemplate = (key) => {
    const t = ROLE_TEMPLATES[key];
    if (!t) return;
    const allowed = new Set(props.permissions || []);
    form.is_agent = t.is_agent;
    form.permissions = t.permissions.filter((p) => allowed.has(p));
    if (!editing.value && !form.name) {
        form.name = key === 'reportes' ? 'auditor' : key;
    }
};
</script>

<template>
    <Head title="Roles" />
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-display text-2xl font-semibold text-white">Roles y permisos</h2>
            <p class="text-sm text-[#8B9AAB]">
                Define quién es agente (tablero) y qué puede hacer cada rol.
            </p>
        </div>
        <el-button type="primary" @click="openCreate">
            <el-icon class="mr-1"><Plus /></el-icon>
            Nuevo rol
        </el-button>
    </div>

    <div
        v-if="!roles?.length"
        class="rounded-[12px] border border-dashed border-[#2a3340] bg-[#12171d] px-6 py-12 text-center"
    >
        <el-icon :size="28" class="text-[#8B9AAB]"><Key /></el-icon>
        <p class="mt-3 font-medium text-[#E8EEF4]">Sin roles</p>
        <el-button class="mt-4" type="primary" @click="openCreate">Crear rol</el-button>
    </div>

    <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <button
            v-for="role in roles"
            :key="role.id"
            type="button"
            class="rounded-[12px] border border-[#2a3340] bg-[#12171d] p-4 text-left transition-colors hover:border-[#3d4a5c] hover:bg-[#151b22]"
            @click="openEdit(role)"
        >
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h3 class="font-display text-lg font-semibold text-white">{{ role.name }}</h3>
                    <p class="text-xs text-[#8B9AAB]">
                        {{ role.permissions?.length || 0 }} permisos
                        <span v-if="role.is_agent"> · agente</span>
                        <span v-if="isSystem(role.name)"> · sistema</span>
                    </p>
                </div>
                <div class="flex shrink-0 gap-1" @click.stop>
                    <el-button text type="primary" size="small" @click="openEdit(role)">Editar</el-button>
                    <el-button
                        v-if="!isSystem(role.name)"
                        text
                        type="danger"
                        size="small"
                        @click="destroy(role)"
                    >
                        Eliminar
                    </el-button>
                </div>
            </div>
            <div class="mt-3 flex max-h-28 flex-wrap gap-1.5 overflow-y-auto">
                <el-tag
                    v-for="p in role.permissions"
                    :key="p"
                    size="small"
                    effect="dark"
                    type="info"
                    class="!border-0"
                >
                    {{ p }}
                </el-tag>
                <span v-if="!role.permissions?.length" class="text-xs text-[#8B9AAB]">Sin permisos</span>
            </div>
        </button>
    </div>

    <el-dialog
        v-model="dialogOpen"
        :title="editing ? `Editar rol · ${editing.name}` : 'Nuevo rol'"
        width="560px"
        destroy-on-close
        align-center
        class="role-dialog"
        @closed="form.reset()"
    >
        <el-form label-position="top" @submit.prevent="submit">
            <el-form-item label="Nombre" :error="form.errors.name">
                <el-input
                    v-model="form.name"
                    placeholder="Ej. auditor"
                    :disabled="editing && isSystem(editing.name)"
                />
            </el-form-item>
            <el-form-item label="Plantilla rápida">
                <div class="flex flex-wrap gap-2">
                    <el-button
                        v-for="(t, key) in ROLE_TEMPLATES"
                        :key="key"
                        size="small"
                        @click="applyTemplate(key)"
                    >
                        {{ t.label }}
                    </el-button>
                </div>
                <p class="mt-1 text-[11px] text-[#8B9AAB]">
                    Rellena permisos e indicador de agente. «Solo reportes» no incluye Mis chats / Portal.
                </p>
            </el-form-item>
            <el-form-item label="Tipo de acceso">
                <el-switch
                    v-model="form.is_agent"
                    active-text="Agente (ve tablero / asigna)"
                    inactive-text="Solicitante / limitado"
                />
                <p class="mt-1 text-[11px] text-[#8B9AAB]">
                    Los agentes ven todas las incidencias del área y pueden asignar/mover tarjetas según permisos.
                </p>
            </el-form-item>
            <el-form-item label="Permisos" :error="form.errors.permissions">
                <div class="max-h-[50vh] w-full space-y-4 overflow-y-auto pr-1">
                    <div v-for="group in permissionGroups" :key="group.label">
                        <div class="mb-1.5 flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#8B9AAB]">
                                {{ group.label }}
                            </p>
                            <el-checkbox
                                :model-value="groupChecked(group.items)"
                                :indeterminate="groupIndeterminate(group.items)"
                                @change="(v) => toggleGroup(group.items, v)"
                            >
                                Todos
                            </el-checkbox>
                        </div>
                        <el-checkbox-group v-model="form.permissions" class="grid grid-cols-1 gap-1 sm:grid-cols-2">
                            <el-checkbox
                                v-for="p in group.items"
                                :key="p"
                                :label="p"
                                :value="p"
                                class="!mr-0"
                            >
                                {{ p }}
                            </el-checkbox>
                        </el-checkbox-group>
                    </div>
                </div>
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="closeDialog">Cancelar</el-button>
            <el-button type="primary" :loading="form.processing" @click="submit">
                {{ editing ? 'Guardar' : 'Crear' }}
            </el-button>
        </template>
    </el-dialog>
</template>
