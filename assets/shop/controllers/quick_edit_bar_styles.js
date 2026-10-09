// Lives inside the shadow root, so neither the shop theme nor this bar can affect each other
export const BAR_HEIGHT = 40;

const FONT = '13px/1.4 system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';

export const styles = `
    :host {
        all: initial;
    }

    .bar {
        box-sizing: border-box;
        display: flex;
        align-items: center;
        gap: 4px;
        height: ${BAR_HEIGHT}px;
        padding: 0 12px;
        overflow-x: auto;
        white-space: nowrap;
        background: #1d2327;
        color: #f0f0f1;
        font: ${FONT};
        box-shadow: 0 1px 3px rgba(0, 0, 0, .3);
    }

    a,
    .group {
        flex: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 30px;
        padding: 0 10px;
        border-radius: 6px;
        color: inherit;
        text-decoration: none;
        transition: background-color .15s ease-in-out;
    }

    .group {
        border: 0;
        background: transparent;
        font: inherit;
        cursor: pointer;
    }

    a:hover,
    a:focus-visible,
    .group:hover,
    .group:focus-visible,
    .group[aria-expanded="true"] {
        background: rgba(255, 255, 255, .12);
    }

    a:focus-visible,
    .group:focus-visible {
        outline: 2px solid #1abb9c;
        outline-offset: 1px;
    }

    .administration {
        font-weight: 600;
    }

    .separator {
        flex: none;
        width: 1px;
        height: 20px;
        margin: 0 6px;
        background: rgba(255, 255, 255, .2);
    }

    .resource {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-width: 0;
        overflow: hidden;
        padding: 0 6px;
        color: #c3c4c7;
    }

    .resource-name {
        min-width: 0;
        overflow: hidden;
        max-width: 32ch;
        text-overflow: ellipsis;
        color: #fff;
        font-weight: 600;
    }

    .action-edit {
        background: #1abb9c;
        color: #fff;
    }

    .action-edit:hover,
    .action-edit:focus-visible {
        background: #169f85;
    }

    .group-wrapper {
        display: contents;
    }

    .chevron {
        width: 12px;
        height: 12px;
    }

    .menu {
        position: fixed;
        inset: auto;
        box-sizing: border-box;
        min-width: 220px;
        max-width: min(360px, calc(100vw - 16px));
        max-height: min(60vh, 420px);
        margin: 0;
        padding: 6px;
        overflow-y: auto;
        border: 1px solid rgba(255, 255, 255, .12);
        border-radius: 8px;
        background: #1d2327;
        color: #f0f0f1;
        font: ${FONT};
        box-shadow: 0 8px 24px rgba(0, 0, 0, .35);
    }

    .menu .menu-link {
        display: flex;
        box-sizing: border-box;
        width: 100%;
        height: auto;
        padding: 8px 10px;
    }

    .menu-label {
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .menu-separator {
        height: 1px;
        margin: 6px 4px;
        background: rgba(255, 255, 255, .12);
    }

    @media (max-width: 640px) {
        .label,
        .resource-type {
            display: none;
        }

        a {
            padding: 0 8px;
        }
    }

    svg {
        flex: none;
        width: 16px;
        height: 16px;
    }
`;
