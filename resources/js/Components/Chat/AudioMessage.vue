<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    src: { type: String, required: true },
    mine: { type: Boolean, default: false },
    name: { type: String, default: 'Nota de voz' },
});

const audioRef = ref(null);
const playing = ref(false);
const duration = ref(0);
const current = ref(0);
const error = ref('');

const progress = computed(() => (duration.value > 0 ? (current.value / duration.value) * 100 : 0));

const fmt = (sec) => {
    if (!Number.isFinite(sec) || sec < 0) return '0:00';
    const s = Math.floor(sec);
    const m = Math.floor(s / 60);
    const r = String(s % 60).padStart(2, '0');
    return `${m}:${r}`;
};

const onMeta = () => {
    const a = audioRef.value;
    if (!a) return;
    if (Number.isFinite(a.duration)) duration.value = a.duration;
};

const onTime = () => {
    current.value = audioRef.value?.currentTime || 0;
};

const onEnded = () => {
    playing.value = false;
    current.value = 0;
};

const toggle = async () => {
    const a = audioRef.value;
    if (!a) return;
    error.value = '';
    try {
        if (playing.value) {
            a.pause();
            playing.value = false;
            return;
        }
        await a.play();
        playing.value = true;
    } catch {
        error.value = 'No se pudo reproducir';
        playing.value = false;
    }
};

const seek = (e) => {
    const a = audioRef.value;
    if (!a || !duration.value) return;
    const rect = e.currentTarget.getBoundingClientRect();
    const ratio = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
    a.currentTime = ratio * duration.value;
};

watch(
    () => props.src,
    () => {
        playing.value = false;
        current.value = 0;
        duration.value = 0;
        error.value = '';
    },
);

onBeforeUnmount(() => {
    audioRef.value?.pause();
});
</script>

<template>
    <div
        class="flex min-w-[200px] max-w-full items-center gap-2 rounded-xl px-1 py-0.5"
        :class="mine ? 'text-white' : 'text-[#E8EEF4]'"
    >
        <audio
            ref="audioRef"
            :src="src"
            preload="metadata"
            class="hidden"
            @loadedmetadata="onMeta"
            @durationchange="onMeta"
            @timeupdate="onTime"
            @ended="onEnded"
            @error="error = 'Audio no disponible'"
        />
        <button
            type="button"
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full transition"
            :class="mine
                ? 'bg-white/20 hover:bg-white/30'
                : 'bg-[#25D366]/25 text-[#25D366] hover:bg-[#25D366]/35'"
            :title="playing ? 'Pausar' : 'Reproducir'"
            @click="toggle"
        >
            <el-icon :size="16">
                <VideoPause v-if="playing" />
                <VideoPlay v-else />
            </el-icon>
        </button>
        <div class="min-w-0 flex-1">
            <button
                type="button"
                class="relative mb-1 h-1.5 w-full overflow-hidden rounded-full"
                :class="mine ? 'bg-white/25' : 'bg-white/15'"
                @click="seek"
            >
                <span
                    class="absolute inset-y-0 left-0 rounded-full"
                    :class="mine ? 'bg-white' : 'bg-[#25D366]'"
                    :style="{ width: `${progress}%` }"
                />
            </button>
            <div class="flex items-center justify-between gap-2 text-[10px] tabular-nums opacity-80">
                <span>{{ fmt(current) }}</span>
                <span class="truncate">{{ name }}</span>
                <span>{{ duration ? fmt(duration) : '--:--' }}</span>
            </div>
            <p v-if="error" class="mt-0.5 text-[10px] text-[#F87168]">{{ error }}</p>
        </div>
    </div>
</template>
