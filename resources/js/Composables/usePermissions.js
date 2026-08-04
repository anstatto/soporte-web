import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermissions() {
    const page = usePage();

    const user = computed(() => page.props.auth?.user ?? null);
    const permissions = computed(() => user.value?.permissions ?? []);
    const roles = computed(() => user.value?.roles ?? []);

    const can = (permission) => permissions.value.includes(permission);
    const hasRole = (role) => {
        if (Array.isArray(role)) {
            return role.some((r) => roles.value.includes(r));
        }
        return roles.value.includes(role);
    };
    const isSoporte = computed(() => !!user.value?.is_soporte);
    const isAdmin = computed(() => !!user.value?.is_admin);
    const isSolicitante = computed(() => !!user.value?.is_solicitante);

    return { user, permissions, roles, can, hasRole, isSoporte, isAdmin, isSolicitante };
}
