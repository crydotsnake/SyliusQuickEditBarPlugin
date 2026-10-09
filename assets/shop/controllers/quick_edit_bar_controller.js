import { Controller } from '@hotwired/stimulus';
import { styles } from './quick_edit_bar_styles.js';

const RESOURCE_SELECTOR = '[data-s-krull-quick-edit-bar-resource]';

// Remembers per browser whether the bar was collapsed, so it stays out of the way on the next pages
const COLLAPSED_STORAGE_KEY = 's_krull_sylius_quick_edit_bar.collapsed';

// Distance of the floating bar to the edge of the viewport
const OFFSET = 12;

// The Symfony web debug toolbar (dev only) is fixed to the bottom with this height
const SYMFONY_TOOLBAR_SELECTOR = '.sf-toolbar';
const SYMFONY_TOOLBAR_HEIGHT = 36;

// Tabler icons (MIT), https://tabler.io/icons
const ICONS = {
    administration: ['M5 4h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1', 'M5 16h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1', 'M15 12h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1', 'M15 4h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1'],
    resource: ['M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5', 'M12 12l8 -4.5', 'M12 12l0 9', 'M12 12l-8 -4.5'],
    taxon: ['M4 4h6v6h-6z', 'M14 4h6v6h-6z', 'M4 14h6v6h-6z', 'M14 17a3 3 0 1 0 6 0a3 3 0 1 0 -6 0'],
    edit: ['M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4', 'M13.5 6.5l4 4'],
    show: ['M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0', 'M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6'],
    variants: ['M9 6l11 0', 'M9 12l11 0', 'M9 18l11 0', 'M5 6l0 .01', 'M5 12l0 .01', 'M5 18l0 .01'],
    index: ['M9 6l11 0', 'M9 12l11 0', 'M9 18l11 0', 'M5 6l0 .01', 'M5 12l0 .01', 'M5 18l0 .01'],
    products: ['M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5', 'M12 12l8 -4.5', 'M12 12l0 9', 'M12 12l-8 -4.5'],
    link: ['M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6', 'M11 13l9 -9', 'M15 4h5v5'],
    chevron: ['M6 9l6 6l6 -6'],
    hide: ['M18 6l-12 12', 'M6 6l12 12'],
    channel: ['M3 21l18 0', 'M3 7v1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1h-18l2 -4h14l2 4', 'M5 21l0 -10.35', 'M19 21l0 -10.35', 'M9 21v-4a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v4'],
    impersonation: ['M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0', 'M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2'],
};

const supportsPopover = () => Object.hasOwn(HTMLElement.prototype, 'popover');

/**
 * Shows a floating bar with links into the Sylius admin on every shop page for logged-in administrators.
 *
 * The bar floats above the page instead of pushing it, at the bottom by default, and can be collapsed to a small button.
 *
 * The hint cookie is only set for administrators and contains the path of the bar endpoint,
 * so regular visitors never trigger a request. The endpoint itself is protected by the admin firewall.
 */
export default class extends Controller {
    static values = {
        hintCookie: String,
        channel: String,
    };

    #host = null;

    #abortController = null;

    async connect() {
        const endpoint = this.#readCookie(this.hintCookieValue);
        if (!endpoint) {
            return;
        }

        const url = new URL(endpoint, window.location.origin);
        if (this.channelValue) {
            url.searchParams.set('channel', this.channelValue);
        }
        const resource = document.querySelector(RESOURCE_SELECTOR);
        if (resource) {
            url.searchParams.set('resource', resource.dataset.sKrullQuickEditBarResource);
            url.searchParams.set('id', resource.dataset.sKrullQuickEditBarResourceId);
        }

        this.#abortController = new AbortController();

        try {
            const response = await fetch(url, {
                signal: this.#abortController.signal,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                // An expired admin session redirects to the login page, which must not count as success
                redirect: 'manual',
            });
            if (!response.ok) {
                return;
            }

            this.#render(await response.json());
        } catch {
            // The bar is a convenience only, never break the shop page
        }
    }

