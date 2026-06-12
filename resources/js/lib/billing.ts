export function formatCents(cents: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('de-DE', {
        style: 'currency',
        currency,
    }).format(cents / 100);
}

export function eurosToCents(value: string): number {
    const normalized = value.replace(/\./g, '').replace(',', '.');
    const parsed = parseFloat(normalized);

    return Number.isNaN(parsed) ? 0 : Math.round(parsed * 100);
}

export function centsToEuros(cents: number): string {
    return (cents / 100).toFixed(2).replace('.', ',');
}
