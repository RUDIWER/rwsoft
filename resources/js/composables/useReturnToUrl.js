export function resolveReturnToUrl(fallbackUrl) {
    if (typeof window === 'undefined') {
        return fallbackUrl;
    }

    const returnTo = new URL(window.location.href).searchParams.get('returnTo');

    if (!returnTo) {
        return fallbackUrl;
    }

    try {
        const target = new URL(returnTo, window.location.origin);

        if (target.origin !== window.location.origin) {
            return fallbackUrl;
        }

        return `${target.pathname}${target.search}${target.hash}`;
    } catch {
        return fallbackUrl;
    }
}
