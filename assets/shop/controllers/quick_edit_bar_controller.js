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
                headers: { Accept: 'text/html' },
                credentials: 'same-origin',
                // An expired admin session redirects to the login page, which must not count as success
                redirect: 'manual',
            });
            if (!response.ok) {
                return;
            }

            this.#render(await response.text());
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

    #render(html) {
        const sheet = new CSSStyleSheet();
        sheet.replaceSync(styles);

        // The markup is rendered by the admin endpoint, which escapes all values
        const template = document.createElement('template');
        template.innerHTML = html;
        const root = template.content.firstElementChild;

        const position = 'top' === root.dataset.position ? 'top' : 'bottom';
        let offset = OFFSET;
        if ('bottom' === position && document.querySelector(SYMFONY_TOOLBAR_SELECTOR)) {
            offset += SYMFONY_TOOLBAR_HEIGHT;
        }

        // Attached to <body> instead of the hook position, so transformed theme containers cannot break the fixed position
        this.#host = document.createElement('div');
        this.#host.setAttribute('data-test-quick-edit-bar', '');
        this.#host.setAttribute('data-position', position);
        this.#host.style.cssText = `all: initial; position: fixed; ${position}: ${offset}px; left: ${OFFSET}px; z-index: 2147483000; max-width: calc(100vw - ${2 * OFFSET}px);`;

        const shadowRoot = this.#host.attachShadow({ mode: 'open' });
        shadowRoot.adoptedStyleSheets = [sheet];
        shadowRoot.append(...root.children);

        const show = shadowRoot.querySelector('.toggle-show');
        const hide = shadowRoot.querySelector('.toggle-hide');
        show.addEventListener('click', () => this.#setCollapsed(false, hide));
        hide.addEventListener('click', () => {
            // An open dropdown would otherwise stay on top of the page
            shadowRoot.querySelectorAll('.menu:popover-open').forEach((menu) => menu.hidePopover());
            this.#setCollapsed(true, show);
        });
        shadowRoot.querySelectorAll('.menu').forEach((menu) => this.#positionMenu(menu, shadowRoot.querySelector(`[popovertarget="${menu.id}"]`)));

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

    /** Opens the dropdown of a group next to its button, the popover itself is opened by the button */
    #positionMenu(menu, button) {
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
    }

    #readCookie(name) {
        const prefix = `${name}=`;
        const cookie = document.cookie.split('; ').find((entry) => entry.startsWith(prefix));

        return cookie ? decodeURIComponent(cookie.substring(prefix.length)) : null;
    }
}
