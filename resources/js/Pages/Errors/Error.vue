<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    status: { type: Number, required: true },
});

const user = computed(() => usePage().props.auth?.user);
const home = computed(() => (user.value ? '/tickets/board' : '/login'));

const copy = computed(() => {
    const map = {
        403: {
            title: 'Acceso denegado',
            detail: 'No tienes permiso para ver esta sección.',
        },
        404: {
            title: 'No encontrado',
            detail: 'La página o el recurso no existe.',
        },
        500: {
            title: 'Error del servidor',
            detail: 'Algo salió mal. Intenta de nuevo en un momento.',
        },
        503: {
            title: 'No disponible',
            detail: 'El servicio está en mantenimiento. Vuelve pronto.',
        },
    };
    return map[props.status] || {
        title: `Error ${props.status}`,
        detail: 'No se pudo completar la solicitud.',
    };
});

const goInbox = () => router.visit('/tickets');
</script>

<template>
    <Head :title="`${status} · ${copy.title}`" />
    <div class="flex min-h-[60vh] flex-col items-center justify-center px-4 py-16 text-center">
        <p class="font-display text-5xl font-semibold text-[#579DFF]">{{ status }}</p>
        <h1 class="mt-3 font-display text-2xl font-semibold text-white">{{ copy.title }}</h1>
        <p class="mt-2 max-w-md text-sm text-[#8B9AAB]">{{ copy.detail }}</p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <Link :href="home">
                <el-button type="primary">
                    <el-icon class="mr-1"><HomeFilled /></el-icon>
                    {{ user ? 'Ir al tablero' : 'Iniciar sesión' }}
                </el-button>
            </Link>
            <el-button v-if="user" @click="goInbox">Bandeja</el-button>
        </div>
    </div>
</template>
