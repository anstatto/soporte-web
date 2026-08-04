<script setup>
import { computed, ref, watch } from 'vue';
import mammoth from 'mammoth';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    file: { type: Object, default: null }, // { url, nombre, kind, mime }
});

const emit = defineEmits(['update:modelValue']);

const open = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const wordHtml = ref('');
const wordLoading = ref(false);
const wordError = ref('');

const kind = computed(() => props.file?.kind || 'other');
const title = computed(() => props.file?.nombre || 'Archivo');

const loadWord = async () => {
    wordHtml.value = '';
    wordError.value = '';
    if (!props.file?.url || kind.value !== 'word') return;

    const name = (props.file.nombre || '').toLowerCase();
    if (name.endsWith('.doc') && !name.endsWith('.docx')) {
        wordError.value = 'Los .doc antiguos no se pueden previsualizar. Descárgalo para abrirlo.';
        return;
    }

    wordLoading.value = true;
    try {
        const res = await fetch(props.file.url);
        if (!res.ok) throw new Error('No se pudo cargar el archivo');
        const buffer = await res.arrayBuffer();
        const result = await mammoth.convertToHtml({ arrayBuffer: buffer });
        wordHtml.value = result.value || '<p>Documento vacío</p>';
    } catch {
        wordError.value = 'No se pudo previsualizar el Word. Prueba descargarlo.';
    } finally {
        wordLoading.value = false;
    }
};

watch(
    () => [props.modelValue, props.file?.url],
    ([isOpen]) => {
        if (isOpen && kind.value === 'word') loadWord();
    },
);
</script>

<template>
    <el-dialog
        v-model="open"
        :title="title"
        width="min(920px, 96vw)"
        top="4vh"
        destroy-on-close
        append-to-body
        class="file-viewer-dialog"
    >
        <div class="min-h-[50vh]">
            <div v-if="kind === 'image'" class="flex justify-center bg-[#0b0f14] p-2">
                <img
                    :src="file?.url"
                    :alt="title"
                    class="max-h-[70vh] max-w-full rounded-lg object-contain"
                />
            </div>

            <iframe
                v-else-if="kind === 'pdf'"
                :src="file?.url"
                class="h-[70vh] w-full rounded-lg border border-[#2a3340] bg-white"
                title="PDF"
            />

            <div v-else-if="kind === 'word'" class="rounded-lg border border-[#2a3340] bg-[#12171d]">
                <div v-if="wordLoading" class="px-4 py-16 text-center text-sm text-[#8B9AAB]">
                    Cargando documento…
                </div>
                <div v-else-if="wordError" class="px-4 py-10 text-center text-sm text-[#E08A84]">
                    {{ wordError }}
                </div>
                <div
                    v-else
                    class="word-preview max-h-[70vh] overflow-y-auto px-5 py-4 text-sm leading-relaxed text-[#E8EEF4] [&_h1]:mb-2 [&_h1]:text-lg [&_h1]:font-semibold [&_h2]:mb-2 [&_h2]:text-base [&_h2]:font-semibold [&_p]:mb-2 [&_ul]:mb-2 [&_ul]:list-disc [&_ul]:pl-5"
                    v-html="wordHtml"
                />
            </div>

            <div v-else class="px-4 py-16 text-center text-sm text-[#8B9AAB]">
                Este tipo de archivo no tiene vista previa.
            </div>
        </div>

        <template #footer>
            <el-button @click="open = false">Cerrar</el-button>
            <a
                v-if="file?.url"
                :href="file.url"
                target="_blank"
                rel="noopener"
                class="el-button el-button--primary"
            >
                Descargar
            </a>
        </template>
    </el-dialog>
</template>
