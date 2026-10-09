import { Controller } from '@hotwired/stimulus';
import { styles } from './quick_edit_bar_styles.js';

const RESOURCE_SELECTOR = '[data-s-krull-quick-edit-bar-resource]';

// Remembers per browser whether the bar was collapsed, so it stays out of the way on the next pages
const COLLAPSED_STORAGE_KEY = 's_krull_sylius_quick_edit_bar.collapsed';

// Remembers per browser the corner the bar was moved to, e.g. away from a chat button of the shop
const CORNER_STORAGE_KEY = 's_krull_sylius_quick_edit_bar.corner';

// Clockwise, a click on the handle moves the bar to the next one
const CORNERS = ['top-left', 'top-right', 'bottom-right', 'bottom-left'];

// Distance the pointer has to move before a press on a handle counts as dragging instead of a click
const DRAG_THRESHOLD = 4;

// Distance of the floating bar to the edge of the viewport
const OFFSET = 12;

// The Symfony web debug toolbar (dev only) is fixed to the bottom with this height
const SYMFONY_TOOLBAR_SELECTOR = '.sf-toolbar';
const SYMFONY_TOOLBAR_HEIGHT = 36;

/**
 * Shows a floating bar with links into the Sylius admin on every shop page for logged-in administrators.
 *
 * The bar floats above the page instead of pushing it, at the bottom by default, and can be collapsed to a small button.
 * Its handle and the collapsed button can be dragged to any corner of the viewport, so the bar can make way for chat buttons, cookie banners and the like.
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

    #corner = null;

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

        // The configured edge applies until the bar is moved
        const defaultCorner = 'top' === root.dataset.position ? 'top-left' : 'bottom-left';

        // Attached to <body> instead of the hook position, so transformed theme containers cannot break the fixed position
        this.#host = document.createElement('div');
        this.#host.setAttribute('data-test-quick-edit-bar', '');
        this.#host.style.cssText = `all: initial; position: fixed; z-index: 2147483000; max-width: calc(100vw - ${2 * OFFSET}px);`;

        const shadowRoot = this.#host.attachShadow({ mode: 'open' });
        shadowRoot.adoptedStyleSheets = [sheet];
        shadowRoot.append(...root.children);

        const show = shadowRoot.querySelector('.toggle-show');
        const hide = shadowRoot.querySelector('.toggle-hide');
        const handle = shadowRoot.querySelector('.handle');

        // Registered before the click listeners, so a drag can swallow the click that follows it
        this.#makeDraggable(handle);
        this.#makeDraggable(show);
        handle.addEventListener('click', () => this.#moveTo(CORNERS[(CORNERS.indexOf(this.#corner) + 1) % CORNERS.length]));

        show.addEventListener('click', () => this.#setCollapsed(false, hide));
        hide.addEventListener('click', () => {
            // An open dropdown would otherwise stay on top of the page
            this.#closeMenus();
            this.#setCollapsed(true, show);
        });
        shadowRoot.querySelectorAll('.menu').forEach((menu) => this.#positionMenu(menu, shadowRoot.querySelector(`[popovertarget="${menu.id}"]`)));

        const storedCorner = readStorage(CORNER_STORAGE_KEY);
        this.#place(CORNERS.includes(storedCorner) ? storedCorner : defaultCorner);

        document.body.prepend(this.#host);

        this.#setCollapsed('1' === readStorage(COLLAPSED_STORAGE_KEY));
    }

    /**
     * Lets the element be dragged around with the mouse, a pen or a finger, it snaps to the nearest corner when released.
     */
    #makeDraggable(element) {
        let start = null;
        let dragging = false;

        element.addEventListener('pointerdown', (event) => {
            if (!event.isPrimary || 0 !== event.button) {
                return;
            }

            // Otherwise the browser starts a native drag of an image or a text selection beneath, which cancels the pointer
            event.preventDefault();

            start = { x: event.clientX, y: event.clientY };
            dragging = false;
            element.setPointerCapture(event.pointerId);
        });

        element.addEventListener('pointermove', (event) => {
            if (!start) {
                return;
            }

            const x = event.clientX - start.x;
            const y = event.clientY - start.y;
            if (!dragging) {
                if (Math.hypot(x, y) < DRAG_THRESHOLD) {
                    return;
                }

                dragging = true;
                this.#host.toggleAttribute('data-dragging', true);
                this.#closeMenus();
            }

            this.#host.style.transform = `translate(${x}px, ${y}px)`;
        });

        const release = (event) => {
            if (!start) {
                return;
            }

            start = null;
            if (!dragging) {
                return;
            }

            // The corner is chosen while the bar is still where it was dropped
            const { top, bottom, left, right } = this.#host.getBoundingClientRect();
            const vertical = (top + bottom) / 2 < window.innerHeight / 2 ? 'top' : 'bottom';
            const horizontal = (left + right) / 2 < window.innerWidth / 2 ? 'left' : 'right';

            this.#host.style.transform = '';
            this.#host.removeAttribute('data-dragging');

            if ('pointerup' === event.type) {
                this.#moveTo(`${vertical}-${horizontal}`);
            }
        };
        element.addEventListener('pointerup', release);
        element.addEventListener('pointercancel', release);

        // A drag ends with a click on the element, which must neither move the bar again nor expand it
        element.addEventListener('click', (event) => {
            if (dragging) {
                dragging = false;
                event.stopImmediatePropagation();
            }
        });
    }

    #moveTo(corner) {
        this.#place(corner);
        writeStorage(CORNER_STORAGE_KEY, corner);
    }

    #place(corner) {
        const [vertical, horizontal] = corner.split('-');

        let offset = OFFSET;
        if ('bottom' === vertical && document.querySelector(SYMFONY_TOOLBAR_SELECTOR)) {
            offset += SYMFONY_TOOLBAR_HEIGHT;
        }

        const { style } = this.#host;
        style.top = 'top' === vertical ? `${offset}px` : 'auto';
        style.bottom = 'bottom' === vertical ? `${offset}px` : 'auto';
        style.left = 'left' === horizontal ? `${OFFSET}px` : 'auto';
        style.right = 'right' === horizontal ? `${OFFSET}px` : 'auto';

        this.#corner = corner;
    }

    #setCollapsed(collapsed, focusTarget = null) {
        this.#host.toggleAttribute('data-collapsed', collapsed);
        writeStorage(COLLAPSED_STORAGE_KEY, collapsed ? '1' : null);

        // Keep the keyboard focus on the button that replaces the clicked one
        focusTarget?.focus();
    }

    #closeMenus() {
        this.#host.shadowRoot.querySelectorAll('.menu:popover-open').forEach((menu) => menu.hidePopover());
    }

    /** Opens the dropdown of a group next to its button, the popover itself is opened by the button */
    #positionMenu(menu, button) {
        menu.addEventListener('beforetoggle', (event) => {
            if ('open' === event.newState) {
                const { top, bottom, left } = button.getBoundingClientRect();
                menu.style.left = `${left}px`;

                // Open towards the middle of the viewport, a bar at the bottom opens its menus upwards
                if (this.#corner.startsWith('bottom')) {
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

// Storage can be unavailable, e.g. in private windows, the bar then just starts expanded at the configured edge again
function readStorage(key) {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function writeStorage(key, value) {
    try {
        if (null === value) {
            window.localStorage.removeItem(key);
        } else {
            window.localStorage.setItem(key, value);
        }
    } catch {
        // See readStorage()
    }
}
