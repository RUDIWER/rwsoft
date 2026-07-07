const initializedAttribute = 'data-cms-behavior-initialized';

const behaviorRegistry = {
    accordion: initializeAccordion,
    tabs: initializeTabs,
    modal: initializeModal,
    carousel: initializeCarousel,
    sticky: initializeSticky,
    'auto-hide-edge': initializeAutoHideEdge,
    menu: initializeMenu,
    'docs-toc': initializeDocsToc,
};

function parseOptions(element) {
    const rawOptions = element.getAttribute('data-cms-behavior-options');

    if (!rawOptions) {
        return {};
    }

    try {
        const options = JSON.parse(rawOptions);

        return options && typeof options === 'object' && !Array.isArray(options)
            ? options
            : {};
    } catch {
        return {};
    }
}

function initializeBehaviors(root = document) {
    root.querySelectorAll('[data-cms-behavior]').forEach((element) => {
        if (element.hasAttribute(initializedAttribute)) {
            return;
        }

        const behaviorKey = element.getAttribute('data-cms-behavior');
        const initialize = behaviorRegistry[behaviorKey];

        if (!initialize) {
            return;
        }

        initialize(element, parseOptions(element));
        element.setAttribute(initializedAttribute, 'true');
    });
}

function initializeAccordion(element) {
    element
        .querySelectorAll('[data-cms-accordion-trigger]')
        .forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const target = accordionTarget(element, trigger);

                if (!target) {
                    return;
                }

                const isOpen = target.hidden === true;

                target.hidden = !isOpen;
                trigger.setAttribute('aria-expanded', String(isOpen));
            });
        });
}

function accordionTarget(element, trigger) {
    const targetId = trigger.getAttribute('aria-controls');

    if (targetId) {
        return element.querySelector(`#${CSS.escape(targetId)}`);
    }

    return trigger.nextElementSibling;
}

function initializeTabs(element) {
    const tabs = Array.from(element.querySelectorAll('[role="tab"]'));

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => activateTab(element, tabs, tab));
    });
}

function activateTab(element, tabs, activeTab) {
    tabs.forEach((tab) => {
        const isActive = tab === activeTab;
        const panelId = tab.getAttribute('aria-controls');

        tab.setAttribute('aria-selected', String(isActive));
        tab.tabIndex = isActive ? 0 : -1;

        if (panelId) {
            const panel = element.querySelector(`#${CSS.escape(panelId)}`);

            if (panel) {
                panel.hidden = !isActive;
            }
        }
    });
}

function initializeModal(element) {
    element.querySelectorAll('[data-cms-modal-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const modal = modalTarget(element, trigger);

            if (modal) {
                modal.hidden = false;
            }
        });
    });

    element.querySelectorAll('[data-cms-modal-close]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const modal = trigger.closest('[data-cms-modal]');

            if (modal) {
                modal.hidden = true;
            }
        });
    });
}

function modalTarget(element, trigger) {
    const targetId = trigger.getAttribute('data-cms-modal-open');

    return targetId ? element.querySelector(`#${CSS.escape(targetId)}`) : null;
}

function initializeCarousel(element) {
    const slides = Array.from(
        element.querySelectorAll('[data-cms-carousel-slide]'),
    );
    let activeIndex = slides.findIndex((slide) => !slide.hidden);

    if (activeIndex < 0) {
        activeIndex = 0;
    }

    showCarouselSlide(slides, activeIndex);

    element.querySelectorAll('[data-cms-carousel-prev]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            activeIndex = (activeIndex - 1 + slides.length) % slides.length;
            showCarouselSlide(slides, activeIndex);
        });
    });

    element.querySelectorAll('[data-cms-carousel-next]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            activeIndex = (activeIndex + 1) % slides.length;
            showCarouselSlide(slides, activeIndex);
        });
    });
}

function showCarouselSlide(slides, activeIndex) {
    slides.forEach((slide, index) => {
        slide.hidden = index !== activeIndex;
    });
}

function initializeSticky(element, options) {
    element.classList.add('rw-public-behavior--sticky');

    if (typeof options.top === 'number') {
        element.style.setProperty('--rw-public-sticky-top', `${options.top}px`);
    }
}

function initializeAutoHideEdge(element, options) {
    const edge = options.edge === 'footer' ? 'footer' : 'header';
    const scrollTarget = scrollTargetForEdge(element);
    const updateOffset = () => updateAutoHideOffset(element, edge);
    let lastScrollTop = currentScrollTop(scrollTarget);

    updateOffset();
    window.addEventListener('resize', updateOffset, { passive: true });

    scrollTarget.addEventListener(
        'scroll',
        () => {
            const scrollTop = currentScrollTop(scrollTarget);
            const scrollingDown = scrollTop > lastScrollTop;
            const shouldHide =
                edge === 'footer' ? !scrollingDown : scrollingDown;

            element.classList.toggle(
                'rw-public-section--auto-hidden',
                scrollTop > 8 && shouldHide,
            );
            lastScrollTop = scrollTop;
        },
        { passive: true },
    );
}

