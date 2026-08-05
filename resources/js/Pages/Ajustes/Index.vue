<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { toast } from 'vue-sonner';

const props = defineProps({
    settings: Object,
    envInfo: Object,
});

const form = useForm({
    ...props.settings,
    tickets_per_page: Number(props.settings?.tickets_per_page) || 24,
    livekit_enabled: ['1', 'true', true, 1].includes(props.settings?.livekit_enabled),
    livekit_allow_video: ['1', 'true', true, 1].includes(props.settings?.livekit_allow_video),
    livekit_ring_timeout: Number(props.settings?.livekit_ring_timeout) || 45,
    livekit_max_call_minutes: Number(props.settings?.livekit_max_call_minutes ?? 20),
    livekit_video_quality: props.settings?.livekit_video_quality || 'low',
    livekit_api_secret: '',
});

const testTo = ref(props.settings?.support_email || props.settings?.mail_from_address || '');
const testing = ref(false);

const mailerLabel = computed(() => {
    const m = props.envInfo?.mail_mailer || 'log';
    if (m === 'log') return 'log (solo escribe en storage/logs)';
    if (m === 'smtp') return `smtp → ${props.envInfo?.mail_host || 'sin host'}`;
    return m;
});

const submit = () => {
    form.put('/ajustes', { preserveScroll: true });
};

const sendTest = async () => {
    if (!testTo.value) {
        toast.error('Indica un email de destino');
        return;
    }
    testing.value = true;
    try {
        await axios.post('/ajustes/test-mail', { to: testTo.value });
        toast.success('Correo de prueba enviado');
    } catch (err) {
        toast.error(err?.response?.data?.message || 'No se pudo enviar el correo');
    } finally {
        testing.value = false;
    }
};
</script>

