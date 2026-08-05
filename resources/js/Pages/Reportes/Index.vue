<script setup>
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { toast } from 'vue-sonner';

const props = defineProps({
    users: Array,
    estados: Array,
    tiposReporte: Object,
    filters: Object,
});

const form = reactive({ ...props.filters });
const loading = ref(null);
const previewOpen = ref(false);
const previewUrl = ref('');
const previewTitle = ref('Vista previa del reporte');

const tipoLabel = computed(() => props.tiposReporte?.[form.tipo_reporte] || 'Reporte');

const revokePreview = () => {
    if (previewUrl.value) {
        window.URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = '';
    }
};

onBeforeUnmount(revokePreview);

const blobErrorMessage = async (err) => {
    const data = err?.response?.data;
    if (data instanceof Blob) {
        try {
            const text = await data.text();
            const json = JSON.parse(text);
            return json.message || 'No se pudo generar el reporte';
        } catch {
            return 'No se pudo generar el reporte';
        }
    }
    return err?.response?.data?.message || 'No se pudo generar el reporte';
};

const requestReport = async (format, { preview = false } = {}) => {
    loading.value = preview ? 'preview' : format;
    try {
        const res = await axios.post(
            '/reportes/exportar',
            { ...form, format, preview },
            { responseType: 'blob' },
        );

        const contentType = res.headers['content-type'] || '';
        if (contentType.includes('application/json')) {
            const text = await res.data.text();
            const json = JSON.parse(text);
            throw new Error(json.message || 'Error al generar');
        }

        const blob = new Blob([res.data], {
            type: format === 'pdf'
                ? 'application/pdf'
                : format === 'excel'
                    ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                    : 'text/csv',
        });

        if (preview && format === 'pdf') {
            revokePreview();
            previewUrl.value = window.URL.createObjectURL(blob);
            previewTitle.value = `${tipoLabel.value} — vista previa`;
            previewOpen.value = true;
            return;
        }

        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `reporte_${form.tipo_reporte || 'soportes'}.${format === 'excel' ? 'xlsx' : format}`;
        a.click();
        window.URL.revokeObjectURL(url);
        toast.success('Descarga lista');
    } catch (err) {
        toast.error(await blobErrorMessage(err));
    } finally {
        loading.value = null;
    }
};

const closePreview = () => {
    previewOpen.value = false;
    revokePreview();
};

const downloadFromPreview = () => {
    if (!previewUrl.value) return;
    const a = document.createElement('a');
    a.href = previewUrl.value;
    a.download = `reporte_${form.tipo_reporte || 'soportes'}.pdf`;
    a.click();
    toast.success('PDF descargado');
};
</script>

<template>
    <Head title="Reportes" />
    <div class="mb-4">
        <h2 class="font-display text-2xl font-semibold text-white">Reportes</h2>
        <p class="text-sm text-[#8B9AAB]">
            Filtra, previsualiza el PDF en pantalla y descarga PDF, Excel o CSV generados en el servidor.
        </p>
    </div>

    <div class="mx-auto grid max-w-5xl gap-4 lg:grid-cols-[1fr_280px]">
        <div class="rounded-[10px] border border-[#2a3340] bg-[#12171d] p-5">
            <div class="mb-4 flex items-center gap-2 text-white">
                <el-icon :size="18" class="text-[#579DFF]"><DataAnalysis /></el-icon>
                <h3 class="font-medium">Filtros</h3>
            </div>

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
                <el-button
                    type="primary"
                    :loading="loading === 'preview'"
                    :disabled="!!loading && loading !== 'preview'"
                    @click="requestReport('pdf', { preview: true })"
                >
                    <el-icon class="mr-1"><View /></el-icon>
                    Vista previa PDF
                </el-button>
                <el-button
                    :loading="loading === 'pdf'"
                    :disabled="!!loading && loading !== 'pdf'"
                    @click="requestReport('pdf')"
                >
                    <el-icon class="mr-1"><Document /></el-icon>
                    Descargar PDF
                </el-button>
                <el-button
                    :loading="loading === 'excel'"
                    :disabled="!!loading && loading !== 'excel'"
                    @click="requestReport('excel')"
                >
                    <el-icon class="mr-1"><Download /></el-icon>
                    Excel
                </el-button>
                <el-button
                    :loading="loading === 'csv'"
                    :disabled="!!loading && loading !== 'csv'"
                    @click="requestReport('csv')"
                >
                    <el-icon class="mr-1"><DocumentCopy /></el-icon>
                    CSV
                </el-button>
            </div>
        </div>

        <aside class="rounded-[10px] border border-[#2a3340] bg-[#12171d] p-5">
            <div class="mb-3 flex items-center gap-2 text-white">
                <el-icon :size="18" class="text-[#57D9A3]"><InfoFilled /></el-icon>
                <h3 class="font-medium">Tipos</h3>
            </div>
            <ul class="space-y-3 text-sm text-[#8B9AAB]">
                <li>
                    <span class="font-medium text-[#E8EEF4]">Básico</span>
                    — listado por usuario con estado y prioridad.
                </li>
                <li>
                    <span class="font-medium text-[#E8EEF4]">Detallado</span>
                    — incluye descripción, departamento y fechas.
                </li>
                <li>
                    <span class="font-medium text-[#E8EEF4]">Estadístico</span>
                    — KPIs y barras por estado, prioridad y depto.
                </li>
                <li>
                    <span class="font-medium text-[#E8EEF4]">Rendimiento</span>
                    — horas de resolución en tickets cerrados y tasa de cierre.
                </li>
            </ul>
        </aside>
    </div>

    <el-dialog
        v-model="previewOpen"
        :title="previewTitle"
        width="min(960px, 96vw)"
        top="3vh"
        destroy-on-close
        append-to-body
        class="report-preview-dialog"
        @closed="revokePreview"
    >
        <div class="overflow-hidden rounded-lg border border-[#2a3340] bg-[#0b0f14]">
            <iframe
                v-if="previewUrl"
                :src="previewUrl"
                class="h-[72vh] w-full bg-white"
                title="Vista previa PDF"
            />
            <div v-else class="flex h-[40vh] items-center justify-center text-[#8B9AAB]">
                Sin documento
            </div>
        </div>
        <template #footer>
            <el-button @click="closePreview">Cerrar</el-button>
            <el-button type="primary" :disabled="!previewUrl" @click="downloadFromPreview">
                <el-icon class="mr-1"><Download /></el-icon>
                Descargar PDF
            </el-button>
        </template>
    </el-dialog>
</template>
