<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ user: Object, departamentos: Array });

const profileForm = useForm({
    name: props.user.name,
    email: props.user.email,
    username: props.user.username,
    departamento_id: props.user.departamento_id || null,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const initials = computed(() => {
    const parts = (props.user.name || '?').trim().split(/\s+/);
    return ((parts[0]?.[0] || '') + (parts[1]?.[0] || '')).toUpperCase() || '?';
});

const saveProfile = () => profileForm.put('/perfil', { preserveScroll: true });

const savePassword = () => {
    passwordForm.put('/perfil', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        transform: (data) => ({
            ...profileForm.data(),
            current_password: data.current_password,
            password: data.password,
            password_confirmation: data.password_confirmation,
        }),
    });
};
</script>

<template>
    <Head title="Mi perfil" />

    <div class="mb-6">
        <h2 class="font-display text-2xl font-semibold text-white">Mi perfil</h2>
        <p class="text-sm text-[#8B9AAB]">Datos de cuenta y seguridad</p>
    </div>

    <div class="mb-4 flex items-center gap-4 rounded-[12px] border border-[#2a3340] bg-[#12171d] p-4">
        <el-avatar
            :size="56"
            :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(user.name || '?')}&background=579DFF&color=fff&size=112`"
        >
            {{ initials }}
        </el-avatar>
        <div class="min-w-0">
            <p class="truncate font-display text-lg font-semibold text-white">{{ user.name }}</p>
            <p class="truncate text-sm text-[#8B9AAB]">@{{ user.username }} · {{ user.email }}</p>
            <div class="mt-2 flex flex-wrap gap-1.5">
                <el-tag
                    v-for="r in user.roles || []"
                    :key="r"
                    size="small"
                    effect="dark"
                    type="info"
                >
                    {{ r }}
                </el-tag>
                <el-tag v-if="user.departamento" size="small" effect="dark">
                    {{ user.departamento }}
                </el-tag>
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-[12px] border border-[#2a3340] bg-[#12171d] p-4">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-[#8B9AAB]">
                Datos personales
            </h3>
            <el-form label-position="top" @submit.prevent="saveProfile">
                <el-form-item label="Nombre" :error="profileForm.errors.name">
                    <el-input v-model="profileForm.name" />
                </el-form-item>
                <el-form-item label="Username" :error="profileForm.errors.username">
                    <el-input v-model="profileForm.username" />
                </el-form-item>
                <el-form-item label="Email" :error="profileForm.errors.email">
                    <el-input v-model="profileForm.email" type="email" />
                </el-form-item>
                <el-form-item label="Departamento">
                    <el-select
                        v-model="profileForm.departamento_id"
                        clearable
                        filterable
                        placeholder="—"
                        class="w-full"
                    >
                        <el-option
                            v-for="d in departamentos"
                            :key="d.id"
                            :label="d.nombre"
                            :value="d.id"
                        />
                    </el-select>
                </el-form-item>
                <div class="flex justify-end pt-1">
                    <el-button
                        type="primary"
                        :loading="profileForm.processing"
                        :disabled="!profileForm.isDirty"
                        native-type="submit"
                    >
                        Guardar datos
                    </el-button>
                </div>
            </el-form>
        </section>

        <section class="rounded-[12px] border border-[#2a3340] bg-[#12171d] p-4">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-[#8B9AAB]">
                Cambiar contraseña
            </h3>
            <el-form label-position="top" @submit.prevent="savePassword">
                <el-form-item label="Contraseña actual" :error="passwordForm.errors.current_password">
                    <el-input
                        v-model="passwordForm.current_password"
                        type="password"
                        show-password
                        autocomplete="current-password"
                    />
                </el-form-item>
                <el-form-item label="Nueva contraseña" :error="passwordForm.errors.password">
                    <el-input
                        v-model="passwordForm.password"
                        type="password"
                        show-password
                        autocomplete="new-password"
                    />
                </el-form-item>
                <el-form-item label="Confirmar nueva" :error="passwordForm.errors.password_confirmation">
                    <el-input
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        show-password
                        autocomplete="new-password"
                    />
                </el-form-item>
                <div class="flex justify-end pt-1">
                    <el-button
                        type="primary"
                        :loading="passwordForm.processing"
                        :disabled="!passwordForm.password"
                        native-type="submit"
                    >
                        Actualizar contraseña
                    </el-button>
                </div>
            </el-form>
        </section>
    </div>
</template>
