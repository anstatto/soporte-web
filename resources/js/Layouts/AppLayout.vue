<script setup>
import { computed, onMounted, onUnmounted, provide, ref, watch } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import { Toaster, toast } from 'vue-sonner';
import { onClickOutside, useIntervalFn, useLocalStorage } from '@vueuse/core';
import axios from 'axios';
import { usePermissions } from '@/Composables/usePermissions';
import { timeAgo } from '@/Composables/useDate';
import CreateTicketModal from '@/Components/Tickets/CreateTicketModal.vue';
import 'vue-sonner/style.css';

const page = usePage();
const { can, isAdmin, isSoporte, user } = usePermissions();
const isEndUser = computed(() => !isSoporte.value);

const navItems = computed(() => {
    if (isEndUser.value) {
        return [
            { label: 'Portal', href: '/portal', icon: 'House', match: 'portal', show: can('view tickets') },
            { label: 'Mis chats', href: '/tickets', icon: 'ChatDotRound', match: 'inbox', show: can('view tickets') },
            { label: 'Notificaciones', href: '/notificaciones', icon: 'Bell', match: 'notifs', show: true },
        ].filter((i) => i.show);
    }
    return [
        { label: 'Portal', href: '/portal', icon: 'House', match: 'portal', show: can('view tickets') },
        { label: 'Tablero', href: '/tickets/board', icon: 'Grid', match: 'board', show: can('view tickets') },
        { label: 'Bandeja', href: '/tickets', icon: 'Message', match: 'inbox', show: can('view tickets') },
        { label: 'Notificaciones', href: '/notificaciones', icon: 'Bell', match: 'notifs', show: true },
        { label: 'Reportes', href: '/reportes', icon: 'DataAnalysis', match: 'reports', show: can('view reports') },
    ].filter((i) => i.show);
});

const configItems = computed(() => {
    if (isEndUser.value) {
        return [{ label: 'Mi perfil', href: '/perfil', icon: 'User', show: true }];
    }
    return [
        { label: 'Usuarios', href: '/users', icon: 'User', show: can('view users') || can('manage users') },
        { label: 'Áreas de trabajo', href: '/workspaces', icon: 'OfficeBuilding', show: isAdmin.value },
        { label: 'Departamentos', href: '/departamentos', icon: 'Folder', show: can('view departamento') && isAdmin.value },
        { label: 'Estados', href: '/estados', icon: 'CollectionTag', show: can('view estado') && isAdmin.value },
        { label: 'Roles', href: '/roles', icon: 'Key', show: isAdmin.value },
        { label: 'Perfil', href: '/perfil', icon: 'Avatar', show: true },
    ].filter((i) => i.show);
});

const configHref = computed(() => configItems.value[0]?.href || '/perfil');
const configMenuLabel = computed(() => (isEndUser.value ? 'Cuenta' : 'Configuración'));
const hasConfigSubmenu = computed(() => !isEndUser.value && configItems.value.length > 1);

const notificationsOpen = ref(false);
const notifications = ref([]);
const unreadCount = ref(page.props.unreadNotificationsCount || 0);
const notifsReady = ref(false);
const realtimeNotifs = ref(false);
const notifRef = ref(null);
const globalSearch = ref('');
const searchInputRef = ref(null);
const sidebarCollapsed = useLocalStorage('soporte-sidebar-collapsed', false);
const configOpen = ref(false);
const configFlyoutOpen = ref(false);
const workspaceFlyoutOpen = ref(false);
const createModalRef = ref(null);

onClickOutside(notifRef, () => { notificationsOpen.value = false; });

provide('appNotifications', notifications);
provide('appUnreadCount', unreadCount);

const toggleConfigMenu = () => {
    if (isEndUser.value) {
        go(configHref.value);
        return;
    }
    if (sidebarCollapsed.value) {
        configFlyoutOpen.value = !configFlyoutOpen.value;
        return;
    }
    configOpen.value = !configOpen.value;
};

const roleLabel = computed(() => {
    if (isAdmin.value) return 'Administrador';
    if (isSoporte.value) return 'Soporte';
    return 'Solicitante';
});