function updateAutoHideOffset(element, edge) {
    const parent = element.parentElement;

    if (!parent) {
        element.style.removeProperty('--rw-public-auto-hide-offset');

        return;
    }

    const siblings = Array.from(parent.children).filter(
        (candidate) => candidate instanceof HTMLElement,
    );
    const elementIndex = siblings.indexOf(element);

    if (elementIndex < 0) {
        element.style.removeProperty('--rw-public-auto-hide-offset');

        return;
    }

    const stickySiblings = siblings.filter((candidate, candidateIndex) => {
        const isRelevantSibling =
            edge === 'footer'
                ? candidateIndex > elementIndex
                : candidateIndex < elementIndex;

        return (
            isRelevantSibling &&
            candidate.classList.contains('rw-public-section--scroll-sticky')
        );
    });
    const offset = stickySiblings.reduce(
        (height, candidate) =>
            height + candidate.getBoundingClientRect().height,
        0,
    );

    if (offset > 0) {
        element.style.setProperty(
            '--rw-public-auto-hide-offset',
            `${Math.round(offset)}px`,
        );
    } else {
        element.style.removeProperty('--rw-public-auto-hide-offset');
    }
}

function scrollTargetForEdge(element) {
    const shell = element.closest('.rw-public-shell');

    if (shell?.classList.contains('rw-public-shell--fixed-edges')) {
        return shell.querySelector('.rw-public-main') || window;
    }

    return window;
}

function currentScrollTop(scrollTarget) {
    return scrollTarget === window
        ? window.scrollY || document.documentElement.scrollTop || 0
        : scrollTarget.scrollTop;
}

function initializeDocsToc(element) {
    const section = element.closest('.rw-public-section');

    if (!section) {
        return;
    }

    const storageKey = 'rwsoft.docs.tocCollapsed';
    const toggle = element.querySelector('[data-rw-docs-toc-toggle]');
    const restore = section.querySelector('[data-rw-docs-toc-restore]');
    const setCollapsed = (isCollapsed) => {
        section.classList.toggle('rw-docs-toc-collapsed', isCollapsed);
        toggle?.setAttribute('aria-expanded', String(!isCollapsed));
        restore?.setAttribute('aria-expanded', String(!isCollapsed));

        try {
            window.localStorage.setItem(storageKey, isCollapsed ? '1' : '0');
        } catch {
            // Ignore storage failures in private browsing or locked-down browsers.
        }
    };

    try {
        section.classList.toggle(
            'rw-docs-toc-collapsed',
            window.localStorage.getItem(storageKey) === '1',
        );
    } catch {
        section.classList.remove('rw-docs-toc-collapsed');
    }

    const isCollapsed = section.classList.contains('rw-docs-toc-collapsed');
    toggle?.setAttribute('aria-expanded', String(!isCollapsed));
    restore?.setAttribute('aria-expanded', String(!isCollapsed));

    toggle?.addEventListener('click', () => setCollapsed(true));
    restore?.addEventListener('click', () => setCollapsed(false));

    initializeDocsDrawers(section);
}

function initializeDocsDrawers(section) {
    const updateOffset = () => scheduleDocsDrawerOffsetUpdate(section);

    updateOffset();
    window.addEventListener('resize', updateOffset, { passive: true });
    window.addEventListener('scroll', updateOffset, { passive: true });

    section.querySelectorAll('.rw-docs-drawer').forEach((drawer) => {
        const summary = drawer.querySelector('summary');
        const closeDrawer = (restoreFocus = false) => {
            drawer.removeAttribute('open');

            if (restoreFocus && summary instanceof HTMLElement) {
                summary.focus();
            }
        };

        drawer
            .querySelectorAll('[data-rw-docs-drawer-close]')
            .forEach((button) => {
                button.addEventListener('click', () => closeDrawer(true));
            });

        drawer.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', () => closeDrawer(false));
        });

        drawer.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeDrawer(true);
            }
        });
    });
}

function scheduleDocsDrawerOffsetUpdate(section) {
    updateDocsDrawerOffset(section);

    window.requestAnimationFrame(() => updateDocsDrawerOffset(section));
    window.setTimeout(() => updateDocsDrawerOffset(section), 220);
}

