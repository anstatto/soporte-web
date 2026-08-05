<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import { Room, RoomEvent, Track, VideoPresets } from 'livekit-client';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    phase: { type: String, default: 'idle' }, // idle | outgoing | incoming | active
    call: { type: Object, default: null },
    token: { type: String, default: null },
    muted: { type: Boolean, default: false },
    cameraOff: { type: Boolean, default: true },
});

const emit = defineEmits([
    'accept',
    'decline',
    'end',
    'failed',
    'update:muted',
    'update:cameraOff',
]);

const page = usePage();
const maxCallMinutes = computed(() => Number(page.props.livekit?.max_call_minutes) || 0);
const videoQuality = computed(() => page.props.livekit?.video_quality || 'low');

const localVideoEl = ref(null);
const remoteVideoEl = ref(null);
const remoteAudioEl = ref(null);
const connecting = ref(false);
const hasRemoteVideo = ref(false);
const hasRemote = ref(false);
const minimized = ref(false);
const elapsed = ref(0);
const connectError = ref('');

let room = null;
let elapsedTimer = null;
let activeSince = null;
let intentionalDisconnect = false;
let maxDurationTimer = null;

const clearMaxDuration = () => {
    if (maxDurationTimer) {
        clearTimeout(maxDurationTimer);
        maxDurationTimer = null;
    }
};

const armMaxDuration = () => {
    clearMaxDuration();
    const mins = maxCallMinutes.value;
    if (!mins || mins <= 0) return;
    maxDurationTimer = setTimeout(() => {
        emit('end');
    }, mins * 60 * 1000);
};

const roomOptions = () => {
    const low = videoQuality.value !== 'medium';
    const preset = low ? VideoPresets.h180 : VideoPresets.h360;
    return {
        adaptiveStream: true,
        dynacast: true,
        videoCaptureDefaults: {
            resolution: preset.resolution,
        },
        publishDefaults: {
            videoSimulcastLayers: low ? [VideoPresets.h180] : [VideoPresets.h180, VideoPresets.h360],
            videoCodec: 'vp8',
            dtx: true,
            red: true,
        },
    };
};

const visible = computed(() => props.phase !== 'idle' && props.call);
const peerLabel = computed(
    () => props.call?.peer_name || props.call?.caller_name || 'Usuario',
);
const isVideoCall = computed(() => !!props.call?.video);
const initial = computed(() => (peerLabel.value || '?').charAt(0).toUpperCase());

const statusText = computed(() => {
    if (connectError.value) return connectError.value;
    if (props.phase === 'outgoing') return 'Llamando…';
    if (props.phase === 'incoming') {
        return props.call?.video ? 'Videollamada entrante' : 'Llamada entrante';
    }
    if (connecting.value) return 'Conectando…';
    if (!hasRemote.value && props.phase === 'active') return 'Esperando al otro…';
    return isVideoCall.value ? 'En videollamada' : 'En llamada';
});

const elapsedLabel = computed(() => {
    const s = elapsed.value;
    const m = Math.floor(s / 60);
    const r = String(s % 60).padStart(2, '0');
    return `${m}:${r}`;
});

const maxLabel = computed(() => {
    const mins = maxCallMinutes.value;
    if (!mins) return '';
    return ` · máx. ${mins} min`;
});

const showVideoStage = computed(
    () =>
        isVideoCall.value &&
        (props.phase === 'active' || props.phase === 'outgoing') &&
        !minimized.value,
);

const attachTrack = (track, el) => {
    if (!el || !track) return;
    track.attach(el);
};

const detachAll = () => {
    if (localVideoEl.value) localVideoEl.value.srcObject = null;
    if (remoteVideoEl.value) remoteVideoEl.value.srcObject = null;
    if (remoteAudioEl.value) remoteAudioEl.value.srcObject = null;
    hasRemoteVideo.value = false;
    hasRemote.value = false;
};

const stopElapsed = () => {
    if (elapsedTimer) {
        clearInterval(elapsedTimer);
        elapsedTimer = null;
    }
    activeSince = null;
    elapsed.value = 0;
    clearMaxDuration();
};

const startElapsed = () => {
    stopElapsed();
    activeSince = Date.now();
    elapsedTimer = setInterval(() => {
        elapsed.value = Math.floor((Date.now() - activeSince) / 1000);
    }, 1000);
    armMaxDuration();
};

const disconnectRoom = async () => {
    if (room) {
        intentionalDisconnect = true;
        try {
            await room.disconnect();
        } catch {
            /* ignore */
        }
        room = null;
        intentionalDisconnect = false;
    }
    detachAll();
    connecting.value = false;
};

const bindParticipant = (participant) => {
    hasRemote.value = true;
    participant.trackPublications.forEach((pub) => {
        if (pub.track) onTrackSubscribed(pub.track, pub, participant);
    });
};

