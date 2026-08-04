<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';

const props = defineProps({ comentario: Object });
const form = useForm({ contenido: props.comentario.contenido });
const ticketId = props.comentario.ticket_id || props.comentario.ticket?.id;
</script>

<template>
    <Head title="Editar mensaje" />
    <div class="mx-auto max-w-lg">
        <Link :href="`/tickets/board?card=${ticketId}`" class="text-sm text-[#85B8FF] hover:underline">← Volver</Link>
        <h2 class="mt-2 mb-4 font-display text-2xl font-semibold text-white">Editar mensaje</h2>
        <el-form label-position="top" @submit.prevent="form.put(`/comentarios/${comentario.id}`)">
            <el-form-item label="Contenido" :error="form.errors.contenido">
                <el-input v-model="form.contenido" type="textarea" :rows="5" />
            </el-form-item>
            <div class="flex justify-end gap-2">
                <Link :href="`/tickets/board?card=${ticketId}`"><el-button>Cancelar</el-button></Link>
                <el-button type="primary" :loading="form.processing" native-type="submit">Guardar</el-button>
            </div>
        </el-form>
    </div>
</template>
