<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    users: Object,
    filters: Object,
    canManage: Boolean,
    roles: { type: Array, default: () => [] },
    formCatalog: { type: Object, default: null },
    assignable: { type: Object, default: null },
});

const page = usePage();
const { isAdmin } = usePermissions();
const search = ref(props.filters?.search || '');
const roleFilter = ref(props.filters?.role || '');
const statusFilter = ref(props.filters?.status ?? '');

const apply = useDebounceFn(() => {
    router.get('/users', {
        search: search.value || undefined,
        role: roleFilter.value || undefined,
        status: statusFilter.value === '' ? undefined : statusFilter.value,
    }, { preserveState: true, replace: true });
}, 300);

watch([search, roleFilter, statusFilter], apply);

const clearFilters = () => {
    search.value = '';
    roleFilter.value = '';
    statusFilter.value = '';
};

const hasFilters = () => !!(search.value || roleFilter.value || statusFilter.value !== '');

const dialogOpen = ref(false);
const editing = ref(null);
const userForm = useForm({
    name: '',
    username: '',
    email: '',
    departamento_id: null,
    role: 'solicitante',
    is_active: true,
    password: '',
    workspace_ids: [],
});

const catalogRoles = computed(() => props.formCatalog?.roles || []);
const catalogDepts = computed(() => props.formCatalog?.departamentos || []);
const catalogWorkspaces = computed(() => props.formCatalog?.workspaces || []);

const openCreate = () => {
    editing.value = null;
    userForm.clearErrors();
    userForm.reset();
    userForm.name = '';
    userForm.username = '';
    userForm.email = '';
    userForm.departamento_id = null;
    userForm.role = catalogRoles.value.find((r) => r.name === 'solicitante')?.name
        || catalogRoles.value[0]?.name
        || 'solicitante';
    userForm.is_active = true;
    userForm.password = '';
    userForm.workspace_ids = page.props.auth?.user?.current_workspace_id
        ? [page.props.auth.user.current_workspace_id]
        : [];
    dialogOpen.value = true;
};

const openEdit = (row) => {
    editing.value = row;
    userForm.clearErrors();
    userForm.name = row.name;
    userForm.username = row.username;
    userForm.email = row.email;
    userForm.departamento_id = row.departamento_id || null;
    userForm.role = row.role || row.roles?.[0] || 'solicitante';
    userForm.is_active = !!row.is_active;
    userForm.password = '';
    userForm.workspace_ids = [...(row.workspace_ids || [])];
    dialogOpen.value = true;
};

const submitUser = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            userForm.reset();
        },
    };
    if (editing.value) {
        userForm.put(`/users/${editing.value.id}`, opts);
    } else {
        userForm.post('/users', opts);
    }
};

const assignOpen = ref(false);
const assignForm = useForm({
    user_id: null,
    roles: [],
});

const openAssign = (row = null) => {
    const list = props.assignable?.users || [];
    const target = row || list[0];
    assignForm.clearErrors();
    assignForm.user_id = target?.id ?? null;
    assignForm.roles = target ? [...(target.roles || [])] : [];
    assignOpen.value = true;
};

const loadAssignRoles = () => {
    const u = (props.assignable?.users || []).find((x) => x.id === Number(assignForm.user_id));
    assignForm.roles = u ? [...(u.roles || [])] : [];
};

const submitAssign = () => {
    assignForm.post('/users/assign-roles', {
        preserveScroll: true,
        onSuccess: () => { assignOpen.value = false; },
    });
};
</script>

