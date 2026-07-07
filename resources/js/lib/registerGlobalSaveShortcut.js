function isElementVisible(element) {
    if (!(element instanceof HTMLElement)) {
        return false;
    }

    if (element.matches('[disabled], [aria-disabled="true"]')) {
        return false;
    }

    const style = window.getComputedStyle(element);

    if (
        style.display === 'none' ||
        style.visibility === 'hidden' ||
        Number(style.opacity || 1) === 0
    ) {
        return false;
    }

    const rect = element.getBoundingClientRect();

    return rect.width > 0 && rect.height > 0;
}

function findShortcutTarget() {
    const selectors = [
        '[data-shortcut-intent="save"]',
        '[data-shortcut-intent="submit"]',
        'button[type="submit"]',
    ];

    for (const selector of selectors) {
        const candidates = Array.from(
            document.querySelectorAll(selector),
        ).filter((candidate) => {
            return isElementVisible(candidate);
        });

        if (candidates.length > 0) {
            return candidates[candidates.length - 1];
        }
    }

    return null;
}

function isSaveShortcut(event) {
    if (event.defaultPrevented || event.isComposing) {
        return false;
    }

    if (event.altKey || event.shiftKey) {
        return false;
    }

    const hasMeta = event.metaKey || event.ctrlKey;

    if (!hasMeta) {
        return false;
    }

    return String(event.key || '').toLowerCase() === 's';
}

function handleGlobalSaveShortcut(event) {
    if (!isSaveShortcut(event)) {
        return;
    }

    const target = findShortcutTarget();

    if (!target) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    target.click();
}

export function registerGlobalSaveShortcut() {
    if (typeof window === 'undefined') {
        return;
    }

    if (window.__rwGlobalSaveShortcutRegistered) {
        return;
    }

    window.addEventListener('keydown', handleGlobalSaveShortcut, true);
    window.__rwGlobalSaveShortcutRegistered = true;
}