const pushRealtimeNotification = (payload) => {
    const data = {
        type: payload.type,
        ticket_id: payload.ticket_id,
        ticket_title: payload.ticket_title,
        message: payload.message,
        excerpt: payload.excerpt,
        by: payload.by,
        assigned_by: payload.assigned_by,
        mentioned_by: payload.mentioned_by,
        from_estado: payload.from_estado,
        to_estado: payload.to_estado,
    };
    const item = {
        id: payload.id || `rt-${Date.now()}`,
        type: payload.type,
        data,
        created_at: new Date().toISOString(),
        read_at: null,
    };
    if (notifications.value.some((n) => n.id === item.id)) return;
    notifications.value = [item, ...notifications.value];
    unreadCount.value += 1;
    playNotifSound();
    toast.message(data.message || data.ticket_title || 'Nueva notificación', {
        description: data.excerpt || data.ticket_title,
        action: data.ticket_id
            ? { label: 'Abrir', onClick: () => viewNotification(item) }
            : undefined,
    });
};

const playNotifSound = () => {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        const ctx = new Ctx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = 880;
        gain.gain.value = 0.04;
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.18);
        osc.stop(ctx.currentTime + 0.2);
        setTimeout(() => ctx.close(), 300);
    } catch {
        // autoplay bloqueado hasta interacción
    }
};

const fetchNotifications = async () => {
    try {
        const { data } = await axios.get('/notifications');
        const list = data.notifications || data;
        const prevIds = new Set(notifications.value.map((n) => n.id));
        const fresh = list.filter((n) => !prevIds.has(n.id) && !n.read_at);
        notifications.value = list;
        unreadCount.value = data.unread_count ?? list.filter((n) => !n.read_at).length;

        if (notifsReady.value && fresh.length) {
            const newest = fresh[0];
            if (!realtimeNotifs.value) playNotifSound();
            toast.message(newest.data?.message || newest.data?.ticket_title || 'Nueva notificación', {
                description: newest.data?.excerpt || newest.data?.ticket_title,
                action: newest.data?.ticket_id
                    ? { label: 'Abrir', onClick: () => viewNotification(newest) }
                    : undefined,
            });
        }
        notifsReady.value = true;
    } catch { /* */ }
};

// Poll de respaldo cada 15s (Echo es el camino principal)
const { pause: pausePoll } = useIntervalFn(fetchNotifications, 15000, { immediateCallback: true });

let subscribedUid = null;

const subscribeNotifications = () => {
    const uid = user.value?.id;
    if (!window.Echo || !uid) {
        realtimeNotifs.value = false;
        return;
    }
    if (subscribedUid === uid && realtimeNotifs.value) return;

    if (subscribedUid && window.Echo) {
        window.Echo.leave(`App.Models.User.${subscribedUid}`);
    }

    try {
        const channel = window.Echo.private(`App.Models.User.${uid}`);
        channel.notification((n) => {
            pushRealtimeNotification(n);
        });
        channel.error?.((err) => {
            console.warn('[Echo] canal notificaciones', err);
            realtimeNotifs.value = false;
        });
        subscribedUid = uid;
        realtimeNotifs.value = true;
        if (import.meta.env.DEV) {
            console.info(`[Echo] suscrito a App.Models.User.${uid}`);
        }
    } catch (e) {
        console.warn('[Echo] no se pudo suscribir', e);
        realtimeNotifs.value = false;
    }
};

const unsubscribeNotifications = () => {
    if (window.Echo && subscribedUid) {
        window.Echo.leave(`App.Models.User.${subscribedUid}`);
    }
    subscribedUid = null;
    realtimeNotifs.value = false;
};

watch(() => user.value?.id, (id) => {
    if (id) {
        subscribeNotifications();
        fetchNotifications();
    } else {
        unsubscribeNotifications();
    }
});

const onGlobalKeydown = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        searchInputRef.value?.focus?.();
        const input = document.getElementById('global-search-input');
        input?.focus();
    }
};

