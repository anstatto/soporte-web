<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    departamentos: Array,
    estados: Array,
    usuarios: Array,
    isSoporte: Boolean,
});

const form = useForm({
    titulo: '',
    descripcion: '',
    departamento_id: props.departamentos[0]?.id || '',
    estado_id: props.estados[0]?.id || '',
    fecha_entrega: '',
    recordatorio: '',
    user_ids: [],
});

const submit = () => form.post('/tickets');
</script>

<template>
    <Head title="Nueva solicitud" />
    <div class="mx-auto max-w-2xl">
        <Link href="/tickets" class="text-sm text-[#85B8FF] hover:underline">← Bandeja</Link>
        <h2 class="mt-2 font-display text-2xl font-semibold text-white">Nueva solicitud</h2>
        <p class="mb-4 text-sm text-[#8B9AAB]">Escribe el asunto y el primer mensaje del hilo</p>

        <el-form label-position="top" class="space-y-1" @submit.prevent="submit">
            <el-form-item label="Asunto" :error="form.errors.titulo">
                <el-input v-model="form.titulo" placeholder="Ej. No puedo acceder a la impresora" />
            </el-form-item>
            <el-form-item label="Mensaje" :error="form.errors.descripcion">
                <el-input v-model="form.descripcion" type="textarea" :rows="6" placeholder="Describe el problema…" />
            </el-form-item>
            <el-form-item label="Departamento" :error="form.errors.departamento_id">
                <el-select v-model="form.departamento_id" class="w-full">
                    <el-option v-for="d in departamentos" :key="d.id" :label="d.nombre" :value="d.id" />
                </el-select>
            </el-form-item>

            <template v-if="isSoporte">
                <el-form-item label="Estado">
                    <el-select v-model="form.estado_id" class="w-full">
                        <el-option v-for="e in estados" :key="e.id" :label="e.nombre" :value="e.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="Asignar a">
                    <el-select v-model="form.user_ids" multiple filterable collapse-tags class="w-full" placeholder="Personas">
                        <el-option v-for="u in usuarios" :key="u.id" :label="u.name" :value="u.id" />
                    </el-select>
                </el-form-item>
                <div class="grid gap-4 sm:grid-cols-2">
                    <el-form-item label="Fecha entrega">
                        <el-date-picker v-model="form.fecha_entrega" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                    </el-form-item>
                    <el-form-item label="Recordatorio">
                        <el-date-picker v-model="form.recordatorio" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                    </el-form-item>
                </div>
            </template>

            <div class="flex justify-end gap-2 pt-2">
                <Link href="/tickets"><el-button>Cancelar</el-button></Link>
                <el-button type="primary" :loading="form.processing" native-type="submit">Enviar solicitud</el-button>
            </div>
        </el-form>
    </div>
</template>