const onTrackSubscribed = (track) => {
    if (track.kind === Track.Kind.Audio) {
        attachTrack(track, remoteAudioEl.value);
    }
    if (track.kind === Track.Kind.Video) {
        hasRemoteVideo.value = true;
        attachTrack(track, remoteVideoEl.value);
    }
};

const onTrackUnsubscribed = (track) => {
    track.detach();
    if (track.kind === Track.Kind.Video) {
        hasRemoteVideo.value = false;
    }
};

const attachLocalCamera = async () => {
    await nextTick();
    if (!room?.localParticipant || !localVideoEl.value) return;
    const camPub = [...room.localParticipant.trackPublications.values()].find(
        (p) => p.track && p.track.kind === Track.Kind.Video,
    );
    if (camPub?.track) attachTrack(camPub.track, localVideoEl.value);
    else localVideoEl.value.srcObject = null;
};

const connectRoom = async () => {
    if (!props.token || !props.call?.url) return;
    if (room) return;

    connecting.value = true;
    connectError.value = '';
    try {
        const r = new Room(roomOptions());
        room = r;

        r.on(RoomEvent.TrackSubscribed, onTrackSubscribed);
        r.on(RoomEvent.TrackUnsubscribed, onTrackUnsubscribed);
        r.on(RoomEvent.ParticipantConnected, (p) => {
            hasRemote.value = true;
            bindParticipant(p);
        });
        r.on(RoomEvent.ParticipantDisconnected, () => {
            hasRemote.value = r.remoteParticipants.size > 0;
            if (!hasRemote.value) hasRemoteVideo.value = false;
        });
        r.on(RoomEvent.Disconnected, () => {
            room = null;
            if (intentionalDisconnect) return;
            if (props.phase === 'active' || props.phase === 'outgoing') {
                emit('end');
            }
        });

        await r.connect(props.call.url, props.token);
        await r.localParticipant.setMicrophoneEnabled(!props.muted);
        const wantCam = isVideoCall.value && !props.cameraOff;
        await r.localParticipant.setCameraEnabled(wantCam);
        await attachLocalCamera();
        r.remoteParticipants.forEach((p) => bindParticipant(p));
    } catch (e) {
        console.warn('[LiveKit] connect', e);
        connectError.value = 'No se pudo conectar al media';
        await disconnectRoom();
        emit('failed', e?.message || 'error');
    } finally {
        connecting.value = false;
    }
};

watch(
    () => [props.phase, props.token],
    async ([phase, token]) => {
        if (phase === 'active') {
            if (!activeSince) startElapsed();
        } else if (phase !== 'outgoing') {
            stopElapsed();
        }

        if ((phase === 'outgoing' || phase === 'active') && token) {
            await connectRoom();
        }
        if (phase === 'idle' || phase === 'incoming') {
            await disconnectRoom();
            minimized.value = false;
            connectError.value = '';
        }
        if (phase === 'idle') stopElapsed();
    },
);

watch(
    () => props.muted,
    async (muted) => {
        if (room?.localParticipant) {
            await room.localParticipant.setMicrophoneEnabled(!muted);
        }
    },
);

watch(
    () => props.cameraOff,
    async (off) => {
        if (room?.localParticipant && isVideoCall.value) {
            await room.localParticipant.setCameraEnabled(!off);
            await attachLocalCamera();
        }
    },
);

onUnmounted(() => {
    stopElapsed();
    disconnectRoom();
});

const toggleMute = () => emit('update:muted', !props.muted);
const toggleCam = () => emit('update:cameraOff', !props.cameraOff);
const toggleMin = () => {
    minimized.value = !minimized.value;
};
</script>

