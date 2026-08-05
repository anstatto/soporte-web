<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { toast } from 'vue-sonner';

const emit = defineEmits(['recorded', 'cancel']);

const recording = ref(false);
const seconds = ref(0);
const supported = computed(() => typeof window !== 'undefined' && !!(navigator.mediaDevices?.getUserMedia && window.MediaRecorder));

let mediaStream = null;
let mediaRecorder = null;
let chunks = [];
let tickTimer = null;
let startedAt = 0;

const MAX_SECONDS = 120;

const fmt = (sec) => {
    const s = Math.max(0, Math.floor(sec));
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
};

const pickMime = () => {
    const candidates = [
        'audio/webm;codecs=opus',
        'audio/webm',
        'audio/mp4',
        'audio/ogg;codecs=opus',
        'audio/ogg',
    ];
    for (const t of candidates) {
        if (window.MediaRecorder.isTypeSupported?.(t)) return t;
    }
    return '';
};

const cleanup = () => {
    if (tickTimer) {
        clearInterval(tickTimer);
        tickTimer = null;
    }
    mediaRecorder = null;
    chunks = [];
    if (mediaStream) {
        mediaStream.getTracks().forEach((t) => t.stop());
        mediaStream = null;
    }
};

const stopInternal = ({ emitFile }) => {
    if (!mediaRecorder) return;
    const rec = mediaRecorder;
    const mime = rec.mimeType || pickMime() || 'audio/webm';
    rec.onstop = () => {
        const blob = new Blob(chunks, { type: mime });
        cleanup();
        recording.value = false;
        seconds.value = 0;
        if (!emitFile) {
            emit('cancel');
            return;
        }
        if (blob.size < 800) {
            toast.error('Grabación demasiado corta');
            emit('cancel');
            return;
        }
        const ext = mime.includes('mp4') ? 'm4a' : mime.includes('ogg') ? 'ogg' : 'webm';
        const file = new File([blob], `nota-voz-${Date.now()}.${ext}`, { type: mime.split(';')[0] });
        emit('recorded', file);
    };
    if (rec.state !== 'inactive') rec.stop();
    else {
        cleanup();
        recording.value = false;
    }
};

const start = async () => {
    if (!supported.value) {
        toast.error('Este navegador no permite grabar audio');
        return;
    }
    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const mime = pickMime();
        mediaRecorder = mime
            ? new MediaRecorder(mediaStream, { mimeType: mime })
            : new MediaRecorder(mediaStream);
        chunks = [];
        mediaRecorder.ondataavailable = (e) => {
            if (e.data?.size) chunks.push(e.data);
        };
        mediaRecorder.start(250);
        recording.value = true;
        startedAt = Date.now();
        seconds.value = 0;
        tickTimer = setInterval(() => {
            seconds.value = Math.floor((Date.now() - startedAt) / 1000);
            if (seconds.value >= MAX_SECONDS) stopInternal({ emitFile: true });
        }, 250);
    } catch {
        cleanup();
        recording.value = false;
        toast.error('No se pudo acceder al micrófono. Revisa permisos.');
    }
};

const stopAndSend = () => stopInternal({ emitFile: true });
const cancel = () => stopInternal({ emitFile: false });

const toggle = () => {
    if (recording.value) stopAndSend();
    else start();
};

onBeforeUnmount(() => {
    if (recording.value) stopInternal({ emitFile: false });
    else cleanup();
});

defineExpose({ recording, start, stopAndSend, cancel });
</script>

<template>
    <div class="flex items-center gap-1">
        <template v-if="recording">
            <button
                type="button"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#C4554D]/20 text-[#F87168] hover:bg-[#C4554D]/30"
                title="Cancelar"
                @click="cancel"
            >
                <el-icon :size="16"><Close /></el-icon>
            </button>
            <span class="flex items-center gap-1.5 rounded-full bg-[#C4554D]/15 px-2.5 py-1 text-[11px] font-semibold text-[#F87168]">
                <span class="h-2 w-2 animate-pulse rounded-full bg-[#EF5C48]" />
                {{ fmt(seconds) }}
            </span>
            <button
                type="button"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#25D366] text-[#052e16] hover:bg-[#2ee06f]"
                title="Enviar nota de voz"
                @click="stopAndSend"
            >
                <el-icon :size="16"><Check /></el-icon>
            </button>
        </template>
        <button
            v-else
            type="button"
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[#8B9AAB] transition hover:bg-white/5 hover:text-[#25D366] disabled:opacity-40"
            :disabled="!supported"
            title="Nota de voz"
            @click="toggle"
        >
            <el-icon :size="18"><Microphone /></el-icon>
        </button>
    </div>
</template>
