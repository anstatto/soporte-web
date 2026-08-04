<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';

const form = useForm({ email: '' });

const submit = () => {
    form.post('/password/email');
};
</script>

<template>
    <Head title="Recuperar contraseña" />
    <div>
        <h2 class="font-display text-2xl font-semibold text-white">Recuperar contraseña</h2>
        <p class="mt-1 text-sm text-[#8B9AAB]">Te enviaremos un enlace a tu correo</p>

        <el-form class="mt-6" label-position="top" @submit.prevent="submit">
            <el-form-item label="Correo" :error="form.errors.email">
                <el-input v-model="form.email" type="email" size="large" placeholder="correo@empresa.com" />
            </el-form-item>
            <el-button
                type="primary"
                size="large"
                class="!w-full !rounded-[10px]"
                :loading="form.processing"
                native-type="submit"
            >
                Enviar enlace
            </el-button>
        </el-form>
        <p class="mt-4 text-center text-sm">
            <Link href="/login" class="text-[#85B8FF] hover:underline">Volver al login</Link>
        </p>
    </div>
</template>