<template>
    <Teleport to="body">
        <!-- Barra flotante minimizada -->
        <button
            v-if="visible && minimized"
            type="button"
            class="fixed bottom-20 right-4 z-[10000] flex items-center gap-3 rounded-full bg-[#1D2125] px-4 py-2.5 shadow-2xl ring-1 ring-[#25D366]/50 md:bottom-6"
            @click="toggleMin"
        >
            <span class="relative flex h-9 w-9 items-center justify-center rounded-full bg-[#2a3340] text-sm font-semibold text-white">
                {{ initial }}
                <span class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-[#25D366]" />
            </span>
            <span class="text-left">
                <span class="block max-w-[140px] truncate text-sm font-medium text-white">{{ peerLabel }}</span>
                <span class="block text-[11px] text-[#8B9AAB]">
                    {{ phase === 'active' ? elapsedLabel : statusText }}
                </span>
            </span>
            <el-button type="danger" circle size="small" @click.stop="emit('end')">
                <el-icon><Phone /></el-icon>
            </el-button>
        </button>

        <div
            v-if="visible && !minimized"
            class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
        >
            <div
                class="relative flex w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-[#1D2125] shadow-2xl ring-1 ring-white/10"
                :class="showVideoStage ? 'min-h-[420px]' : ''"
            >
                <div class="flex items-center justify-between border-b border-[#2a3340] px-4 py-2">
                    <p class="truncate text-xs font-medium text-[#8B9AAB]">
                        <template v-if="phase === 'active'">{{ elapsedLabel }}{{ maxLabel }}</template>
                        <template v-else>{{ statusText }}</template>
                    </p>
                    <el-button
                        v-if="phase === 'active' || phase === 'outgoing'"
                        text
                        circle
                        size="small"
                        title="Minimizar"
                        @click="toggleMin"
                    >
                        <el-icon><Minus /></el-icon>
                    </el-button>
                </div>

                <!-- Video -->
                <div v-if="showVideoStage" class="relative aspect-video w-full bg-[#0b0f14]">
                    <video
                        ref="remoteVideoEl"
                        class="h-full w-full object-cover"
                        :class="hasRemoteVideo ? 'opacity-100' : 'opacity-0'"
                        autoplay
                        playsinline
                    />
                    <div
                        v-if="!hasRemoteVideo"
                        class="absolute inset-0 flex flex-col items-center justify-center gap-3"
                    >
                        <div
                            class="flex h-20 w-20 items-center justify-center rounded-full bg-[#2a3340] text-2xl font-semibold text-white"
                            :class="phase === 'outgoing' ? 'call-pulse' : ''"
                        >
                            {{ initial }}
                        </div>
                        <p class="text-sm text-[#8B9AAB]">{{ statusText }}</p>
                    </div>
                    <video
                        ref="localVideoEl"
                        class="absolute bottom-3 right-3 h-28 w-20 scale-x-[-1] rounded-lg bg-[#12171d] object-cover ring-1 ring-white/20"
                        :class="cameraOff ? 'opacity-0 pointer-events-none' : 'opacity-100'"
                        autoplay
                        playsinline
                        muted
                    />
                    <div
                        v-if="cameraOff && isVideoCall"
                        class="absolute bottom-3 right-3 flex h-28 w-20 items-center justify-center rounded-lg bg-[#12171d] text-[10px] text-[#8B9AAB] ring-1 ring-white/10"
                    >
                        Cámara off
                    </div>
                </div>

                <!-- Audio / ringing -->
                <div v-else class="flex flex-col items-center gap-4 px-6 py-10">
                    <div
                        class="flex h-24 w-24 items-center justify-center rounded-full bg-[#2a3340] text-3xl font-semibold text-white"
                        :class="phase === 'incoming' || phase === 'outgoing' ? 'call-pulse' : ''"
                    >
                        {{ initial }}
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-semibold text-white">{{ peerLabel }}</p>
                        <p class="mt-1 text-sm text-[#8B9AAB]">
                            <template v-if="phase === 'active'">{{ elapsedLabel }} · {{ statusText }}</template>
                            <template v-else>{{ statusText }}</template>
                        </p>
                    </div>
                </div>

                <audio ref="remoteAudioEl" autoplay />

                <!-- Controls -->
                <div class="flex items-center justify-center gap-3 border-t border-[#2a3340] px-4 py-4">
                    <template v-if="phase === 'incoming'">
                        <el-button type="danger" round @click="emit('decline')">
                            <el-icon class="mr-1"><CircleClose /></el-icon>
                            Rechazar
                        </el-button>
                        <el-button type="success" round @click="emit('accept')">
                            <el-icon class="mr-1"><Phone /></el-icon>
                            Aceptar
                        </el-button>
                    </template>

                    <template v-else>
                        <el-button
                            circle
                            :type="muted ? 'danger' : 'default'"
                            :title="muted ? 'Activar micrófono' : 'Silenciar'"
                            @click="toggleMute"
                        >
                            <el-icon>
                                <Mute v-if="muted" />
                                <Microphone v-else />
                            </el-icon>
                        </el-button>
                        <el-button
                            v-if="isVideoCall && phase !== 'outgoing'"
                            circle
                            :type="cameraOff ? 'info' : 'default'"
                            :title="cameraOff ? 'Encender cámara' : 'Apagar cámara'"
                            @click="toggleCam"
                        >
                            <el-icon><VideoCamera /></el-icon>
                        </el-button>
                        <el-button type="danger" round @click="emit('end')">
                            <el-icon class="mr-1"><Phone /></el-icon>
                            {{ phase === 'outgoing' ? 'Cancelar' : 'Colgar' }}
                        </el-button>
                    </template>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.call-pulse {
    animation: call-pulse 1.4s ease-out infinite;
}
@keyframes call-pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.45);
    }
    70% {
        box-shadow: 0 0 0 18px rgba(37, 211, 102, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(37, 211, 102, 0);
    }
}
</style>
