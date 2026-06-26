import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Company {
    id: number;
    name: string;
    slug: string;
    email: string | null;
    phone: string | null;
    timezone: string;
    is_active: boolean;
    plan_id: number | null;
    plan_name: string | null;
    billing_exempt: boolean;
    trial_ends_at: string | null;
    on_trial: boolean;
    subscribed: boolean;
    staff_limit_override: number | null;
    customer_limit_override: number | null;
    active_staff_count: number;
    active_customers_count: number;
    staff_limit: number | null;
    customer_limit: number | null;
    prospect_search_override: boolean | null;
    has_prospect_search: boolean;
}

interface PlanOption {
    id: number;
    name: string;
    is_active: boolean;
    is_default: boolean;
}

interface Props {
    companies: Company[];
    plans: PlanOption[];
    defaultTrialDays: number;
}

type OverrideMode = 'plan' | 'unlimited' | 'custom';
type ProspectSearchMode = 'plan' | 'enabled' | 'disabled';

interface BillingFormState {
    plan_id: string;
    billing_exempt: boolean;
    is_active: boolean;
    staff_mode: OverrideMode;
    staff_value: string;
    customer_mode: OverrideMode;
    customer_value: string;
    prospect_search_mode: ProspectSearchMode;
    trial_ends_at: string;
}

function overrideMode(value: number | null): OverrideMode {
    if (value === null) return 'plan';
    if (value < 0) return 'unlimited';
    return 'custom';
}

function overrideValue(mode: OverrideMode, value: string): number | null {
    if (mode === 'plan') return null;
    if (mode === 'unlimited') return -1;
    return parseInt(value || '0', 10);
}

function prospectSearchMode(value: boolean | null): ProspectSearchMode {
    if (value === true) return 'enabled';
    if (value === false) return 'disabled';
    return 'plan';
}

function prospectSearchOverride(mode: ProspectSearchMode): boolean | null {
    if (mode === 'enabled') return true;
    if (mode === 'disabled') return false;
    return null;
}

function limitLabel(limit: number | null): string {
    return limit === null ? '∞' : String(limit);
}

function billingStatus(company: Company): { label: string; variant: 'default' | 'secondary' | 'destructive' } {
    if (company.billing_exempt) return { label: 'Befreit (Demo)', variant: 'secondary' };
    if (company.subscribed) return { label: 'Abo aktiv', variant: 'default' };
    if (company.on_trial) return { label: `Testzeitraum bis ${company.trial_ends_at}`, variant: 'secondary' };
    return { label: 'Kein Abo (Read-only)', variant: 'destructive' };
}

const selectClass = 'flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm';

