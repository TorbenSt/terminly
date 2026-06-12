import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { eurosToCents, formatCents } from '@/lib/billing';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Coupon {
    id: string;
    name: string | null;
    percent_off: number | null;
    amount_off: number | null;
    currency: string | null;
    duration: string;
    duration_in_months: number | null;
    valid: boolean;
}

interface PromotionCode {
    id: string;
    code: string;
    coupon_id: string;
    coupon_name: string | null;
    active: boolean;
    times_redeemed: number;
}

interface Props {
    coupons: Coupon[];
    promotionCodes: PromotionCode[];
    stripeConfigured: boolean;
}

const durationLabels: Record<string, string> = {
    once: 'Einmalig',
    repeating: 'Mehrere Monate',
    forever: 'Dauerhaft',
};

function couponValue(coupon: Coupon): string {
    if (coupon.percent_off) {
        return `${coupon.percent_off} %`;
    }

    return formatCents(coupon.amount_off ?? 0, (coupon.currency ?? 'eur').toUpperCase());
}

export default function Index({ coupons, promotionCodes, stripeConfigured }: Props) {
    const [form, setForm] = useState({
        name: '',
        type: 'percent' as 'percent' | 'amount',
        percent_off: '',
        amount_off_euros: '',
        duration: 'once' as 'once' | 'repeating' | 'forever',
        duration_in_months: '',
        code: '',
    });
    const [processing, setProcessing] = useState(false);

    const submit = (e: FormEvent) => {
        e.preventDefault();

        router.post(
            route('admin.coupons.store'),
            {
                name: form.name,
                type: form.type,
                percent_off: form.type === 'percent' ? parseFloat(form.percent_off.replace(',', '.')) : null,
                amount_off_cents: form.type === 'amount' ? eurosToCents(form.amount_off_euros) : null,
                duration: form.duration,
                duration_in_months: form.duration === 'repeating' ? parseInt(form.duration_in_months || '0', 10) : null,
                code: form.code || null,
            },
            {
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () =>
                    setForm({
                        name: '',
                        type: 'percent',
                        percent_off: '',
                        amount_off_euros: '',
                        duration: 'once',
                        duration_in_months: '',
                        code: '',
                    }),
            },
        );
    };

    const selectClass = 'flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm';

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Gutscheine</h2>}
        >
            <Head title="Gutscheine" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {!stripeConfigured ? (
                        <div className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
                            Stripe ist nicht konfiguriert (STRIPE_SECRET fehlt). Gutscheine werden direkt in
                            Stripe verwaltet und sind erst nach Konfiguration verfügbar.
                        </div>
                    ) : (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Neuer Gutschein</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <Label htmlFor="coupon-name">Name</Label>
                                            <Input
                                                id="coupon-name"
                                                value={form.name}
                                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                                                required
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="coupon-type">Rabatt-Art</Label>
                                            <select
                                                id="coupon-type"
                                                className={selectClass}
                                                value={form.type}
                                                onChange={(e) => setForm({ ...form, type: e.target.value as 'percent' | 'amount' })}
                                            >
                                                <option value="percent">Prozent</option>
                                                <option value="amount">Fester Betrag</option>
                                            </select>
                                        </div>
                                        {form.type === 'percent' ? (
                                            <div>
                                                <Label htmlFor="coupon-percent">Rabatt (%)</Label>
                                                <Input
                                                    id="coupon-percent"
                                                    inputMode="decimal"
                                                    placeholder="z.B. 20"
                                                    value={form.percent_off}
                                                    onChange={(e) => setForm({ ...form, percent_off: e.target.value })}
                                                    required
                                                />
                                            </div>
                                        ) : (
                                            <div>
                                                <Label htmlFor="coupon-amount">Rabatt (€)</Label>
                                                <Input
                                                    id="coupon-amount"
                                                    inputMode="decimal"
                                                    placeholder="z.B. 10,00"
                                                    value={form.amount_off_euros}
                                                    onChange={(e) => setForm({ ...form, amount_off_euros: e.target.value })}
                                                    required
                                                />
                                            </div>
                                        )}
                                        <div>
                                            <Label htmlFor="coupon-duration">Gültigkeit</Label>
                                            <select
                                                id="coupon-duration"
                                                className={selectClass}
                                                value={form.duration}
                                                onChange={(e) =>
                                                    setForm({ ...form, duration: e.target.value as 'once' | 'repeating' | 'forever' })
                                                }
                                            >
                                                <option value="once">Einmalig (erste Rechnung)</option>
                                                <option value="repeating">Mehrere Monate</option>
                                                <option value="forever">Dauerhaft</option>
                                            </select>
                                        </div>
                                        {form.duration === 'repeating' && (
                                            <div>
                                                <Label htmlFor="coupon-months">Anzahl Monate</Label>
                                                <Input
                                                    id="coupon-months"
                                                    type="number"
                                                    min={1}
                                                    max={36}
                                                    value={form.duration_in_months}
                                                    onChange={(e) => setForm({ ...form, duration_in_months: e.target.value })}
                                                    required
                                                />
                                            </div>
                                        )}
                                        <div>
                                            <Label htmlFor="coupon-code">Promo-Code (optional, für Kunden im Checkout)</Label>
                                            <Input
                                                id="coupon-code"
                                                placeholder="z.B. SOMMER25"
                                                value={form.code}
                                                onChange={(e) => setForm({ ...form, code: e.target.value })}
                                            />
                                        </div>
                                        <div className="flex items-end">
                                            <Button type="submit" disabled={processing}>
                                                Anlegen
                                            </Button>
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Promo-Codes</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {promotionCodes.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">Keine Promo-Codes vorhanden.</p>
                                    ) : (
                                        <ul className="divide-y">
                                            {promotionCodes.map((code) => (
                                                <li key={code.id} className="flex items-center justify-between py-3">
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <p className="font-mono font-medium">{code.code}</p>
                                                            {!code.active && <Badge variant="secondary">Inaktiv</Badge>}
                                                        </div>
                                                        <p className="text-sm text-muted-foreground">
                                                            Gutschein: {code.coupon_name ?? code.coupon_id} · {code.times_redeemed}x eingelöst
                                                        </p>
                                                    </div>
                                                    {code.active && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                router.patch(route('admin.promotion-codes.deactivate', code.id))
                                                            }
                                                        >
                                                            Deaktivieren
                                                        </Button>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Gutscheine</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {coupons.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">Keine Gutscheine vorhanden.</p>
                                    ) : (
                                        <ul className="divide-y">
                                            {coupons.map((coupon) => (
                                                <li key={coupon.id} className="flex items-center justify-between py-3">
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <p className="font-medium">{coupon.name ?? coupon.id}</p>
                                                            {!coupon.valid && <Badge variant="secondary">Ungültig</Badge>}
                                                        </div>
                                                        <p className="text-sm text-muted-foreground">
                                                            {couponValue(coupon)} Rabatt · {durationLabels[coupon.duration] ?? coupon.duration}
                                                            {coupon.duration === 'repeating' && coupon.duration_in_months
                                                                ? ` (${coupon.duration_in_months} Monate)`
                                                                : ''}
                                                        </p>
                                                    </div>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() => {
                                                            if (confirm('Gutschein löschen? Bereits angewendete Rabatte bleiben bestehen.')) {
                                                                router.delete(route('admin.coupons.destroy', coupon.id));
                                                            }
                                                        }}
                                                    >
                                                        Löschen
                                                    </Button>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>
                        </>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
