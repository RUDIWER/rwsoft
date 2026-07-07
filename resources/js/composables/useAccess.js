import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useAccess() {
    const page = usePage();

    const acl = computed(
        () => page.props.acl ?? { is_super_admin: false, allowed_routes: [] },
    );

    const canAccess = (routeName) => {
        if (!routeName) {
            return false;
        }

        if (acl.value.is_super_admin) {
            return true;
        }

        return (
            Array.isArray(acl.value.allowed_routes) &&
            acl.value.allowed_routes.includes(routeName)
        );
    };

    return {
        acl,
        canAccess,
    };
}
