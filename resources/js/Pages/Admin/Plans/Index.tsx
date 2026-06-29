import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { centsToEuros, eurosToCents, formatCents } from '@/lib/billing';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Plan {
    id: number;
    name: string;
    description: string | null;
    price_cents: number;
    currency: string;
    included_staff: number | null;
    included_customers: number | null;
    extra_staff_price_cents: number;
    extra_customer_price_cents: number;
    includes_prospect_search: boolean;
    max_prospect_results_per_run: number | null;
    prospect_outreach_limit_per_day: number | null;
    is_active: boolean;
    is_default: boolean;
    companies_count: number;
}

interface Props {
    plans: Plan[];
    defaultTrialDays: number;
    prospectSearchPriceCents: number;
    stripeConfigured: boolean;
}

interface PlanFormData {
    name: string;
    description: string;
    price_euros: string;
    included_staff: string;
    staff_unlimited: boolean;
    included_customers: string;
    customers_unlimited: boolean;
    extra_staff_price_euros: string;
    extra_customer_price_euros: string;
    includes_prospect_search: boolean;
    max_prospect_results_per_run: string;
    prospect_outreach_limit_per_day: string;
    is_active: boolean;
    is_default: boolean;
}

const emptyForm: PlanFormData = {
    name: '',
    description: '',
    price_euros: '',
    included_staff: '',
    staff_unlimited: false,
    included_customers: '',
    customers_unlimited: false,
    extra_staff_price_euros: '',
    extra_customer_price_euros: '',
    includes_prospect_search: false,
    max_prospect_results_per_run: '25',
    prospect_outreach_limit_per_day: '',
    is_active: true,
    is_default: false,
};

function limitLabel(limit: number | null): string {
    return limit === null ? 'unendlich' : String(limit);
}

