import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCents } from '@/lib/billing';
import { BillingUsage } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Plan {
    id: number;
    name: string;
    description: string | null;
    price_cents: number;
    included_staff: number | null;
    included_customers: number | null;
    extra_staff_price_cents: number;
    extra_customer_price_cents: number;
}

interface Invoice {
    id: string;
    date: string;
    total: string;
    status: string | null;
    url: string | null;
}

interface Props {
    company: {
        name: string;
        billing_exempt: boolean;
        on_trial: boolean;
        trial_ends_at: string | null;
        subscribed: boolean;
        subscription_status: string | null;
        on_grace_period: boolean;
        ends_at: string | null;
    };
    currentPlan: Plan | null;
    effectivePlan: Plan | null;
    plans: Plan[];
    usage: BillingUsage;
    invoices: Invoice[];
    stripeConfigured: boolean;
    prospectAddon: {
        price_cents: number;
        has_access: boolean;
        has_addon: boolean;
        included_in_plan: boolean;
    };
}

function limitLabel(limit: number | null): string {
    return limit === null ? 'unendlich' : String(limit);
}

function UsageBar({ label, usage, limit }: { label: string; usage: number; limit: number | null }) {
    const percent = limit === null || limit === 0 ? 0 : Math.min(100, (usage / limit) * 100);
    const over = limit !== null && usage > limit;

    return (
        <div>
            <div className="flex justify-between text-sm">
                <span>{label}</span>
                <span className={over ? 'font-medium text-amber-600' : ''}>
                    {usage} / {limitLabel(limit)}
                </span>
            </div>
            {limit !== null && (
                <div className="mt-1 h-2 w-full rounded-full bg-gray-200">
                    <div
                        className={`h-2 rounded-full ${over ? 'bg-amber-500' : 'bg-primary'}`}
                        style={{ width: `${percent}%` }}
                    />
                </div>
            )}
        </div>
    );
}