onMounted(() => {
    subscribeNotifications();
    // Reintentar si Echo aún no estaba listo al montar
    setTimeout(subscribeNotifications, 800);
    window.addEventListener('keydown', onGlobalKeydown);
});

onUnmounted(() => {
    unsubscribeNotifications();
    pausePoll();
    window.removeEventListener('keydown', onGlobalKeydown);
});

const notificationLabel = (n) => {
    const d = n.data || {};
    if (d.message) return d.message;
    if (d.type === 'ticket_moved') {
        return `${d.by || 'Alguien'} movió «${d.ticket_title || 'ticket'}» a ${d.to_estado || 'otro estado'}`;
    }
    if (d.type === 'ticket_message') return `Nuevo mensaje · ${d.ticket_title}`;
    if (d.type === 'ticket_mentioned') return `${d.mentioned_by || d.by} te mencionó`;
    if (d.type === 'ticket_assigned') return `${d.assigned_by || d.by} te asignó un ticket`;
    return `${d.by || d.assigned_by || 'Sistema'} · ${d.ticket_title || 'Ticket'}`;
};

const viewNotification = async (notification) => {
    try {
        await axios.post(`/notifications/${notification.id}/mark-as-read`);
        notifications.value = notifications.value.filter((n) => n.id !== notification.id);
        unreadCount.value = Math.max(0, unreadCount.value - 1);
        notificationsOpen.value = false;
        const ticketId = notification.data?.ticket_id;
        if (ticketId) router.visit(`/tickets/board?card=${ticketId}`);
    } catch {
        toast.error('No se pudo abrir la notificación');
    }
};

const markAllNotificationsRead = async () => {
    try {
        await axios.post('/notifications/mark-all-read');
        notifications.value = [];
        unreadCount.value = 0;
    } catch {
        toast.error('No se pudieron marcar como leídas');
    }
};

const seenFlash = ref({ success: null, error: null, temp_password: null });
watch(() => page.props.flash, (flash) => {
    if (!flash) return;
    if (flash.success && flash.success !== seenFlash.value.success) {
        toast.success(flash.success);
        seenFlash.value.success = flash.success;
    }
    if (flash.error && flash.error !== seenFlash.value.error) {
        toast.error(flash.error);
        seenFlash.value.error = flash.error;
    }
    if (flash.temp_password && flash.temp_password !== seenFlash.value.temp_password) {
        toast.message(`Contraseña temporal: ${flash.temp_password}`, { duration: 15000 });
        seenFlash.value.temp_password = flash.temp_password;
    }
}, { deep: true, immediate: true });

const avatarUrl = computed(() =>
    `https://ui-avatars.com/api/?name=${encodeURIComponent(user.value?.name || 'U')}&background=579DFF&color=fff`,
);

const currentPath = computed(() => page.url.split('?')[0]);
const isBoard = computed(() => currentPath.value.startsWith('/tickets/board'));
const fullBleed = computed(() => isBoard.value || isInbox.value);
const isInbox = computed(() => currentPath.value === '/tickets' || /^\/tickets\/\d+/.test(currentPath.value));
const isReports = computed(() => currentPath.value.startsWith('/reportes'));
const isConfig = computed(() =>
    ['/users', '/workspaces', '/departamentos', '/estados', '/roles', '/perfil'].some(
        (p) => currentPath.value === p || currentPath.value.startsWith(`${p}/`),
    ),
);

watch(isConfig, (v) => { if (v) configOpen.value = true; }, { immediate: true });

watch(sidebarCollapsed, () => {
    configFlyoutOpen.value = false;
    workspaceFlyoutOpen.value = false;
});

watch(currentPath, () => {
    configFlyoutOpen.value = false;
    workspaceFlyoutOpen.value = false;
});

const isActiveNav = (item) => {
    if (item.match === 'board') return isBoard.value;
    if (item.match === 'inbox') return isInbox.value && !isBoard.value;
    if (item.match === 'reports') return isReports.value;
    if (item.match === 'portal') return currentPath.value.startsWith('/portal');
    if (item.match === 'notifs') return currentPath.value.startsWith('/notificaciones');
    return currentPath.value === item.href || currentPath.value.startsWith(`${item.href}/`);
};

