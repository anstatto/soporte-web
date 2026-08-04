<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    token: String,
    email: String,
});

const form = useForm({
    token: props.token,
    email: props.email || '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/password/reset');
};
</script>

<template>
    <Head title="Nueva contraseña" />
    <div>
        <h2 class="font-display text-2xl font-semibold text-white">Nueva contraseña</h2>
        <p class="mt-1 text-sm text-[#8B9AAB]">Define una contraseña segura</p>

        <el-form class="mt-6" label-position="top" @submit.prevent="submit">
            <el-form-item label="Correo" :error="form.errors.email">
                <el-input v-model="form.email" type="email" size="large" />
            </el-form-item>
            <el-form-item label="Contraseña" :error="form.errors.password">
                <el-input v-model="form.password" type="password" size="large" show-password />
            </el-form-item>
            <el-form-item label="Confirmar">
                <el-input v-model="form.password_confirmation" type="password" size="large" show-password />
            </el-form-item>
            <el-button
                type="primary"
                size="large"
                class="!w-full !rounded-[10px]"
                :loading="form.processing"
                native-type="submit"
            >
                Guardar
            </el-button>
        </el-form>
        <p class="mt-4 text-center text-sm">
            <Link href="/login" class="text-[#85B8FF] hover:underline">Volver al login</Link>
        </p>
    </div>
</template>
