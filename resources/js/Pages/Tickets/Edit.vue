<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    ticket: Object,
    departamentos: Array,
    estados: Array,
    usuarios: Array,
});

const form = useForm({
    titulo: props.ticket.titulo,
    descripcion: props.ticket.descripcion,
    departamento_id: props.ticket.departamento_id,
    estado_id: props.ticket.estado_id,
    fecha_entrega: props.ticket.fecha_entrega?.substring?.(0, 10) || props.ticket.fecha_entrega || '',
    recordatorio: props.ticket.recordatorio?.substring?.(0, 10) || props.ticket.recordatorio || '',
    user_ids: props.ticket.users?.map((u) => u.id) || [],
});

const submit = () => form.put(`/tickets/${props.ticket.id}`);
</script>

<template>
    <Head title="Editar ticket" />
    <div class="mx-auto max-w-2xl">
        <Link :href="`/tickets/board?card=${ticket.id}`" class="text-sm text-[#85B8FF] hover:underline">← Volver al tablero</Link>
        <h2 class="mt-2 mb-4 font-display text-2xl font-semibold text-white">Editar ticket</h2>

        <el-form label-position="top" class="space-y-1" @submit.prevent="submit">
            <el-form-item label="Asunto" :error="form.errors.titulo">
                <el-input v-model="form.titulo" />
            </el-form-item>
            <el-form-item label="Descripción" :error="form.errors.descripcion">
                <el-input v-model="form.descripcion" type="textarea" :rows="5" />
            </el-form-item>
            <div class="grid gap-4 sm:grid-cols-2">
                <el-form-item label="Departamento">
                    <el-select v-model="form.departamento_id" class="w-full">
                        <el-option v-for="d in departamentos" :key="d.id" :label="d.nombre" :value="d.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="Estado">
                    <el-select v-model="form.estado_id" class="w-full">
                        <el-option v-for="e in estados" :key="e.id" :label="e.nombre" :value="e.id" />
                    </el-select>
                </el-form-item>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <el-form-item label="Entrega">
                    <el-date-picker v-model="form.fecha_entrega" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                </el-form-item>
                <el-form-item label="Recordatorio">
                    <el-date-picker v-model="form.recordatorio" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                </el-form-item>
            </div>
            <el-form-item label="Asignados">
                <el-select v-model="form.user_ids" multiple filterable collapse-tags class="w-full">
                    <el-option v-for="u in usuarios" :key="u.id" :label="u.name" :value="u.id" />
                </el-select>
            </el-form-item>
            <div class="flex justify-end gap-2 pt-2">
                <Link :href="`/tickets/board?card=${ticket.id}`"><el-button>Cancelar</el-button></Link>
                <el-button type="primary" :loading="form.processing" native-type="submit">Guardar</el-button>
            </div>
        </el-form>
    </div>
</template>