export default function Index({ company, currentPlan, effectivePlan, plans, usage, invoices, stripeConfigured, prospectAddon }: Props) {
    const [processingPlanId, setProcessingPlanId] = useState<number | null>(null);
    const [processingAddon, setProcessingAddon] = useState(false);

    const startCheckout = (plan: Plan) => {
        router.post(
            route('billing.checkout'),
            { plan_id: plan.id },
            {
                onStart: () => setProcessingPlanId(plan.id),
                onFinish: () => setProcessingPlanId(null),
            },
        );
    };

    const purchaseProspectAddon = () => {
        router.post(route('billing.prospect-addon'), {}, {
            onStart: () => setProcessingAddon(true),
            onFinish: () => setProcessingAddon(false),
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Abo & Abrechnung</h2>}
        >
            <Head title="Abo & Abrechnung" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <Card>
                        <CardHeader>
                            <CardTitle>Status</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex flex-wrap items-center gap-2">
                                {company.billing_exempt && <Badge variant="secondary">Von Abrechnung befreit</Badge>}
                                {company.subscribed && <Badge>Abo aktiv{currentPlan ? `: ${currentPlan.name}` : ''}</Badge>}
                                {company.on_grace_period && (
                                    <Badge variant="secondary">Gekündigt – läuft bis {company.ends_at}</Badge>
                                )}
                                {!company.subscribed && company.on_trial && (
                                    <Badge variant="secondary">Testzeitraum bis {company.trial_ends_at}</Badge>
                                )}
                                {!company.subscribed && !company.on_trial && !company.billing_exempt && (
                                    <Badge variant="destructive">Kein aktives Abo – nur Lesezugriff</Badge>
                                )}
                            </div>

                            {company.subscribed && (
                                <Button variant="outline" onClick={() => (window.location.href = route('billing.portal'))}>
                                    Abo-Portal öffnen (Zahlungsmethode, Rechnungsdaten, Kündigung)
                                </Button>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Nutzung</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <UsageBar label="Aktive Mitarbeiter" usage={usage.staff.usage} limit={usage.staff.limit} />
                            <UsageBar label="Aktive Kunden" usage={usage.customers.usage} limit={usage.customers.limit} />

                            {usage.extra_total_cents > 0 && (
                                <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
                                    Über dem inkludierten Kontingent:{' '}
                                    {usage.staff.overage > 0 &&
                                        `${usage.staff.overage} zusätzliche(r) Mitarbeiter (${formatCents(usage.staff.overage * usage.staff.extra_price_cents)}/Monat)`}
                                    {usage.staff.overage > 0 && usage.customers.overage > 0 && ', '}
                                    {usage.customers.overage > 0 &&
                                        `${usage.customers.overage} zusätzliche Kunden (${formatCents(usage.customers.overage * usage.customers.extra_price_cents)}/Monat)`}
                                    {' – '}insgesamt {formatCents(usage.extra_total_cents)}/Monat zusätzlich auf der nächsten
                                    Rechnung.
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {company.subscribed && !prospectAddon.has_access && !company.billing_exempt && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Kundensuche Add-on</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <p className="text-sm text-muted-foreground">
                                    Finden Sie potenzielle B2B-Kunden per Google Places in Ihrer Region — mit KI-Bewertung
                                    und Kaltakquise-E-Mails.
                                </p>
                                <Button
                                    disabled={!stripeConfigured || processingAddon}
                                    onClick={purchaseProspectAddon}
                                >
                                    {processingAddon
                                        ? 'Wird gebucht…'
                                        : `Kundensuche buchen (${formatCents(prospectAddon.price_cents)}/Monat)`}
                                </Button>
                            </CardContent>
                        </Card>
                    )}

                    {prospectAddon.has_access && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Kundensuche</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge>Aktiv</Badge>
                                    {prospectAddon.included_in_plan && (
                                        <span className="text-sm text-muted-foreground">Im Abo enthalten</span>
                                    )}
                                    {prospectAddon.has_addon && !prospectAddon.included_in_plan && (
                                        <span className="text-sm text-muted-foreground">
                                            Add-on ({formatCents(prospectAddon.price_cents)}/Monat)
                                        </span>
                                    )}
                                    {company.billing_exempt && (
                                        <span className="text-sm text-muted-foreground">Demo-Zugang</span>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {!company.subscribed && !company.billing_exempt && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Abo auswählen</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {!stripeConfigured && (
                                    <p className="mb-4 text-sm text-amber-700">
                                        Stripe ist nicht konfiguriert – Buchung derzeit nicht möglich.
                                    </p>
                                )}
                                {plans.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">Derzeit sind keine Abos verfügbar.</p>
                                ) : (
                                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                        {plans.map((plan) => (
                                            <div key={plan.id} className="flex flex-col rounded-md border p-4">
                                                <p className="font-medium">{plan.name}</p>
                                                <p className="text-2xl font-semibold">
                                                    {formatCents(plan.price_cents)}
                                                    <span className="text-sm font-normal text-muted-foreground">/Monat</span>
                                                </p>
                                                {plan.description && (
                                                    <p className="mt-1 text-sm text-muted-foreground">{plan.description}</p>
                                                )}
                                                <ul className="mt-2 flex-1 space-y-1 text-sm text-muted-foreground">
                                                    <li>{limitLabel(plan.included_staff)} Mitarbeiter inklusive</li>
                                                    <li>{limitLabel(plan.included_customers)} Kunden inklusive</li>
                                                    {plan.included_staff !== null && (
                                                        <li>
                                                            +{formatCents(plan.extra_staff_price_cents)}/Monat je weiterem Mitarbeiter
                                                        </li>
                                                    )}
                                                    {plan.included_customers !== null && (
                                                        <li>
                                                            +{formatCents(plan.extra_customer_price_cents)}/Monat je weiterem Kunden
                                                        </li>
                                                    )}
                                                </ul>
                                                <Button
                                                    className="mt-4"
                                                    disabled={!stripeConfigured || processingPlanId !== null}
                                                    onClick={() => startCheckout(plan)}
                                                >
                                                    {processingPlanId === plan.id ? 'Weiterleitung…' : 'Jetzt buchen'}
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                                <p className="mt-4 text-xs text-muted-foreground">
                                    Gutschein- und Promo-Codes können im Stripe-Checkout eingelöst werden.
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {effectivePlan && !company.subscribed && (company.on_trial || company.billing_exempt) && (
                        <p className="px-1 text-sm text-muted-foreground">
                            Aktuell gelten die Limits des Abos „{effectivePlan.name}“.
                        </p>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Rechnungen</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {invoices.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Noch keine Rechnungen vorhanden.</p>
                            ) : (
                                <ul className="divide-y">
                                    {invoices.map((invoice) => (
                                        <li key={invoice.id} className="flex items-center justify-between py-3 text-sm">
                                            <span>{invoice.date}</span>
                                            <span>{invoice.total}</span>
                                            <span className="text-muted-foreground">{invoice.status}</span>
                                            {invoice.url ? (
                                                <a
                                                    href={invoice.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-primary underline"
                                                >
                                                    Ansehen
                                                </a>
                                            ) : (
                                                <span />
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
