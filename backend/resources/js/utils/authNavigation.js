import { localizedPath } from '@/utils/localizedPath';

/**
 * Full page navigation after auth so Ziggy routes match the logged-in user.
 * Client-side Inertia visits keep the guest route list from the login page.
 */
export function navigateAfterAuth(url) {
    window.location.assign(localizedPath(url));
}