    disconnect() {
        this.#abortController?.abort();

        if (!this.#host) {
            return;
        }

        this.#host.remove();
        this.#host = null;
    }

    #render(data) {
        const sheet = new CSSStyleSheet();
        sheet.replaceSync(styles);

        const position = 'top' === data.position ? 'top' : 'bottom';
        let offset = OFFSET;
        if ('bottom' === position && document.querySelector(SYMFONY_TOOLBAR_SELECTOR)) {
            offset += SYMFONY_TOOLBAR_HEIGHT;
        }

        // Attached to <body> instead of the hook position, so transformed theme containers cannot break the fixed position
        this.#host = document.createElement('div');
        this.#host.setAttribute('data-test-quick-edit-bar', '');
        this.#host.setAttribute('data-position', position);
        this.#host.style.cssText = `all: initial; position: fixed; ${position}: ${offset}px; left: ${OFFSET}px; z-index: 2147483000; max-width: calc(100vw - ${2 * OFFSET}px);`;

        const bar = this.#buildBar(data);
        const show = this.#buildToggle(data.toggle.show, 'administration', 'show');
        const hide = this.#buildToggle(data.toggle.hide, 'hide', 'hide');
        bar.append(hide);

        show.addEventListener('click', () => this.#setCollapsed(false, hide));
        hide.addEventListener('click', () => {
            // An open dropdown would otherwise stay on top of the page
            bar.querySelectorAll('.menu').forEach((menu) => menu.matches(':popover-open') && menu.hidePopover());
            this.#setCollapsed(true, show);
        });

        const shadowRoot = this.#host.attachShadow({ mode: 'open' });
        shadowRoot.adoptedStyleSheets = [sheet];
        shadowRoot.append(bar, show);
        document.body.prepend(this.#host);

        this.#setCollapsed(this.#isStoredCollapsed());
    }

    #setCollapsed(collapsed, focusTarget = null) {
        this.#host.toggleAttribute('data-collapsed', collapsed);

        try {
            if (collapsed) {
                window.localStorage.setItem(COLLAPSED_STORAGE_KEY, '1');
            } else {
                window.localStorage.removeItem(COLLAPSED_STORAGE_KEY);
            }
        } catch {
            // Storage can be unavailable, e.g. in private windows, the bar then just starts expanded again
        }

        // Keep the keyboard focus on the button that replaces the clicked one
        focusTarget?.focus();
    }

