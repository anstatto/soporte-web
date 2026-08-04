<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';

defineProps({
    status: String,
});

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Iniciar sesión" />

    <div>
        <h2 class="font-display text-2xl font-semibold text-white">Iniciar sesión</h2>
        <p class="mt-1 text-sm text-[#8B9AAB]">Accede con tu usuario corporativo</p>

        <p
            v-if="status"
            class="mt-4 rounded-[10px] border border-[#3D7A5F]/40 bg-[#3D7A5F]/15 px-3 py-2 text-sm text-[#6BCB9A]"
        >
            {{ status }}
        </p>

        <el-form class="mt-6" label-position="top" @submit.prevent="submit">
            <el-form-item label="Usuario" :error="form.errors.username">
                <el-input
                    v-model="form.username"
                    size="large"
                    autocomplete="username"
                    placeholder="tu.usuario"
                    autofocus
                />
            </el-form-item>
            <el-form-item label="Contraseña" :error="form.errors.password">
                <el-input
                    v-model="form.password"
                    type="password"
                    size="large"
                    show-password
                    autocomplete="current-password"
                    placeholder="••••••••"
                />
            </el-form-item>
            <div class="mb-4 flex items-center justify-between">
                <el-checkbox v-model="form.remember">Recordarme</el-checkbox>
                <Link href="/password/reset" class="text-sm text-[#85B8FF] hover:underline">
                    ¿Olvidaste tu contraseña?
                </Link>
            </div>
            <el-button
                type="primary"
                size="large"
                class="!w-full !rounded-[10px]"
                :loading="form.processing"
                native-type="submit"
            >
                Entrar
            </el-button>
        </el-form>
    </div>
</template>
