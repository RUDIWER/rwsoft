import { useAdminTranslations } from '@/composables/useAdminTranslations';

export function useSecurityTranslations() {
    const { t } = useAdminTranslations('admin_security_ui');

    function normalizeTranslationKey(value) {
        return String(value || '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    function roleLabel(role) {
        const key = normalizeTranslationKey(role?.key);

        return key
            ? t(`role_labels.${key}`, role?.name || role?.key || '')
            : role?.name || '';
    }

    function roleDescription(role) {
        const key = normalizeTranslationKey(role?.key);

        return key
            ? t(
                  `role_descriptions.${key}`,
                  role?.description || role?.name || role?.key || '',
              )
            : role?.description || '';
    }

    function permissionDescription(permission) {
        const key = normalizeTranslationKey(permission?.route_name);

        return key
            ? t(
                  `permission_descriptions.${key}`,
                  permission?.description || permission?.route_name || '',
              )
            : permission?.description || '';
    }

    function permissionModule(permission) {
        const module = permission?.module;
        const rawKey = typeof module === 'object' ? module?.key : module;
        const rawName = typeof module === 'object' ? module?.name : module;
        const key = normalizeTranslationKey(rawKey || rawName);

        return key
            ? t(`permission_modules.${key}`, rawName || rawKey || '')
            : rawName || '';
    }

    function permissionAction(permission) {
        const action = permission?.action;
        const rawKey = typeof action === 'object' ? action?.key : action;
        const rawName = typeof action === 'object' ? action?.name : action;
        const key = normalizeTranslationKey(rawKey || rawName);

        return key
            ? t(`permission_actions.${key}`, rawName || rawKey || '')
            : rawName || '';
    }

    function permissionType(permission) {
        const type = permission?.type;
        const rawKey = typeof type === 'object' ? type?.key : type;
        const rawName = typeof type === 'object' ? type?.name : type;
        const key = normalizeTranslationKey(rawKey || rawName);

        return key
            ? t(`permission_types.${key}`, rawName || rawKey || '')
            : rawName || '';
    }

    return {
        normalizeTranslationKey,
        permissionAction,
        permissionDescription,
        permissionModule,
        permissionType,
        roleDescription,
        roleLabel,
    };
}