    #isStoredCollapsed() {
        try {
            return '1' === window.localStorage.getItem(COLLAPSED_STORAGE_KEY);
        } catch {
            return false;
        }
    }

    #buildToggle(label, icon, action) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `toggle toggle-${action}`;
        button.title = label;
        button.setAttribute('aria-label', label);
        button.setAttribute('aria-expanded', String('hide' === action));
        button.setAttribute('data-test-quick-edit-bar-toggle', action);
        button.append(this.#buildIcon(icon));

        return button;
    }

    #buildBar({ label, administration, channel, impersonation, resource }) {
        const bar = document.createElement('nav');
        bar.className = 'bar';
        bar.setAttribute('aria-label', label);

        bar.append(this.#buildLink(administration, 'administration', 'administration'));

        if (channel) {
            // Only the name is shown, the type stays in the tooltip and the accessible name
            const link = this.#buildLink({ label: channel.name, url: channel.url }, 'channel', 'channel');
            link.title = `${channel.label}: ${channel.name}`;
            link.setAttribute('aria-label', link.title);
            bar.append(link);
        }

        if (impersonation) {
            bar.append(this.#buildLink(impersonation, 'impersonation', 'impersonation'));
        }

        if (resource) {
            const separator = document.createElement('span');
            separator.className = 'separator';

            const description = document.createElement('span');
            description.className = 'resource';
            const name = document.createElement('span');
            name.className = 'resource-name';
            name.textContent = resource.name;
            name.title = resource.name;
            const type = document.createElement('span');
            type.className = 'resource-type';
            type.textContent = `${resource.type}:`;
            description.append(this.#buildIcon(ICONS[resource.key] ? resource.key : 'resource'), type, name);

            bar.append(separator, description);
            resource.links.forEach((item) => bar.append('group' === item.type ? this.#buildGroup(item) : this.#buildLink(item, item.action, `action-${item.action}`)));
        }

        return bar;
    }

    #buildLink({ label, url }, icon, className) {
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.className = className;
        link.title = label;
        link.setAttribute('aria-label', label);
        link.setAttribute('data-test-quick-edit-bar-link', className);

        // The label is hidden on narrow screens, the icon and the accessible name stay
        const text = document.createElement('span');
        text.className = 'label';
        text.textContent = label;
        link.append(this.#buildIcon(icon), text);

        return link;
    }

    /** Rendered as a popover, so the dropdown is not clipped by the horizontally scrollable bar */
    #buildGroup({ action, label, links }) {
        if (!supportsPopover()) {
            // Older browsers get the last link of the group, e.g. the list of all variants
            return this.#buildLink({ label, url: links[links.length - 1].url }, action, `action-${action}`);
        }

        const menu = document.createElement('div');
        menu.className = 'menu';
        menu.popover = 'auto';
        menu.setAttribute('data-test-quick-edit-bar-menu', action);
        links.forEach((link) => {
            if ('index' === link.action) {
                const separator = document.createElement('div');
                separator.className = 'menu-separator';
                menu.append(separator);
            }

            menu.append(this.#buildMenuLink(link));
        });

        const button = document.createElement('button');
        button.type = 'button';
        button.className = `group action-${action}`;
        button.title = label;
        button.popoverTargetElement = menu;
        button.setAttribute('aria-label', label);
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('data-test-quick-edit-bar-link', `action-${action}`);

        const text = document.createElement('span');
        text.className = 'label';
        text.textContent = label;
        const chevron = this.#buildIcon('chevron');
        chevron.classList.add('chevron');
        button.append(this.#buildIcon(action), text, chevron);

        menu.addEventListener('beforetoggle', (event) => {
            if ('open' === event.newState) {
                const { top, bottom, left } = button.getBoundingClientRect();
                menu.style.left = `${left}px`;

                // Open towards the middle of the viewport, a bar at the bottom opens its menus upwards
                if ('bottom' === this.#host.dataset.position) {
                    menu.style.top = '';
                    menu.style.bottom = `${window.innerHeight - top + 4}px`;
                } else {
                    menu.style.top = `${bottom + 4}px`;
                    menu.style.bottom = '';
                }
            }
        });
        menu.addEventListener('toggle', (event) => {
            const isOpen = 'open' === event.newState;
            button.setAttribute('aria-expanded', String(isOpen));

            // Keep the menu inside the viewport once its width is known
            if (isOpen) {
                const overflow = menu.getBoundingClientRect().right - (window.innerWidth - 8);
                if (overflow > 0) {
                    menu.style.left = `${Math.max(8, parseFloat(menu.style.left) - overflow)}px`;
                }
            }
        });

        const group = document.createElement('span');
        group.className = 'group-wrapper';
        group.append(button, menu);

        return group;
    }

    #buildMenuLink({ action, label, url }) {
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.className = 'menu-link';
        link.title = label;

        const text = document.createElement('span');
        text.className = 'menu-label';
        text.textContent = label;
        link.append(this.#buildIcon(action), text);

        return link;
    }

    #buildIcon(name) {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');

        (ICONS[name] ?? ICONS.link).forEach((path) => {
            const element = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            element.setAttribute('d', path);
            svg.append(element);
        });

        return svg;
    }

    #readCookie(name) {
        const prefix = `${name}=`;
        const cookie = document.cookie.split('; ').find((entry) => entry.startsWith(prefix));

        return cookie ? decodeURIComponent(cookie.substring(prefix.length)) : null;
    }
}
