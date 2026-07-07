const OVERLAY_ID = 'inertia-server-error-overlay';

function hideServerErrorOverlay() {
    const existing = document.getElementById(OVERLAY_ID);
    if (!existing) {
        return;
    }

    existing.remove();
    document.body.style.overflow = '';
}

function dismissServerErrorOverlay() {
    hideServerErrorOverlay();
    document.removeEventListener('keydown', onEscape);
}

function onEscape(event) {
    if (event.key === 'Escape') {
        dismissServerErrorOverlay();
    }
}

function replaceActionsWithDismiss(panel) {
    const actions = panel.querySelector('.actions');
    if (!actions) {
        return;
    }

    actions.replaceChildren();

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-primary';
    button.textContent = 'Понятно';
    button.addEventListener('click', dismissServerErrorOverlay);

    actions.appendChild(button);
}

export function showServerErrorOverlay(html) {
    if (typeof html !== 'string') {
        window.location.reload();
        return;
    }

    const doc = new DOMParser().parseFromString(html, 'text/html');
    const panel = doc.querySelector('.panel');

    if (!panel) {
        window.location.reload();
        return;
    }

    hideServerErrorOverlay();
    replaceActionsWithDismiss(panel);

    const overlay = document.createElement('div');
    overlay.id = OVERLAY_ID;

    const style = document.createElement('style');
    const pageStyles = doc.querySelector('style')?.textContent ?? '';
    style.textContent = `${pageStyles}
        #${OVERLAY_ID} {
            position: fixed;
            inset: 0;
            z-index: 200000;
            display: grid;
            place-items: center;
            padding: 24px;
            background: rgba(15, 23, 42, 0.45);
            -webkit-tap-highlight-color: transparent;
        }

        #${OVERLAY_ID} .panel {
            margin: 0;
        }
    `;

    overlay.appendChild(style);
    overlay.appendChild(panel);
    document.body.prepend(overlay);
    document.body.style.overflow = 'hidden';

    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            dismissServerErrorOverlay();
        }
    });

    document.addEventListener('keydown', onEscape);
}