function updateDocsDrawerOffset(section) {
    const shell = section.closest('.rw-public-shell');
    const header = shell?.querySelector('.rw-public-header-stack--sticky');

    if (!(header instanceof HTMLElement)) {
        section.style.removeProperty('--rw-docs-drawer-offset-top');

        return;
    }

    const visibleBottom = Array.from(
        header.querySelectorAll('.rw-public-section'),
    ).reduce((bottom, headerSection) => {
        const rect = headerSection.getBoundingClientRect();

        if (rect.bottom <= 0 || rect.top >= window.innerHeight) {
            return bottom;
        }

        return Math.max(bottom, rect.bottom);
    }, 0);
    const fallbackBottom = header.getBoundingClientRect().bottom;
    const offset = Math.max(
        0,
        Math.min(
            window.innerHeight,
            Math.round(visibleBottom || fallbackBottom),
        ),
    );

    section.style.setProperty('--rw-docs-drawer-offset-top', `${offset}px`);
}

function initializeMenu(element) {
    const menu = element.querySelector('[data-rw-public-menu]');
    const toggle = element.querySelector('[data-rw-public-menu-toggle]');
    const panel = element.querySelector('[data-rw-public-menu-panel]');
    const backdrop = element.querySelector('[data-rw-public-menu-backdrop]');
    const menuSection = menu?.closest('.rw-public-section');

    if (!menu) {
        return;
    }

    const closeButtons = element.querySelectorAll(
        '[data-rw-public-menu-close]',
    );
    const focusableSelector =
        'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';
    const updateDrawerOffset = () => updateMenuDrawerOffset(menu);
    const setOpen = (isOpen, restoreFocus = false) => {
        menu.classList.toggle('is-open', isOpen);
        menuSection?.classList.toggle('rw-public-section--menu-open', isOpen);
        updateDrawerOffset();
        toggle?.setAttribute('aria-expanded', String(isOpen));

        if (panel) {
            panel.hidden = !isOpen;
        }

        if (backdrop) {
            backdrop.hidden = !isOpen;
        }

        if (isOpen) {
            window.requestAnimationFrame(() => {
                panel?.querySelector(focusableSelector)?.focus();
            });
        } else if (restoreFocus) {
            toggle?.focus();
        }
    };

    updateDrawerOffset();
    window.addEventListener('resize', updateDrawerOffset, { passive: true });

    toggle?.addEventListener('click', () => {
        setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });

    backdrop?.addEventListener('click', () => setOpen(false, true));

    closeButtons.forEach((button) => {
        button.addEventListener('click', () => setOpen(false, true));
    });

    menu.querySelectorAll('[data-rw-public-submenu-toggle]').forEach(
        (trigger) => {
            trigger.addEventListener('click', (event) => {
                if (!shouldToggleSubmenuFromClick(trigger, event)) {
                    return;
                }

                const item = trigger.closest('.rw-public-nav__item');
                const isOpen = trigger.getAttribute('aria-expanded') !== 'true';

                if (!isOpen && trigger instanceof HTMLAnchorElement) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                trigger.setAttribute('aria-expanded', String(isOpen));
                item?.classList.toggle('is-submenu-open', isOpen);
            });
        },
    );

    panel?.addEventListener('click', (event) => {
        if (
            event.target instanceof Element &&
            event.target.closest('a[href]') &&
            !event.target.closest('[data-rw-public-submenu-toggle]')
        ) {
            setOpen(false);
        }
    });

    document.addEventListener('click', (event) => {
        if (
            toggle?.getAttribute('aria-expanded') !== 'true' ||
            !(event.target instanceof Node) ||
            menu.contains(event.target)
        ) {
            return;
        }

        setOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (
            event.key === 'Escape' &&
            toggle?.getAttribute('aria-expanded') === 'true'
        ) {
            setOpen(false, true);
        }
    });
}

function updateMenuDrawerOffset(menu) {
    if (
        !menu.classList.contains(
            'rw-public-menu--drawer-top-below-sticky-header',
        )
    ) {
        menu.style.removeProperty('--rw-public-menu-drawer-offset-top');

        return;
    }

    const shell = menu.closest('.rw-public-shell');
    const header = shell?.querySelector('.rw-public-header-stack--sticky');

    if (!(header instanceof HTMLElement)) {
        menu.style.removeProperty('--rw-public-menu-drawer-offset-top');

        return;
    }

    const offset = menuDrawerOffset(menu, header);

    menu.style.setProperty('--rw-public-menu-drawer-offset-top', `${offset}px`);
}

function menuDrawerOffset(menu, header) {
    return Math.max(0, Math.round(header.getBoundingClientRect().bottom));
}

function shouldToggleSubmenuFromClick(trigger, event) {
    if (!(trigger instanceof HTMLAnchorElement)) {
        return true;
    }

    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return false;
    }

    if (trigger.target && trigger.target !== '_self') {
        return false;
    }

    return (
        Boolean(trigger.closest('[data-rw-public-menu-panel]')) ||
        window.matchMedia('(max-width: 1023.98px)').matches
    );
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initializeBehaviors());
} else {
    initializeBehaviors();
}

window.RwCmsBehaviors = {
    initialize: initializeBehaviors,
};