<template>
    <Head title="Ajustes del sistema" />
    <div class="mb-4">
        <h2 class="font-display text-2xl font-semibold text-white">Ajustes del sistema</h2>
        <p class="text-sm text-[#8B9AAB]">
            Branding, pie de reportes y remitente de correo. SMTP host/credenciales viven en el `.env` del servidor.
        </p>
    </div>

    <div class="mx-auto grid max-w-5xl gap-4 lg:grid-cols-[1fr_280px]">
        <div class="space-y-4">
            <section class="rounded-[10px] border border-[#2a3340] bg-[#12171d] p-5">
                <div class="mb-4 flex items-center gap-2 text-white">
                    <el-icon :size="18" class="text-[#579DFF]"><OfficeBuilding /></el-icon>
                    <h3 class="font-medium">Empresa y marca</h3>
                </div>
                <el-form label-position="top" @submit.prevent="submit">
                    <div class="grid gap-3 md:grid-cols-2">
                        <el-form-item label="Nombre de la app" :error="form.errors.app_name">
                            <el-input v-model="form.app_name" placeholder="RM Consuegra Soporte" />
                        </el-form-item>
                        <el-form-item label="Empresa" :error="form.errors.company_name">
                            <el-input v-model="form.company_name" placeholder="RM Consuegra SRL" />
                        </el-form-item>
                        <el-form-item label="Email de soporte" :error="form.errors.support_email" class="md:col-span-2">
                            <el-input v-model="form.support_email" type="email" placeholder="soporte@empresa.com" />
                        </el-form-item>
                        <el-form-item label="Pie de reportes PDF" :error="form.errors.report_footer" class="md:col-span-2">
                            <el-input
                                v-model="form.report_footer"
                                type="textarea"
                                :rows="2"
                                placeholder="Texto al pie de los PDF"
                            />
                        </el-form-item>
                        <el-form-item label="Tickets por página (board)" :error="form.errors.tickets_per_page">
                            <el-input-number v-model="form.tickets_per_page" :min="10" :max="100" class="!w-full" />
                        </el-form-item>
                    </div>
                </el-form>
            </section>

            <section class="rounded-[10px] border border-[#2a3340] bg-[#12171d] p-5">
                <div class="mb-4 flex items-center gap-2 text-white">
                    <el-icon :size="18" class="text-[#57D9A3]"><Message /></el-icon>
                    <h3 class="font-medium">Correo saliente</h3>
                </div>
                <el-form label-position="top" @submit.prevent="submit">
                    <div class="grid gap-3 md:grid-cols-2">
                        <el-form-item label="Nombre del remitente" :error="form.errors.mail_from_name">
                            <el-input v-model="form.mail_from_name" />
                        </el-form-item>
                        <el-form-item label="Email del remitente" :error="form.errors.mail_from_address">
                            <el-input v-model="form.mail_from_address" type="email" placeholder="noreply@empresa.com" />
                        </el-form-item>
                    </div>
                    <div class="mt-2 rounded-[10px] border border-[#2a3340] bg-[#0f1419] p-3">
                        <p class="mb-2 text-xs text-[#8B9AAB]">
                            Mailer actual: <span class="text-[#E8EEF4]">{{ mailerLabel }}</span>
                        </p>
                        <div class="flex flex-wrap items-end gap-2">
                            <el-form-item label="Enviar prueba a" class="!mb-0 flex-1">
                                <el-input v-model="testTo" type="email" placeholder="tu@correo.com" />
                            </el-form-item>
                            <el-button :loading="testing" @click="sendTest">
                                <el-icon class="mr-1"><Promotion /></el-icon>
                                Probar correo
                            </el-button>
                        </div>
                    </div>
                </el-form>
            </section>

            <section class="rounded-[10px] border border-[#2a3340] bg-[#12171d] p-5">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2 text-white">
                        <el-icon :size="18" class="text-[#9F8FEF]"><Phone /></el-icon>
                        <h3 class="font-medium">Llamadas y videollamadas (LiveKit)</h3>
                    </div>
                    <el-tag :type="envInfo.livekit_ready ? 'success' : 'danger'" effect="plain" size="small">
                        {{ envInfo.livekit_ready ? 'Listo' : 'No listo' }}
                    </el-tag>
                </div>
                <p class="mb-4 text-sm text-[#8B9AAB]">
                    Usa el plan <strong class="text-[#E8EEF4]">Build</strong> de
                    <a href="https://cloud.livekit.io" target="_blank" rel="noopener" class="text-[#579DFF] underline">LiveKit Cloud</a>
                    (0 $/mes, sin tarjeta). Incluye ~5.000 minutos-participante WebRTC/mes; al agotarse
                    <strong class="text-[#E8EEF4]">no cobra</strong>, solo deja de conectar.
                    Por defecto dejamos <strong class="text-[#E8EEF4]">solo audio</strong> (mucho más barato en datos).
                </p>
                <el-alert
                    type="success"
                    :closable="false"
                    show-icon
                    class="!mb-4"
                    title="Modo económico activo por defecto"
                    description="Audio 1:1 · video off · corte a 20 min · calidad video low si lo activas. Estima ~2.500 min de llamadas 1:1 (≈40 h) al mes en el plan gratis."
                />
                <el-form label-position="top" @submit.prevent="submit">
                    <el-form-item label="Activar llamadas">
                        <el-switch v-model="form.livekit_enabled" active-text="Sí" inactive-text="No" />
                    </el-form-item>
                    <div class="grid gap-3 md:grid-cols-2">
                        <el-form-item label="URL (wss://…)" :error="form.errors.livekit_url" class="md:col-span-2">
                            <el-input
                                v-model="form.livekit_url"
                                placeholder="wss://tu-proyecto.livekit.cloud"
                            />
                        </el-form-item>
                        <el-form-item label="API Key" :error="form.errors.livekit_api_key">
                            <el-input v-model="form.livekit_api_key" placeholder="APIxxxxxxxx" />
                        </el-form-item>
                        <el-form-item
                            :label="settings.livekit_api_secret_set ? 'API Secret (dejar vacío para no cambiar)' : 'API Secret'"
                            :error="form.errors.livekit_api_secret"
                        >
                            <el-input
                                v-model="form.livekit_api_secret"
                                type="password"
                                show-password
                                :placeholder="settings.livekit_api_secret_set ? '•••••••• (ya guardado)' : 'Secret'"
                            />
                        </el-form-item>
                        <el-form-item label="Permitir videollamadas" :error="form.errors.livekit_allow_video">
                            <el-switch v-model="form.livekit_allow_video" active-text="Sí" inactive-text="Solo audio" />
                        </el-form-item>
                        <el-form-item label="Calidad video" :error="form.errors.livekit_video_quality">
                            <el-select v-model="form.livekit_video_quality" class="!w-full" :disabled="!form.livekit_allow_video">
                                <el-option label="Baja (recomendado / gratis)" value="low" />
                                <el-option label="Media" value="medium" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="Timeout ringing (seg)" :error="form.errors.livekit_ring_timeout">
                            <el-input-number v-model="form.livekit_ring_timeout" :min="15" :max="180" class="!w-full" />
                        </el-form-item>
                        <el-form-item
                            label="Corte automático (minutos, 0 = sin límite)"
                            :error="form.errors.livekit_max_call_minutes"
                        >
                            <el-input-number v-model="form.livekit_max_call_minutes" :min="0" :max="180" class="!w-full" />
                        </el-form-item>
                    </div>
                </el-form>
            </section>

            <div class="flex justify-end gap-2">
                <el-button type="primary" :loading="form.processing" @click="submit">
                    <el-icon class="mr-1"><Check /></el-icon>
                    Guardar ajustes
                </el-button>
            </div>
        </div>

        <aside class="h-fit rounded-[10px] border border-[#2a3340] bg-[#12171d] p-5">
            <div class="mb-3 flex items-center gap-2 text-white">
                <el-icon :size="18" class="text-[#F5CD47]"><Monitor /></el-icon>
                <h3 class="font-medium">Servidor</h3>
            </div>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-[#8B9AAB]">Entorno</dt>
                    <dd class="text-[#E8EEF4]">{{ envInfo.app_env }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-[#8B9AAB]">URL</dt>
                    <dd class="break-all text-[#E8EEF4]">{{ envInfo.app_url }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-[#8B9AAB]">Timezone</dt>
                    <dd class="text-[#E8EEF4]">{{ envInfo.timezone }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-[#8B9AAB]">Cola</dt>
                    <dd class="text-[#E8EEF4]">{{ envInfo.queue }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-[#8B9AAB]">Broadcast</dt>
                    <dd class="text-[#E8EEF4]">{{ envInfo.broadcast }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-[#8B9AAB]">LiveKit (llamadas)</dt>
                    <dd class="text-[#E8EEF4]">
                        <span :class="envInfo.livekit_ready ? 'text-[#57D9A3]' : 'text-[#EF5C48]'">
                            {{ envInfo.livekit_ready ? 'Listo' : 'No configurado' }}
                        </span>
                        <span v-if="envInfo.livekit_source" class="ml-1 text-xs text-[#8B9AAB]">
                            ({{ envInfo.livekit_source === 'database' ? 'Ajustes' : '.env' }})
                        </span>
                    </dd>
                    <dd v-if="envInfo.livekit_url" class="mt-1 break-all text-xs text-[#8B9AAB]">
                        {{ envInfo.livekit_url }}
                    </dd>
                </div>
            </dl>
            <p class="mt-4 text-xs leading-relaxed text-[#8B9AAB]">
                Para cambiar SMTP, timezone o URL edita el <code class="text-[#E8EEF4]">.env</code> en el servidor y corre
                <code class="text-[#E8EEF4]">php artisan config:cache</code>.
            </p>
        </aside>
    </div>
</template>
