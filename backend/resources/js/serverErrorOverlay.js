import { i18n } from '@/i18n';

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
    button.className = 'btn-primary';
    button.textContent = i18n.global.t('serverError.dismiss');
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

    const badge = panel.querySelector('.badge');
    if (badge) {
        const text = String(badge.textContent ?? '').trim();
        const code = text.match(/\b(\d{3})\b/)?.[1];
        if (code) {
            badge.textContent = i18n.global.t('serverError.badge', { code });
        }
    }

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
            max-width: 520px;
            width: 100%;
            border-radius: 20px;
            border: 1px solid rgba(148, 163, 184, 0.35);
            background: #ffffff;
            box-shadow: 0 14px 44px rgba(15, 23, 42, 0.18);
            overflow: hidden;
            font-family: Manrope, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        }

        #${OVERLAY_ID} .badge {
            display: block;
            width: 100%;
            padding: 20px 20px 0;
            margin: 0;
            text-align: center;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: 0.01em;
            color: #2563eb;
            background: transparent;
            border: 0;
            border-radius: 0;
        }

        #${OVERLAY_ID} #error-title,
        #${OVERLAY_ID} h1 {
            margin: 10px 0 0;
            padding: 0 20px;
            text-align: center;
            font-size: 18px;
            line-height: 24px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.01em;
        }

        #${OVERLAY_ID} #error-desc,
        #${OVERLAY_ID} p {
            margin: 10px 0 0;
            padding: 0 20px 18px;
            text-align: center;
            font-size: 14px;
            line-height: 20px;
            color: #64748b;
        }

        #${OVERLAY_ID} .panel > :is(#error-title, h1) + :is(#error-desc, p) {
            padding-bottom: 18px;
        }

        #${OVERLAY_ID} .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            padding: 16px 20px 20px;
            border-top: 1px solid rgba(226, 232, 240, 0.9);
            background: #f8fafc;
        }

        #${OVERLAY_ID} .actions .btn-primary {
            max-width: 240px;
            width: 100%;
        }

        /* Remove the small footer line if present */
        #${OVERLAY_ID} .footer,
        #${OVERLAY_ID} .panel footer {
            display: none !important;
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