export default function Index({ plans, defaultTrialDays, prospectSearchPriceCents, stripeConfigured }: Props) {
    const [editingPlan, setEditingPlan] = useState<Plan | null>(null);
    const [trialDays, setTrialDays] = useState(String(defaultTrialDays));
    const [prospectPriceEuros, setProspectPriceEuros] = useState(centsToEuros(prospectSearchPriceCents));
    const [form, setForm] = useState<PlanFormData>(emptyForm);
    const [processing, setProcessing] = useState(false);
    const trialForm = useForm({ default_trial_days: defaultTrialDays });

    const setField = <K extends keyof PlanFormData>(key: K, value: PlanFormData[K]) =>
        setForm((previous) => ({ ...previous, [key]: value }));

    const startEdit = (plan: Plan) => {
        setEditingPlan(plan);
        setForm({
            name: plan.name,
            description: plan.description ?? '',
            price_euros: centsToEuros(plan.price_cents),
            included_staff: plan.included_staff === null ? '' : String(plan.included_staff),
            staff_unlimited: plan.included_staff === null,
            included_customers: plan.included_customers === null ? '' : String(plan.included_customers),
            customers_unlimited: plan.included_customers === null,
            extra_staff_price_euros: centsToEuros(plan.extra_staff_price_cents),
            extra_customer_price_euros: centsToEuros(plan.extra_customer_price_cents),
            includes_prospect_search: plan.includes_prospect_search,
            max_prospect_results_per_run:
                plan.max_prospect_results_per_run === null ? '' : String(plan.max_prospect_results_per_run),
            prospect_outreach_limit_per_day:
                plan.prospect_outreach_limit_per_day === null ? '' : String(plan.prospect_outreach_limit_per_day),
            is_active: plan.is_active,
            is_default: plan.is_default,
        });
    };

    const cancelEdit = () => {
        setEditingPlan(null);
        setForm(emptyForm);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        const payload = {
            name: form.name,
            description: form.description || null,
            price_cents: eurosToCents(form.price_euros),
            included_staff: form.staff_unlimited ? null : parseInt(form.included_staff || '0', 10),
            included_customers: form.customers_unlimited ? null : parseInt(form.included_customers || '0', 10),
            extra_staff_price_cents: eurosToCents(form.extra_staff_price_euros),
            extra_customer_price_cents: eurosToCents(form.extra_customer_price_euros),
            includes_prospect_search: form.includes_prospect_search,
            max_prospect_results_per_run: form.max_prospect_results_per_run
                ? parseInt(form.max_prospect_results_per_run, 10)
                : null,
            prospect_outreach_limit_per_day: form.prospect_outreach_limit_per_day
                ? parseInt(form.prospect_outreach_limit_per_day, 10)
                : null,
            is_active: form.is_active,
            is_default: form.is_default,
        };

        const options = {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => cancelEdit(),
        };

        if (editingPlan) {
            router.patch(route('admin.plans.update', editingPlan.id), payload, options);
        } else {
            router.post(route('admin.plans.store'), payload, options);
        }
    };

    const deletePlan = (plan: Plan) => {
        if (
            confirm(
                plan.companies_count > 0
                    ? `"${plan.name}" wird von ${plan.companies_count} Firma/Firmen genutzt und wird deshalb nur deaktiviert. Fortfahren?`
                    : `Abo "${plan.name}" wirklich löschen?`,
            )
        ) {
            router.delete(route('admin.plans.destroy', plan.id));
        }
    };

    const saveTrialDays = (e: FormEvent) => {
        e.preventDefault();
        trialForm.transform(() => ({
            default_trial_days: parseInt(trialDays || '0', 10),
            prospect_search_price_cents: eurosToCents(prospectPriceEuros),
        }));
        trialForm.patch(route('admin.billing-settings.update'));
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Abos</h2>}
        >
            <Head title="Abos" />

            <div className="space-y-6">
                    {!stripeConfigured && (
                        <div className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
                            Stripe ist nicht konfiguriert (STRIPE_SECRET fehlt). Abos werden nur lokal
                            gespeichert; Checkout und Zahlungen sind erst nach Konfiguration möglich.
                        </div>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Billing-Einstellungen</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={saveTrialDays} className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="trial-days">Standard-Testzeitraum für neue Firmen (Tage)</Label>
                                    <Input
                                        id="trial-days"
                                        type="number"
                                        min={0}
                                        max={365}
                                        className="w-32"
                                        value={trialDays}
                                        onChange={(e) => setTrialDays(e.target.value)}
                                    />
                                    <p className="mt-1 text-xs text-muted-foreground">0 = kein Testzeitraum</p>
                                </div>
                                <div>
                                    <Label htmlFor="prospect-addon-price">Kundensuche Add-on (€/Monat)</Label>
                                    <Input
                                        id="prospect-addon-price"
                                        inputMode="decimal"
                                        placeholder="z.B. 19,00"
                                        value={prospectPriceEuros}
                                        onChange={(e) => setProspectPriceEuros(e.target.value)}
                                    />
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Für Firmen ohne Kundensuche im Abo. Bei Änderung wird ein neuer Stripe-Preis angelegt.
                                    </p>
                                </div>
                                <div className="md:col-span-2">
                                    <Button type="submit" disabled={trialForm.processing}>
                                        Speichern
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{editingPlan ? `Abo bearbeiten: ${editingPlan.name}` : 'Neues Abo'}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="plan-name">Name</Label>
                                    <Input
                                        id="plan-name"
                                        value={form.name}
                                        onChange={(e) => setField('name', e.target.value)}
                                        required
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="plan-price">Monatlicher Basispreis (€)</Label>
                                    <Input
                                        id="plan-price"
                                        inputMode="decimal"
                                        placeholder="z.B. 29,90"
                                        value={form.price_euros}
                                        onChange={(e) => setField('price_euros', e.target.value)}
                                        required
                                    />
                                </div>
                                <div className="md:col-span-2">
                                    <Label htmlFor="plan-description">Beschreibung</Label>
                                    <Textarea
                                        id="plan-description"
                                        value={form.description}
                                        onChange={(e) => setField('description', e.target.value)}
                                        rows={2}
                                    />
                                </div>

                                <div className="space-y-2 rounded-md border p-4">
                                    <p className="font-medium">Mitarbeiter</p>
                                    <div>
                                        <Label htmlFor="plan-staff">Inkludierte Mitarbeiter</Label>
                                        <Input
                                            id="plan-staff"
                                            type="number"
                                            min={0}
                                            value={form.included_staff}
                                            onChange={(e) => setField('included_staff', e.target.value)}
                                            disabled={form.staff_unlimited}
                                            required={!form.staff_unlimited}
                                        />
                                    </div>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={form.staff_unlimited}
                                            onChange={(e) => setField('staff_unlimited', e.target.checked)}
                                        />
                                        Unendlich
                                    </label>
                                    <div>
                                        <Label htmlFor="plan-staff-price">Preis je zusätzlichem Mitarbeiter (€/Monat)</Label>
                                        <Input
                                            id="plan-staff-price"
                                            inputMode="decimal"
                                            placeholder="z.B. 5,00"
                                            value={form.extra_staff_price_euros}
                                            onChange={(e) => setField('extra_staff_price_euros', e.target.value)}
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2 rounded-md border p-4">
                                    <p className="font-medium">Kunden</p>
                                    <div>
                                        <Label htmlFor="plan-customers">Inkludierte Kunden</Label>
                                        <Input
                                            id="plan-customers"
                                            type="number"
                                            min={0}
                                            value={form.included_customers}
                                            onChange={(e) => setField('included_customers', e.target.value)}
                                            disabled={form.customers_unlimited}
                                            required={!form.customers_unlimited}
                                        />
                                    </div>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={form.customers_unlimited}
                                            onChange={(e) => setField('customers_unlimited', e.target.checked)}
                                        />
                                        Unendlich
                                    </label>
                                    <div>
                                        <Label htmlFor="plan-customer-price">Preis je zusätzlichem Kunden (€/Monat)</Label>
                                        <Input
                                            id="plan-customer-price"
                                            inputMode="decimal"
                                            placeholder="z.B. 1,00"
                                            value={form.extra_customer_price_euros}
                                            onChange={(e) => setField('extra_customer_price_euros', e.target.value)}
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2 rounded-md border p-4 md:col-span-2">
                                    <p className="font-medium">Kundensuche (KI-Prospects)</p>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={form.includes_prospect_search}
                                            onChange={(e) => setField('includes_prospect_search', e.target.checked)}
                                        />
                                        Kundensuche im Abo enthalten
                                    </label>
                                    <div>
                                        <Label htmlFor="plan-prospect-max">Max. Ergebnisse pro Suchlauf</Label>
                                        <Input
                                            id="plan-prospect-max"
                                            type="number"
                                            min={1}
                                            max={200}
                                            className="w-32"
                                            value={form.max_prospect_results_per_run}
                                            onChange={(e) => setField('max_prospect_results_per_run', e.target.value)}
                                            placeholder="z.B. 50"
                                        />
                                        <p className="mt-1 text-xs text-muted-foreground">Leer = globaler Cap ({100})</p>
                                    </div>
                                    <div>
                                        <Label htmlFor="plan-outreach-limit">Max. Kaltakquise-E-Mails pro Tag</Label>
                                        <Input
                                            id="plan-outreach-limit"
                                            type="number"
                                            min={0}
                                            max={500}
                                            className="w-32"
                                            value={form.prospect_outreach_limit_per_day}
                                            onChange={(e) => setField('prospect_outreach_limit_per_day', e.target.value)}
                                            placeholder="z.B. 20"
                                        />
                                        <p className="mt-1 text-xs text-muted-foreground">Leer = Env-Standard (20/Tag)</p>
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-6 md:col-span-2">
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={form.is_active}
                                            onChange={(e) => setField('is_active', e.target.checked)}
                                        />
                                        Aktiv (für neue Buchungen wählbar)
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={form.is_default}
                                            onChange={(e) => setField('is_default', e.target.checked)}
                                        />
                                        Standard-Abo (gilt für Limits im Testzeitraum)
                                    </label>
                                </div>

                                {editingPlan && (
                                    <p className="text-xs text-muted-foreground md:col-span-2">
                                        Hinweis: Bei einer Preisänderung wird in Stripe ein neuer Preis angelegt.
                                        Bestehende Abos behalten ihren bisherigen Preis; nur neue Buchungen
                                        erhalten den neuen Preis.
                                    </p>
                                )}

                                <div className="flex gap-2 md:col-span-2">
                                    <Button type="submit" disabled={processing}>
                                        {editingPlan ? 'Speichern' : 'Anlegen'}
                                    </Button>
                                    {editingPlan && (
                                        <Button type="button" variant="outline" onClick={cancelEdit}>
                                            Abbrechen
                                        </Button>
                                    )}
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Alle Abos</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {plans.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Noch keine Abos angelegt.</p>
                            ) : (
                                <ul className="divide-y">
                                    {plans.map((plan) => (
                                        <li key={plan.id} className="flex flex-wrap items-center justify-between gap-4 py-4">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium">{plan.name}</p>
                                                    {plan.is_default && <Badge>Standard</Badge>}
                                                    {!plan.is_active && <Badge variant="secondary">Inaktiv</Badge>}
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    {formatCents(plan.price_cents)}/Monat · Mitarbeiter:{' '}
                                                    {limitLabel(plan.included_staff)} (+{formatCents(plan.extra_staff_price_cents)}/Monat je weiterer) · Kunden:{' '}
                                                    {limitLabel(plan.included_customers)} (+{formatCents(plan.extra_customer_price_cents)}/Monat je weiterer)
                                                    {plan.includes_prospect_search && ' · Kundensuche inkl.'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {plan.companies_count} Firma/Firmen auf diesem Abo
                                                </p>
                                            </div>
                                            <div className="flex gap-2">
                                                <Button variant="outline" size="sm" onClick={() => startEdit(plan)}>
                                                    Bearbeiten
                                                </Button>
                                                <Button variant="destructive" size="sm" onClick={() => deletePlan(plan)}>
                                                    Löschen
                                                </Button>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
        </AuthenticatedLayout>
    );
}
