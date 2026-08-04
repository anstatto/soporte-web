<script setup>
import { Toaster } from 'vue-sonner';
import 'vue-sonner/style.css';
</script>

<template>
    <div class="guest-shell flex min-h-screen bg-[#0f1419] text-[#E8EEF4]">
        <Toaster position="top-right" rich-colors theme="dark" />

        <!-- Panel marca -->
        <aside class="relative hidden w-[42%] flex-col justify-between overflow-hidden border-r border-[#2a3340] bg-[#12171d] p-10 lg:flex">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(87,157,255,0.12),transparent_55%)]" />
            <div class="relative">
                <img src="/images/LogoMono.png" alt="RM Consuegra" class="h-12 w-12 object-contain" />
                <h1 class="mt-8 font-display text-3xl font-semibold tracking-tight text-white">
                    RM Consuegra
                </h1>
                <p class="mt-2 text-lg text-[#85B8FF]">Soporte interno</p>
                <p class="mt-6 max-w-sm text-sm leading-relaxed text-[#8B9AAB]">
                    Gestiona incidencias, asigna responsables y sigue el estado del equipo en un solo lugar.
                </p>
            </div>
            <p class="relative text-xs text-[#5a6a7a]">© {{ new Date().getFullYear() }} RM Consuegra</p>
        </aside>

        <!-- Formulario -->
        <div class="flex flex-1 flex-col items-center justify-center px-4 py-10">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <img src="/images/LogoMono.png" alt="" class="h-10 w-10 object-contain" />
                <div>
                    <p class="font-display text-lg font-semibold text-white">RM Consuegra</p>
                    <p class="text-xs text-[#8B9AAB]">Soporte interno</p>
                </div>
            </div>
            <div class="w-full max-w-[400px]">
                <slot />
            </div>
        </div>
    </div>
</template>
