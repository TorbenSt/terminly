import { router } from '@inertiajs/react';

export const SCHEDULING_LAB_COMPLETE_EVENT = 'scheduling-lab:complete';

export function completeSchedulingLabFlow(schedulingLab: boolean): void {
    if (!schedulingLab) {
        return;
    }

    if (window.self !== window.top) {
        window.parent.postMessage({ type: SCHEDULING_LAB_COMPLETE_EVENT }, window.location.origin);

        return;
    }

    router.visit(route('admin.scheduling-lab.index'));
}

/** @deprecated use completeSchedulingLabFlow */
export function returnToSchedulingLabIfEmbedded(schedulingLab: boolean): void {
    completeSchedulingLabFlow(schedulingLab);
}

export function withSchedulingLabParam(url: string): string {
    const separator = url.includes('?') ? '&' : '?';

    return `${url}${separator}scheduling_lab=1`;
}

export function isSchedulingLabPublicUrl(url: string): boolean {
    try {
        const parsed = new URL(url, window.location.origin);

        return (
            parsed.pathname.startsWith('/p/proposals/') ||
            parsed.pathname.startsWith('/p/negotiations/')
        );
    } catch {
        return false;
    }
}
