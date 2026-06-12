import { formatCents } from '@/lib/billing';
import { BillingStatus } from '@/types';
import { usePage } from '@inertiajs/react';

interface Props {
    type: 'staff' | 'customers';
}

/**
 * Warnt beim Anlegen von Mitarbeitern/Kunden, wenn das inkludierte Kontingent
 * des Abos überschritten würde und Zusatzkosten anfallen.
 */
export default function BillingOverageWarning({ type }: Props) {
    const billing = usePage().props.billing as BillingStatus | null | undefined;
    const entry = billing?.usage?.[type];

    if (!entry || entry.limit === null || entry.usage + 1 <= entry.limit) {
        return null;
    }

    const label = type === 'staff' ? 'Mitarbeiters' : 'Kunden';

    return (
        <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
            Das Anlegen eines weiteren {label} überschreitet Ihr inkludiertes Kontingent ({entry.limit}).
            {entry.extra_price_cents > 0 && (
                <> Es fallen zusätzlich {formatCents(entry.extra_price_cents)}/Monat an, die automatisch mit der nächsten Rechnung abgerechnet werden.</>
            )}
        </div>
    );
}
