export const RWTABLE_RETURN_MARKER_KEY = 'rwtable:return-marker';
export const RWTABLE_RETURN_MARKER_TTL_MS = 5000;

function resolveOrigin(origin) {
    if (typeof origin === 'string' && origin.trim() !== '') {
        return origin;
    }

    if (
        typeof window !== 'undefined' &&
        typeof window.location?.origin === 'string'
    ) {
        return window.location.origin;
    }

    return 'http://localhost';
}

export function normalizeRwTableRestorePath(raw, origin) {
    if (!raw || typeof raw !== 'string') {
        return null;
    }

    try {
        const url = new URL(raw, resolveOrigin(origin));
        return url.pathname || '/';
    } catch {
        if (raw.startsWith('/')) {
            return raw.split('?')[0].split('#')[0] || '/';
        }

        return `/${raw}`.split('?')[0].split('#')[0] || '/';
    }
}

export function buildRwTableRestoreStateKey(path, tableId) {
    if (!path || !tableId) {
        return null;
    }

    return `rwtable:return-state:${encodeURIComponent(String(path))}:${encodeURIComponent(String(tableId))}`;
}

export function createRwTableReturnMarker(
    path,
    token = null,
    createdAt = Date.now(),
) {
    const normalizedPath = normalizeRwTableRestorePath(path);
    if (!normalizedPath) {
        return null;
    }

    return {
        path: normalizedPath,
        token:
            token || `${createdAt}-${Math.random().toString(36).slice(2, 10)}`,
        createdAt,
    };
}

export function parseRwTableReturnMarker(raw) {
    if (!raw) {
        return null;
    }

    try {
        const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
        if (!parsed || typeof parsed !== 'object') {
            return null;
        }

        const normalizedPath = normalizeRwTableRestorePath(parsed.path);
        const token = typeof parsed.token === 'string' ? parsed.token : '';
        const createdAt = Number(parsed.createdAt ?? 0);

        if (
            !normalizedPath ||
            token === '' ||
            !Number.isFinite(createdAt) ||
            createdAt <= 0
        ) {
            return null;
        }

        return {
            path: normalizedPath,
            token,
            createdAt,
        };
    } catch {
        return null;
    }
}

export function shouldApplyRwTableReturnMarker(
    marker,
    path,
    now = Date.now(),
    ttlMs = RWTABLE_RETURN_MARKER_TTL_MS,
) {
    const normalizedPath = normalizeRwTableRestorePath(path);
    if (!marker || !normalizedPath) {
        return false;
    }

    if (marker.path !== normalizedPath) {
        return false;
    }

    const createdAt = Number(marker.createdAt ?? 0);
    if (!Number.isFinite(createdAt) || createdAt <= 0) {
        return false;
    }

    return now - createdAt <= ttlMs;
}

export function markRwTableReturnTarget(targetPath, storage = null) {
    const normalizedPath = normalizeRwTableRestorePath(targetPath);
    if (!normalizedPath) {
        return null;
    }

    const marker = createRwTableReturnMarker(normalizedPath);
    const sessionStorageRef =
        storage ||
        (typeof window !== 'undefined' ? window.sessionStorage : null);
    if (marker && sessionStorageRef) {
        sessionStorageRef.setItem(
            RWTABLE_RETURN_MARKER_KEY,
            JSON.stringify(marker),
        );
    }

    return marker;
}

export function readRwTableReturnMarker(storage = null) {
    const sessionStorageRef =
        storage ||
        (typeof window !== 'undefined' ? window.sessionStorage : null);
    if (!sessionStorageRef) {
        return null;
    }

    return parseRwTableReturnMarker(
        sessionStorageRef.getItem(RWTABLE_RETURN_MARKER_KEY),
    );
}

export function clearRwTableReturnMarker(token = null, storage = null) {
    const sessionStorageRef =
        storage ||
        (typeof window !== 'undefined' ? window.sessionStorage : null);
    if (!sessionStorageRef) {
        return;
    }

    if (!token) {
        sessionStorageRef.removeItem(RWTABLE_RETURN_MARKER_KEY);
        return;
    }

    const marker = readRwTableReturnMarker(sessionStorageRef);
    if (marker?.token === token) {
        sessionStorageRef.removeItem(RWTABLE_RETURN_MARKER_KEY);
    }
}