const isActiveConfig = (href) =>
    currentPath.value === href || currentPath.value.startsWith(`${href}/`);

const go = (href) => router.visit(href, { preserveState: false });

const workspaces = computed(() => page.props.workspaces || []);
const currentWorkspaceId = computed({
    get: () => page.props.auth?.user?.current_workspace_id,
    set: (id) => {
        if (!id || id === page.props.auth?.user?.current_workspace_id) return;
        router.post('/workspaces/switch', { workspace_id: id }, { preserveScroll: true });
    },
});

const openCreateTicket = () => {
    createModalRef.value?.show?.();
    window.dispatchEvent(new CustomEvent('soporte:open-create-ticket'));
};

const submitGlobalSearch = () => {
    const q = globalSearch.value.trim();
    go(q ? `/tickets/board?q=${encodeURIComponent(q)}` : '/tickets/board');
};

const logout = () => router.post('/logout');
</script>

<template>
    <div class="workspace-shell flex min-h-screen bg-[#0f1419] text-[#E8EEF4]">
        <Toaster position="top-right" rich-colors theme="dark" />

        <!-- Sidebar izquierda -->
        <aside
            class="sidebar sticky top-0 z-40 flex h-screen shrink-0 flex-col border-r border-[#2a3340] bg-[#12171d] transition-[width] duration-200"
            :class="sidebarCollapsed ? 'w-[72px]' : 'w-[240px]'"
        >
            <div
                class="flex h-14 items-center border-b border-[#2a3340]"
                :class="sidebarCollapsed ? 'justify-center px-2' : 'gap-2.5 px-3'"
            >
                <img src="/images/LogoMono.png" alt="" class="h-8 w-8 shrink-0 object-contain" />
                <div v-if="!sidebarCollapsed" class="min-w-0 flex-1">
                    <p class="truncate font-display text-sm font-semibold tracking-tight text-white">
                        RM Consuegra
                    </p>
                    <el-select
                        v-if="workspaces.length"
                        v-model="currentWorkspaceId"
                        size="small"
                        class="workspace-switch mt-1 w-full"
                        placeholder="Área"
                    >
                        <el-option
                            v-for="w in workspaces"
                            :key="w.id"
                            :label="w.name"
                            :value="w.id"
                        />
                    </el-select>
                </div>
                <el-popover
                    v-else-if="workspaces.length"
                    v-model:visible="workspaceFlyoutOpen"
                    placement="right-start"
                    :width="200"
                    trigger="click"
                    popper-class="sidebar-flyout"
                >
                    <template #reference>
                        <button
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-[10px] text-[#8B9AAB] hover:bg-white/5 hover:text-white"
                            title="Cambiar área"
                        >
                            <el-icon :size="18"><OfficeBuilding /></el-icon>
                        </button>
                    </template>
                    <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-[#8B9AAB]">Área</p>
                    <button
                        v-for="w in workspaces"
                        :key="w.id"
                        type="button"
                        class="mb-0.5 flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm transition-colors"
                        :class="w.id === currentWorkspaceId
                            ? 'bg-[#579DFF]/15 text-[#85B8FF]'
                            : 'text-[#E8EEF4] hover:bg-white/5'"
                        @click="currentWorkspaceId = w.id; workspaceFlyoutOpen = false"
                    >
                        <el-icon :size="14"><OfficeBuilding /></el-icon>
                        <span class="truncate">{{ w.name }}</span>
                    </button>
                </el-popover>
            </div>

            <nav class="flex flex-1 flex-col gap-0.5 overflow-y-auto p-2">
                <el-tooltip
                    v-for="item in navItems"
                    :key="item.match"
                    :content="item.label"
                    placement="right"
                    :disabled="!sidebarCollapsed"
                    :show-after="200"
                >
                    <Link
                        :href="item.href"
                        class="nav-item flex items-center rounded-[10px] text-sm font-medium transition-colors"
                        :class="[
                            sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5',
                            isActiveNav(item)
                                ? 'bg-[#579DFF]/15 text-[#85B8FF]'
                                : 'text-[#8B9AAB] hover:bg-white/5 hover:text-[#E8EEF4]',
                        ]"
                    >
                        <el-icon :size="20" class="shrink-0"><component :is="item.icon" /></el-icon>
                        <span v-if="!sidebarCollapsed" class="truncate">{{ item.label }}</span>
                    </Link>
                </el-tooltip>

                <!-- Configuración + submenú -->
                <div class="mt-0.5">
                    <!-- Colapsado: flyout con opciones -->
                    <el-popover
                        v-if="sidebarCollapsed && hasConfigSubmenu"
                        v-model:visible="configFlyoutOpen"
                        placement="right-start"
                        :width="220"
                        trigger="click"
                        popper-class="sidebar-flyout"
                    >
                        <template #reference>
                            <button
                                type="button"
                                class="nav-item flex w-full items-center justify-center rounded-[10px] px-0 py-2.5 text-sm font-medium transition-colors"
                                :class="isConfig
                                    ? 'bg-[#579DFF]/15 text-[#85B8FF]'
                                    : 'text-[#8B9AAB] hover:bg-white/5 hover:text-[#E8EEF4]'"
                                :title="configMenuLabel"
                            >
                                <el-icon :size="20"><Setting /></el-icon>
                            </button>
                        </template>
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-[#8B9AAB]">
                            {{ configMenuLabel }}
                        </p>
                        <Link
                            v-for="item in configItems"
                            :key="item.href"
                            :href="item.href"
                            class="mb-0.5 flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-sm transition-colors"
                            :class="isActiveConfig(item.href)
                                ? 'bg-[#579DFF]/15 text-[#85B8FF]'
                                : 'text-[#E8EEF4] hover:bg-white/5'"
                            @click="configFlyoutOpen = false"
                        >
                            <el-icon :size="16" class="shrink-0"><component :is="item.icon" /></el-icon>
                            <span class="truncate">{{ item.label }}</span>
                        </Link>
                    </el-popover>

                    <!-- Colapsado sin submenú (solicitante → perfil) -->
                    <el-tooltip
                        v-else-if="sidebarCollapsed"
                        :content="configMenuLabel"
                        placement="right"
                        :show-after="200"
                    >
                        <Link
                            :href="configHref"
                            class="nav-item flex w-full items-center justify-center rounded-[10px] px-0 py-2.5 text-sm font-medium transition-colors"
                            :class="isConfig
                                ? 'bg-[#579DFF]/15 text-[#85B8FF]'
                                : 'text-[#8B9AAB] hover:bg-white/5 hover:text-[#E8EEF4]'"
                        >
                            <el-icon :size="20"><Setting /></el-icon>
                        </Link>
                    </el-tooltip>

                    <!-- Expandido: accordion -->
                    <template v-else>
                        <button
                            type="button"
                            class="nav-item flex w-full items-center gap-3 rounded-[10px] px-3 py-2.5 text-sm font-medium transition-colors"
                            :class="isConfig
                                ? 'bg-[#579DFF]/15 text-[#85B8FF]'
                                : 'text-[#8B9AAB] hover:bg-white/5 hover:text-[#E8EEF4]'"
                            @click="toggleConfigMenu"
                        >
                            <el-icon :size="20" class="shrink-0"><Setting /></el-icon>
                            <span class="flex-1 truncate text-left">{{ configMenuLabel }}</span>
                            <el-icon
                                v-if="hasConfigSubmenu"
                                :size="14"
                                class="shrink-0 opacity-70 transition-transform duration-200"
                                :class="configOpen ? 'rotate-180' : ''"
                            >
                                <ArrowDown />
                            </el-icon>
                        </button>
                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="opacity-0 -translate-y-1"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition duration-100 ease-in"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <div
                                v-if="hasConfigSubmenu && configOpen"
                                class="mt-0.5 space-y-0.5 border-l border-[#2a3340] ml-5 pl-1.5"
                            >
                                <Link
                                    v-for="item in configItems"
                                    :key="item.href"
                                    :href="item.href"
                                    class="flex items-center gap-2 rounded-md px-2.5 py-2 text-xs font-medium transition-colors"
                                    :class="isActiveConfig(item.href)
                                        ? 'bg-white/10 text-white'
                                        : 'text-[#8B9AAB] hover:bg-white/5 hover:text-[#E8EEF4]'"
                                >
                                    <el-icon :size="14" class="shrink-0"><component :is="item.icon" /></el-icon>
                                    <span class="truncate">{{ item.label }}</span>
                                </Link>
                            </div>
                        </Transition>
                    </template>
                </div>
            </nav>

            <div class="border-t border-[#2a3340] p-2">
                <el-tooltip
                    :content="sidebarCollapsed ? 'Expandir menú' : 'Colapsar menú'"
                    placement="right"
                    :disabled="!sidebarCollapsed"
                    :show-after="200"
                >
                    <button
                        type="button"
                        class="mb-2 flex w-full items-center rounded-[10px] text-sm text-[#8B9AAB] transition-colors hover:bg-white/5 hover:text-[#E8EEF4]"
                        :class="sidebarCollapsed ? 'justify-center px-0 py-2' : 'gap-3 px-3 py-2'"
                        @click="sidebarCollapsed = !sidebarCollapsed; configFlyoutOpen = false; workspaceFlyoutOpen = false"
                    >
                        <el-icon :size="18"><Fold v-if="!sidebarCollapsed" /><Expand v-else /></el-icon>
                        <span v-if="!sidebarCollapsed">Colapsar</span>
                    </button>
                </el-tooltip>

                <el-dropdown
                    trigger="click"
                    class="w-full"
                    :placement="sidebarCollapsed ? 'right-end' : 'top-start'"
                    @command="(c) => c === 'logout' ? logout() : go(c)"
                >
                    <button
                        type="button"
                        class="flex w-full items-center rounded-[10px] text-left hover:bg-white/5"
                        :class="sidebarCollapsed ? 'justify-center px-0 py-2' : 'gap-2.5 px-2 py-2'"
                    >
                        <el-avatar :size="32" :src="avatarUrl" class="shrink-0" />
                        <div v-if="!sidebarCollapsed" class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-[#E8EEF4]">{{ user?.name }}</p>
                            <p class="truncate text-[11px] text-[#8B9AAB]">{{ roleLabel }}</p>
                        </div>
                    </button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="/perfil">
                                <span class="inline-flex items-center gap-2">
                                    <el-icon><User /></el-icon>
                                    Perfil
                                </span>
                            </el-dropdown-item>
                            <el-dropdown-item divided command="logout">
                                <span class="inline-flex items-center gap-2">
                                    <el-icon><SwitchButton /></el-icon>
                                    Cerrar sesión
                                </span>
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </div>
        </aside>

        <!-- Área principal -->
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-3 border-b border-[#2a3340] bg-[#0f1419]/95 px-4 backdrop-blur">
                <div class="mx-auto flex w-full max-w-xl flex-1 items-center">
                    <el-input
                        id="global-search-input"
                        ref="searchInputRef"
                        v-model="globalSearch"
                        size="default"
                        clearable
                        placeholder="Buscar incidencias, sistemas o usuarios…"
                        class="w-full"
                        @keyup.enter="submitGlobalSearch"
                    >
                        <template #prefix>
                            <el-icon class="text-[#8B9AAB]"><Search /></el-icon>
                        </template>
                        <template #suffix>
                            <kbd class="hidden rounded border border-[#2a3340] bg-[#1a222c] px-1.5 py-0.5 text-[10px] text-[#8B9AAB] sm:inline">
                                Ctrl K
                            </kbd>
                        </template>
                    </el-input>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <el-button
                        v-if="can('create tickets')"
                        type="primary"
                        class="!hidden sm:!inline-flex"
                        @click="openCreateTicket"
                    >
                        <el-icon class="mr-1"><Plus /></el-icon>
                        {{ isSoporte ? 'Nueva' : 'Reportar' }}
                    </el-button>
                    <el-button
                        v-if="can('create tickets')"
                        circle
                        type="primary"
                        class="!inline-flex sm:!hidden"
                        @click="openCreateTicket"
                    >
                        <el-icon><Plus /></el-icon>
                    </el-button>

                    <div ref="notifRef" class="relative">
                        <el-badge :value="unreadCount" :hidden="unreadCount < 1" :max="99">
                            <el-button circle text class="!text-[#8B9AAB]" @click="notificationsOpen = !notificationsOpen">
                                <el-icon :size="18"><Bell /></el-icon>
                            </el-button>
                        </el-badge>
                        <div
                            v-show="notificationsOpen"
                            class="absolute right-0 mt-2 w-80 overflow-hidden rounded-[10px] border border-[#2a3340] bg-[#1a222c] shadow-lg"
                        >
                            <div class="flex items-center justify-between border-b border-[#2a3340] px-4 py-2">
                                <span class="text-sm font-medium">Notificaciones</span>
                                <button
                                    v-if="notifications.length"
                                    type="button"
                                    class="text-[11px] text-[#85B8FF] hover:underline"
                                    @click="markAllNotificationsRead"
                                >
                                    Marcar todas
                                </button>
                            </div>
                            <div class="max-h-72 overflow-y-auto">
                                <button
                                    v-for="n in notifications"
                                    :key="n.id"
                                    type="button"
                                    class="block w-full border-b border-[#2a3340]/80 px-4 py-3 text-left text-sm hover:bg-white/5"
                                    @click="viewNotification(n)"
                                >
                                    <p class="font-medium text-[#E8EEF4]">{{ notificationLabel(n) }}</p>
                                    <p v-if="n.data?.excerpt" class="mt-0.5 truncate text-xs text-[#8B9AAB]">{{ n.data.excerpt }}</p>
                                    <p v-else-if="n.data?.ticket_title" class="mt-0.5 truncate text-xs text-[#8B9AAB]">{{ n.data.ticket_title }}</p>
                                    <p class="text-xs text-[#8B9AAB]/80">{{ timeAgo(n.created_at) }}</p>
                                </button>
                                <p v-if="!notifications.length" class="px-4 py-8 text-center text-sm text-[#8B9AAB]">
                                    Sin notificaciones
                                </p>
                            </div>
                            <div class="border-t border-[#2a3340] px-3 py-2">
                                <Link
                                    href="/notificaciones"
                                    class="block rounded-md px-2 py-1.5 text-center text-xs text-[#85B8FF] hover:bg-white/5"
                                    @click="notificationsOpen = false"
                                >
                                    Ver todas
                                </Link>
                            </div>
                        </div>
                    </div>

                    <el-dropdown trigger="click" @command="(c) => c === 'logout' ? logout() : go(c)">
                        <button type="button" class="ml-1 flex items-center gap-2 rounded-[10px] px-2 py-1 hover:bg-white/5">
                            <el-avatar :size="28" :src="avatarUrl" />
                            <span class="hidden max-w-[120px] truncate text-sm text-[#E8EEF4] lg:inline">{{ user?.name }}</span>
                            <el-icon class="hidden text-[#8B9AAB] lg:inline"><ArrowDown /></el-icon>
                        </button>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item disabled>{{ user?.name }}</el-dropdown-item>
                                <el-dropdown-item command="/portal">Portal</el-dropdown-item>
                                <el-dropdown-item command="/perfil">Perfil</el-dropdown-item>
                                <el-dropdown-item divided command="logout">Cerrar sesión</el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </div>
            </header>

            <main
                :class="fullBleed
                    ? 'flex min-h-0 flex-1 flex-col overflow-hidden'
                    : 'flex-1 overflow-auto px-4 py-4 sm:px-6'"
            >
                <slot />
            </main>
        </div>

        <CreateTicketModal ref="createModalRef" />
    </div>
</template>