function CompanyBillingForm({ company, plans }: { company: Company; plans: PlanOption[] }) {
    const [form, setForm] = useState<BillingFormState>({
        plan_id: company.plan_id ? String(company.plan_id) : '',
        billing_exempt: company.billing_exempt,
        is_active: company.is_active,
        staff_mode: overrideMode(company.staff_limit_override),
        staff_value: company.staff_limit_override !== null && company.staff_limit_override >= 0 ? String(company.staff_limit_override) : '',
        customer_mode: overrideMode(company.customer_limit_override),
        customer_value:
            company.customer_limit_override !== null && company.customer_limit_override >= 0 ? String(company.customer_limit_override) : '',
        prospect_search_mode: prospectSearchMode(company.prospect_search_override),
        trial_ends_at: company.trial_ends_at ?? '',
    });
    const [processing, setProcessing] = useState(false);

    const submit = (e: FormEvent) => {
        e.preventDefault();

        router.patch(
            route('admin.companies.update', company.id),
            {
                plan_id: form.plan_id ? parseInt(form.plan_id, 10) : null,
                billing_exempt: form.billing_exempt,
                is_active: form.is_active,
                staff_limit_override: overrideValue(form.staff_mode, form.staff_value),
                customer_limit_override: overrideValue(form.customer_mode, form.customer_value),
                prospect_search_override: prospectSearchOverride(form.prospect_search_mode),
                trial_ends_at: form.trial_ends_at || null,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <form onSubmit={submit} className="mt-4 grid gap-4 rounded-md border bg-gray-50 p-4 md:grid-cols-2">
            <div>
                <Label htmlFor={`plan-${company.id}`}>Abo</Label>
                <select
                    id={`plan-${company.id}`}
                    className={selectClass}
                    value={form.plan_id}
                    onChange={(e) => setForm({ ...form, plan_id: e.target.value })}
                >
                    <option value="">Kein Abo zugewiesen</option>
                    {plans.map((plan) => (
                        <option key={plan.id} value={plan.id}>
                            {plan.name}
                            {plan.is_default ? ' (Standard)' : ''}
                            {!plan.is_active ? ' (inaktiv)' : ''}
                        </option>
                    ))}
                </select>
                {company.subscribed && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        Achtung: Die Firma hat ein aktives Stripe-Abo. Ein Wechsel tauscht den Basispreis im laufenden Abo.
                    </p>
                )}
            </div>

            <div>
                <Label htmlFor={`trial-${company.id}`}>Testzeitraum bis</Label>
                <Input
                    id={`trial-${company.id}`}
                    type="date"
                    value={form.trial_ends_at}
                    onChange={(e) => setForm({ ...form, trial_ends_at: e.target.value })}
                />
                <p className="mt-1 text-xs text-muted-foreground">Leer lassen, um den Testzeitraum zu beenden.</p>
            </div>

            <div>
                <Label htmlFor={`staff-mode-${company.id}`}>Mitarbeiter-Limit (Override)</Label>
                <div className="flex gap-2">
                    <select
                        id={`staff-mode-${company.id}`}
                        className={selectClass}
                        value={form.staff_mode}
                        onChange={(e) => setForm({ ...form, staff_mode: e.target.value as OverrideMode })}
                    >
                        <option value="plan">Wert aus Abo</option>
                        <option value="unlimited">Unendlich</option>
                        <option value="custom">Eigener Wert</option>
                    </select>
                    {form.staff_mode === 'custom' && (
                        <Input
                            type="number"
                            min={0}
                            className="w-28"
                            value={form.staff_value}
                            onChange={(e) => setForm({ ...form, staff_value: e.target.value })}
                            required
                        />
                    )}
                </div>
            </div>

            <div>
                <Label htmlFor={`customer-mode-${company.id}`}>Kunden-Limit (Override)</Label>
                <div className="flex gap-2">
                    <select
                        id={`customer-mode-${company.id}`}
                        className={selectClass}
                        value={form.customer_mode}
                        onChange={(e) => setForm({ ...form, customer_mode: e.target.value as OverrideMode })}
                    >
                        <option value="plan">Wert aus Abo</option>
                        <option value="unlimited">Unendlich</option>
                        <option value="custom">Eigener Wert</option>
                    </select>
                    {form.customer_mode === 'custom' && (
                        <Input
                            type="number"
                            min={0}
                            className="w-28"
                            value={form.customer_value}
                            onChange={(e) => setForm({ ...form, customer_value: e.target.value })}
                            required
                        />
                    )}
                </div>
            </div>

            <div>
                <Label htmlFor={`prospect-search-${company.id}`}>Kundensuche</Label>
                <select
                    id={`prospect-search-${company.id}`}
                    className={selectClass}
                    value={form.prospect_search_mode}
                    onChange={(e) => setForm({ ...form, prospect_search_mode: e.target.value as ProspectSearchMode })}
                >
                    <option value="plan">Wert aus Abo / Add-on</option>
                    <option value="enabled">Freigeschaltet (Override)</option>
                    <option value="disabled">Gesperrt (Override)</option>
                </select>
                {company.has_prospect_search && (
                    <p className="mt-1 text-xs text-green-700">Aktuell: Kundensuche verfügbar</p>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-6 md:col-span-2">
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.billing_exempt}
                        onChange={(e) => setForm({ ...form, billing_exempt: e.target.checked })}
                    />
                    Von Abrechnung befreit (Demo-/Entwickler-Zugang)
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.is_active}
                        onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                    />
                    Firma aktiv
                </label>
            </div>

            <div className="md:col-span-2">
                <Button type="submit" size="sm" disabled={processing}>
                    Speichern
                </Button>
            </div>
        </form>
    );
}

export default function Index({ companies, plans, defaultTrialDays }: Props) {
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        phone: '',
        timezone: 'Europe/Berlin',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('admin.companies.store'), {
            onSuccess: () => reset(),
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Unternehmen</h2>}
        >
            <Head title="Unternehmen" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <Card>
                        <CardHeader>
                            <CardTitle>Neues Unternehmen</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        required
                                    />
                                    {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
                                </div>
                                <div>
                                    <Label htmlFor="email">E-Mail</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="phone">Telefon</Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                    />
                                </div>
                                <div className="flex items-end">
                                    <Button type="submit" disabled={processing}>
                                        Anlegen
                                    </Button>
                                </div>
                            </form>
                            {defaultTrialDays > 0 && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    Neue Firmen erhalten automatisch {defaultTrialDays} Tage Testzeitraum.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Alle Unternehmen</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y">
                                {companies.map((company) => {
                                    const status = billingStatus(company);

                                    return (
                                        <li key={company.id} className="py-3">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <p className="font-medium">{company.name}</p>
                                                        <Badge variant={status.variant}>{status.label}</Badge>
                                                        {!company.is_active && <Badge variant="destructive">Deaktiviert</Badge>}
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">
                                                        {company.slug} · Abo: {company.plan_name ?? '–'} · Mitarbeiter:{' '}
                                                        {company.active_staff_count}/{limitLabel(company.staff_limit)} · Kunden:{' '}
                                                        {company.active_customers_count}/{limitLabel(company.customer_limit)}
                                                        {company.has_prospect_search ? ' · Kundensuche aktiv' : ''}
                                                    </p>
                                                </div>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setExpandedId(expandedId === company.id ? null : company.id)
                                                    }
                                                >
                                                    {expandedId === company.id ? 'Schließen' : 'Abo & Limits'}
                                                </Button>
                                            </div>
                                            {expandedId === company.id && (
                                                <CompanyBillingForm company={company} plans={plans} />
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