<template>
    <Head title="Usuarios" />
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-display text-2xl font-semibold text-white">Usuarios</h2>
            <p class="text-sm text-[#8B9AAB]">
                Crea solicitantes y agentes, asígnales área y rol desde un solo modal.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <el-button v-if="isAdmin && assignable" @click="openAssign()">
                <el-icon class="mr-1"><UserFilled /></el-icon>
                Asignar roles
            </el-button>
            <el-button v-if="canManage" type="primary" @click="openCreate">
                <el-icon class="mr-1"><Plus /></el-icon>
                Nuevo usuario
            </el-button>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2 rounded-[10px] border border-[#2a3340] bg-[#1a222c] px-3 py-2.5">
        <el-input
            v-model="search"
            clearable
            placeholder="Buscar nombre, usuario o email…"
            class="w-full max-w-[260px]"
        >
            <template #prefix>
                <el-icon><Search /></el-icon>
            </template>
        </el-input>
        <el-select v-model="roleFilter" clearable placeholder="Rol" class="w-[140px]">
            <el-option v-for="r in roles" :key="r" :label="r" :value="r" />
        </el-select>
        <el-select v-model="statusFilter" clearable placeholder="Estado" class="w-[140px]">
            <el-option label="Activo" value="1" />
            <el-option label="Inactivo" value="0" />
        </el-select>
        <button
            type="button"
            class="ml-auto inline-flex items-center gap-1.5 rounded-[8px] border border-[#2a3340] px-2.5 py-1.5 text-xs text-[#8B9AAB] hover:text-white disabled:opacity-40"
            :disabled="!hasFilters()"
            @click="clearFilters"
        >
            <el-icon :size="14"><Filter /></el-icon>
            Limpiar
        </button>
    </div>

    <div class="overflow-hidden rounded-[10px] border border-[#2a3340]">
        <el-table :data="users.data" class="w-full" empty-text="No hay usuarios con esos filtros">
            <el-table-column label="Nombre" min-width="220">
                <template #default="{ row }">
                    <div class="flex items-center gap-3 py-0.5">
                        <el-avatar
                            :size="32"
                            :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(row.name || '?')}&background=579DFF&color=fff`"
                        />
                        <div class="min-w-0">
                            <p class="truncate font-medium text-[#E8EEF4]">{{ row.name }}</p>
                            <p class="truncate text-xs text-[#8B9AAB]">{{ row.email }}</p>
                        </div>
                    </div>
                </template>
            </el-table-column>
            <el-table-column prop="username" label="Usuario" width="140">
                <template #default="{ row }">
                    <span class="text-[#E8EEF4]">{{ row.username }}</span>
                </template>
            </el-table-column>
            <el-table-column label="Rol" width="130">
                <template #default="{ row }">
                    <el-tag size="small" effect="dark" type="info">{{ row.role || row.roles?.[0] || '—' }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column label="Depto" min-width="120">
                <template #default="{ row }">
                    <span class="text-[#8B9AAB]">{{ row.departamento || '—' }}</span>
                </template>
            </el-table-column>
            <el-table-column label="Estado" width="110">
                <template #default="{ row }">
                    <el-tag :type="row.is_active ? 'success' : 'danger'" size="small" effect="dark">
                        {{ row.is_active ? 'Activo' : 'Inactivo' }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column label="" width="150" align="right">
                <template #default="{ row }">
                    <el-button
                        v-if="isAdmin && assignable"
                        text
                        type="primary"
                        size="small"
                        @click="openAssign(row)"
                    >
                        Roles
                    </el-button>
                    <el-button
                        v-if="canManage"
                        text
                        type="primary"
                        size="small"
                        @click="openEdit(row)"
                    >
                        Editar
                    </el-button>
                </template>
            </el-table-column>
        </el-table>
    </div>

    <el-dialog
        v-model="dialogOpen"
        :title="editing ? `Editar · ${editing.name}` : 'Nuevo usuario'"
        width="520px"
        destroy-on-close
        align-center
    >
        <el-form label-position="top" @submit.prevent="submitUser">
            <div class="grid gap-x-3 sm:grid-cols-2">
                <el-form-item label="Nombre" :error="userForm.errors.name">
                    <el-input v-model="userForm.name" placeholder="Nombre completo" />
                </el-form-item>
                <el-form-item label="Username" :error="userForm.errors.username">
                    <el-input v-model="userForm.username" placeholder="usuario.login" />
                </el-form-item>
            </div>
            <el-form-item label="Email" :error="userForm.errors.email">
                <el-input v-model="userForm.email" type="email" placeholder="correo@empresa.com" />
            </el-form-item>
            <div class="grid gap-x-3 sm:grid-cols-2">
                <el-form-item label="Departamento" :error="userForm.errors.departamento_id">
                    <el-select
                        v-model="userForm.departamento_id"
                        clearable
                        filterable
                        placeholder="Opcional"
                        class="w-full"
                    >
                        <el-option
                            v-for="d in catalogDepts"
                            :key="d.id"
                            :label="d.nombre"
                            :value="d.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item label="Rol principal" :error="userForm.errors.role">
                    <el-select v-model="userForm.role" class="w-full" filterable>
                        <el-option
                            v-for="r in catalogRoles"
                            :key="r.id"
                            :label="r.is_agent ? `${r.name} · agente` : r.name"
                            :value="r.name"
                        />
                    </el-select>
                </el-form-item>
            </div>
            <el-form-item label="Áreas de trabajo" :error="userForm.errors.workspace_ids">
                <el-select
                    v-model="userForm.workspace_ids"
                    multiple
                    filterable
                    collapse-tags
                    collapse-tags-tooltip
                    class="w-full"
                    placeholder="Selecciona una o más áreas"
                >
                    <el-option
                        v-for="w in catalogWorkspaces"
                        :key="w.id"
                        :label="w.name"
                        :value="w.id"
                    />
                </el-select>
            </el-form-item>
            <el-form-item v-if="editing" label="Cuenta activa">
                <el-switch v-model="userForm.is_active" active-text="Activo" inactive-text="Inactivo" />
            </el-form-item>
            <el-form-item
                :label="editing ? 'Nueva contraseña (opcional)' : 'Contraseña (opcional)'"
                :error="userForm.errors.password"
            >
                <el-input
                    v-model="userForm.password"
                    type="password"
                    show-password
                    :placeholder="editing ? 'Dejar vacío para no cambiar' : 'Se genera temporal si queda vacío'"
                />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="dialogOpen = false">Cancelar</el-button>
            <el-button type="primary" :loading="userForm.processing" @click="submitUser">
                {{ editing ? 'Guardar cambios' : 'Crear usuario' }}
            </el-button>
        </template>
    </el-dialog>

    <el-dialog v-model="assignOpen" title="Asignar roles" width="440px" destroy-on-close align-center>
        <el-form label-position="top" @submit.prevent="submitAssign">
            <el-form-item label="Usuario" :error="assignForm.errors.user_id">
                <el-select
                    v-model="assignForm.user_id"
                    class="w-full"
                    filterable
                    @change="loadAssignRoles"
                >
                    <el-option
                        v-for="u in assignable?.users || []"
                        :key="u.id"
                        :label="`${u.name} (@${u.username})`"
                        :value="u.id"
                    />
                </el-select>
            </el-form-item>
            <el-form-item label="Roles" :error="assignForm.errors.roles">
                <el-checkbox-group v-model="assignForm.roles" class="flex flex-col gap-2">
                    <el-checkbox
                        v-for="r in assignable?.roles || []"
                        :key="r.id"
                        :label="r.name"
                        :value="r.name"
                    >
                        {{ r.name }}
                        <span v-if="r.is_agent" class="text-[#8B9AAB]"> · agente</span>
                    </el-checkbox>
                </el-checkbox-group>
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="assignOpen = false">Cancelar</el-button>
            <el-button type="primary" :loading="assignForm.processing" @click="submitAssign">
                Guardar
            </el-button>
        </template>
    </el-dialog>
</template>
