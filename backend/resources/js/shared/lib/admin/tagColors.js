export function statusTagColor(status) {
    if (['active', 'completed', 'approved', 'credited'].includes(status)) {
        return 'success';
    }

    if (['suspended', 'pending_review', 'pending_admin_confirmation', 'payment_proof_uploaded', 'awaiting_kzt_payment', 'needs_reconcile', 'manual_review', 'kzt_sent'].includes(status)) {
        return 'warning';
    }

    if (['blocked', 'failed', 'rejected', 'cancelled', 'dispute'].includes(status)) {
        return 'error';
    }

    if (['created', 'sending', 'sent', 'kzt_received', 'awaiting_telegram_confirmation'].includes(status)) {
        return 'processing';
    }

    return 'default';
}

export function withdrawalStatusTagColor(status) {
    if (status === 'completed') {
        return 'success';
    }

    if (['pending_review', 'needs_reconcile', 'awaiting_telegram_confirmation'].includes(status)) {
        return 'warning';
    }

    if (['failed', 'rejected', 'cancelled'].includes(status)) {
        return 'error';
    }

    if (['approved', 'sending', 'sent', 'created'].includes(status)) {
        return 'processing';
    }

    return 'default';
}

export function networkTagColor(network) {
    if (network === 'TRC20') {
        return 'red';
    }

    if (network === 'BEP20') {
        return 'gold';
    }

    return 'blue';
}

export function clientTypeTagColor(type) {
    return type === 'legal_entity' ? 'blue' : 'default';
}
