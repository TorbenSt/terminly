/** PHP/Carbon day_of_week: 0 = Sonntag, 1 = Montag, …, 6 = Samstag */
export const WEEK_DAYS_MONDAY_FIRST: number[] = [1, 2, 3, 4, 5, 6, 0];

export const DAY_LABELS_SHORT: Record<number, string> = {
    0: 'So',
    1: 'Mo',
    2: 'Di',
    3: 'Mi',
    4: 'Do',
    5: 'Fr',
    6: 'Sa',
};

export const DAY_LABELS_LONG: Record<number, string> = {
    0: 'Sonntag',
    1: 'Montag',
    2: 'Dienstag',
    3: 'Mittwoch',
    4: 'Donnerstag',
    5: 'Freitag',
    6: 'Samstag',
};

export function compareWeekdaysMondayFirst(a: number, b: number): number {
    return WEEK_DAYS_MONDAY_FIRST.indexOf(a) - WEEK_DAYS_MONDAY_FIRST.indexOf(b);
}

export function formatTime24(value: string): string {
    return value.slice(0, 5);
}

const HOUR_OPTIONS = Array.from({ length: 24 }, (_, hour) => String(hour).padStart(2, '0'));
const MINUTE_OPTIONS = Array.from({ length: 60 }, (_, minute) => String(minute).padStart(2, '0'));

export { HOUR_OPTIONS, MINUTE_OPTIONS };

export function parseTime24(value: string): { hour: string; minute: string } {
    const [hour = '00', minute = '00'] = value.split(':');

    return {
        hour: hour.padStart(2, '0').slice(0, 2),
        minute: minute.padStart(2, '0').slice(0, 2),
    };
}

export function buildTime24(hour: string, minute: string): string {
    return `${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
}

export function formatDateDe(value: string | Date): string {
    const date = typeof value === 'string' ? new Date(value.includes('T') ? value : `${value}T12:00:00`) : value;

    return date.toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

export function formatDateLongDe(value: string | Date): string {
    const date = typeof value === 'string' ? new Date(value.includes('T') ? value : `${value}T12:00:00`) : value;

    return date.toLocaleDateString('de-DE', {
        weekday: 'long',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

export function formatDateTimeDe(value: string | Date): string {
    const date = typeof value === 'string' ? new Date(value) : value;

    return date.toLocaleString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
}
