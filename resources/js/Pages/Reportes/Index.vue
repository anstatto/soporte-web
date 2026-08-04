<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { toast } from 'vue-sonner';

const props = defineProps({
    users: Array,
    estados: Array,
    tiposReporte: Object,
    filters: Object,
});

const form = reactive({ ...props.filters });

const exportar = async (format) => {
    try {
        const res = await axios.post('/reportes/exportar', { ...form, format }, { responseType: 'blob' });
        const url = window.URL.createObjectURL(new Blob([res.data]));
        const a = document.createElement('a');
        a.href = url;
        a.download = `reporte_soportes.${format === 'excel' ? 'xlsx' : format}`;
        a.click();
        window.URL.revokeObjectURL(url);
        toast.success('Descarga lista');
    } catch {
        toast.error('No se pudo generar el reporte');
    }
};
</script>

<template>
    <Head title="Reportes" />
    <div class="mb-4">
        <h2 class="font-display text-2xl font-semibold text-white">Reportes</h2>
        <p class="text-sm text-[#8B9AAB]">
            Elige filtros y descarga PDF, Excel o CSV. El archivo se genera en el servidor (no se previsualiza aquí).
        </p>
    </div>

    <div class="mx-auto max-w-3xl rounded-[10px] border border-[#2a3340] bg-[#12171d] p-5">
        <div class="grid gap-3 md:grid-cols-2">
            <el-form-item label="Desde" class="!mb-0">
                <el-date-picker
                    v-model="form.fecha_inicio"
                    type="datetime"
                    placeholder="Desde"
                    value-format="YYYY-MM-DDTHH:mm"
                    class="!w-full"
                />
            </el-form-item>
            <el-form-item label="Hasta" class="!mb-0">
                <el-date-picker
                    v-model="form.fecha_fin"
                    type="datetime"
                    placeholder="Hasta"
                    value-format="YYYY-MM-DDTHH:mm"
                    class="!w-full"
                />
            </el-form-item>
            <el-form-item label="Usuario" class="!mb-0">
                <el-select v-model="form.user_id" clearable placeholder="Todos" class="w-full">
                    <el-option v-for="u in users" :key="u.id" :label="u.name" :value="u.id" />
                </el-select>
            </el-form-item>
            <el-form-item label="Estado" class="!mb-0">
                <el-select v-model="form.estado_id" clearable placeholder="Todos" class="w-full">
                    <el-option v-for="e in estados" :key="e.id" :label="e.nombre" :value="e.id" />
                </el-select>
            </el-form-item>
            <el-form-item label="Tipo de reporte" class="!mb-0 md:col-span-2">
                <el-select v-model="form.tipo_reporte" placeholder="Tipo" class="w-full">
                    <el-option v-for="(label, key) in tiposReporte" :key="key" :label="label" :value="key" />
                </el-select>
            </el-form-item>
        </div>

        <div class="mt-6 flex flex-wrap gap-2">
            <el-button type="primary" @click="exportar('pdf')">
                <el-icon class="mr-1"><Document /></el-icon>
                Descargar PDF
            </el-button>
            <el-button @click="exportar('excel')">Excel</el-button>
            <el-button @click="exportar('csv')">CSV</el-button>
        </div>
    </div>
</template>
