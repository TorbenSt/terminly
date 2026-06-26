import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatCents } from '@/lib/billing';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

interface Prospect {
    id: number;
    company_name: string;
    email: string | null;
    phone: string | null;
    postal_code: string | null;
    city: string | null;
    industry: string | null;
    match_score: number | null;
    match_reason: string | null;
    status: string;
    source_url: string | null;
}

interface Profile {
    id: number;
    name: string;
    industries: string[];
    ai_instructions: string | null;
    data_source: string;
    postal_code: string;
    radius_km: number;
    max_results_per_run: number;
    exclude_existing_customers: boolean;
    is_active: boolean;
    schedule_enabled: boolean;
    last_run_at: string | null;
}

interface Props {
    hasAccess: boolean;
    prospects: { data: Prospect[]; links: unknown[] } | [];
    profiles: Profile[];
    recentRuns: Array<{
        id: number;
        status: string;
        data_source?: string | null;
        candidates_found?: number;
        prospects_saved: number;
        duplicates_skipped: number;
        error_message?: string | null;
        profile?: { name: string };
    }>;
    stats: { new_count: number; contacted_count: number; converted_count: number } | null;
    addon: { price_cents: number; plan_includes: boolean; has_addon: boolean };
    filters: { status: string | null };
    activeRun: { id: number; status: string; data_source?: string | null; error_message?: string | null } | null;
    outreach: { daily_limit: number; sent_today: number; remaining_today: number } | null;
    dataSources: Array<{ value: string; label: string; configured: boolean }>;
}

const emptyProfile = {
    name: '',
    industries: '',
    ai_instructions: '',
    data_source: 'google_places',
    postal_code: '',
    radius_km: 10,
    max_results_per_run: 25,
    exclude_existing_customers: true,
    is_active: true,
    schedule_enabled: false,
};

const statusLabels: Record<string, string> = {
    new: 'Neu',
    contacted: 'Kontaktiert',
    replied: 'Geantwortet',
    interested: 'Interessiert',
    rejected: 'Abgelehnt',
    opted_out: 'Abgemeldet',
    converted: 'Übernommen',
    discarded: 'Verworfen',
};

const sourceLabels: Record<string, string> = {
    google_places: 'Google Places',
    apify: 'Apify',
};

export default function Index({ hasAccess, prospects, profiles, recentRuns, stats, addon, filters, activeRun, outreach, dataSources }: Props) {
    const [showProfileForm, setShowProfileForm] = useState(false);
    const [outreachId, setOutreachId] = useState<number | null>(null);
    const [statusEditId, setStatusEditId] = useState<number | null>(null);
    const [statusValue, setStatusValue] = useState('rejected');
    const [feedbackReason, setFeedbackReason] = useState('');
    const profileForm = useForm(emptyProfile);
    const outreachForm = useForm({ subject: '', body: '' });

    const prospectList = Array.isArray(prospects) ? [] : prospects.data ?? [];

    useEffect(() => {
        if (!activeRun) return;
        const interval = window.setInterval(() => {
            router.reload({ only: ['activeRun', 'prospects', 'recentRuns', 'stats'] });
        }, 5000);
        return () => window.clearInterval(interval);
    }, [activeRun?.id, activeRun?.status]);

    const submitProfile = (e: FormEvent) => {
        e.preventDefault();
        profileForm.transform((data) => ({
            ...data,
            industries: String(data.industries)
                .split(',')
                .map((s) => s.trim())
                .filter(Boolean),
        }));
        profileForm.post(route('prospects.profiles.store'), {
            onSuccess: () => {
                profileForm.reset();
                setShowProfileForm(false);
            },
        });
    };

    const updateStatus = (prospectId: number) => {
        router.patch(route('prospects.update', prospectId), {
            status: statusValue,
            feedback_reason: feedbackReason || null,
        }, {
            onSuccess: () => {
                setStatusEditId(null);
                setFeedbackReason('');
            },
        });
    };

    const sendOutreach = (e: FormEvent) => {
        e.preventDefault();
        if (!outreachId) return;
        outreachForm.post(route('prospects.outreach', outreachId), {
            onSuccess: () => {
                outreachForm.reset();
                setOutreachId(null);
            },
        });
    };

    if (!hasAccess) {
        return (
            <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Kundensuche</h2>}>
                <Head title="Kundensuche" />
                <div className="py-8">
                    <Card className="mx-auto max-w-2xl">
                        <CardHeader>
                            <CardTitle>Kundensuche freischalten</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-muted-foreground">
                                Finden Sie potenzielle B2B-Kunden per Google Places in Ihrer Region. Die KI bewertet Treffer
                                nach Ihren Branchen und Hinweisen. Bestehende Kunden werden automatisch ausgeschlossen.
                            </p>
                            <ul className="list-inside list-disc text-sm text-muted-foreground">
                                <li>Suche per PLZ und Radius</li>
                                <li>Konfigurierbare Ergebnisanzahl pro Lauf</li>
                                <li>Kaltakquise-E-Mails mit Opt-out</li>
                                <li>KI lernt aus Ihrem Feedback</li>
                            </ul>
                            {!addon.plan_includes && (
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        onClick={() => router.post(route('billing.prospect-addon'))}
                                    >
                                        Add-on buchen ({formatCents(addon.price_cents)}/Monat)
                                    </Button>
                                    <Link href={route('billing.index')}>
                                        <Button variant="outline">Abo upgraden</Button>
                                    </Link>
                                </div>
                            )}
                            {addon.plan_includes && (
                                <p className="text-sm text-amber-700">Ihr Plan enthält Kundensuche — bitte kontaktieren Sie den Support.</p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Kundensuche</h2>}>
            <Head title="Kundensuche" />

            <div className="space-y-6 py-8">
                {stats && (
                    <div className="grid gap-4 md:grid-cols-3">
                        <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">Neu</p><p className="text-2xl font-bold">{stats.new_count}</p></CardContent></Card>
                        <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">Kontaktiert</p><p className="text-2xl font-bold">{stats.contacted_count}</p></CardContent></Card>
                        <Card><CardContent className="pt-6"><p className="text-sm text-muted-foreground">Übernommen</p><p className="text-2xl font-bold">{stats.converted_count}</p></CardContent></Card>
                    </div>
                )}

                {activeRun && (
                    <div className="rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
                        {activeRun.status === 'pending' && 'Suche wird vorbereitet…'}
                        {activeRun.status === 'running' && (
                            activeRun.data_source === 'apify'
                                ? 'Apify-Scraper läuft — kann 2–6 Minuten dauern. Bitte Seite offen lassen.'
                                : 'Suche läuft…'
                        )}
                    </div>
                )}

                {outreach && (
                    <p className="text-sm text-muted-foreground px-1">
                        Kaltakquise heute: {outreach.sent_today} / {outreach.daily_limit} E-Mails
                        {outreach.remaining_today === 0 && ' — Tageslimit erreicht'}
                    </p>
                )}

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Suchprofile</CardTitle>
                        <Button size="sm" onClick={() => setShowProfileForm(!showProfileForm)}>
                            {showProfileForm ? 'Abbrechen' : 'Neues Profil'}
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {showProfileForm && (
                            <form onSubmit={submitProfile} className="grid gap-3 md:grid-cols-2 rounded-md border p-4">
                                <div><Label>Name</Label><Input value={profileForm.data.name} onChange={(e) => profileForm.setData('name', e.target.value)} required /></div>
                                <div><Label>PLZ</Label><Input value={profileForm.data.postal_code} onChange={(e) => profileForm.setData('postal_code', e.target.value)} required /></div>
                                <div className="md:col-span-2">
                                    <Label>Datenquelle</Label>
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {dataSources.map((source) => (
                                            <Button
                                                key={source.value}
                                                type="button"
                                                size="sm"
                                                variant={profileForm.data.data_source === source.value ? 'default' : 'outline'}
                                                disabled={!source.configured}
                                                onClick={() => profileForm.setData('data_source', source.value)}
                                            >
                                                {source.label}
                                                {!source.configured && ' (nicht konfiguriert)'}
                                            </Button>
                                        ))}
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Google Places: schnell per API. Apify: Google-Maps-Scraper, dauert länger, oft mehr Kontaktdaten.
                                    </p>
                                </div>
                                <div><Label>Branchen (kommagetrennt)</Label><Input value={profileForm.data.industries} onChange={(e) => profileForm.setData('industries', e.target.value)} placeholder="Heizung, Sanitär" required /></div>
                                <div><Label>Radius (km)</Label><Input type="number" min={1} max={100} value={profileForm.data.radius_km} onChange={(e) => profileForm.setData('radius_km', Number(e.target.value))} required /></div>
                                <div><Label>Max. Ergebnisse pro Lauf</Label><Input type="number" min={1} max={100} value={profileForm.data.max_results_per_run} onChange={(e) => profileForm.setData('max_results_per_run', Number(e.target.value))} required /></div>
                                <div className="md:col-span-2"><Label>Besonderheiten für die KI</Label><Textarea value={profileForm.data.ai_instructions} onChange={(e) => profileForm.setData('ai_instructions', e.target.value)} rows={2} /></div>
                                <label className="flex items-center gap-2 text-sm md:col-span-2"><input type="checkbox" checked={profileForm.data.exclude_existing_customers} onChange={(e) => profileForm.setData('exclude_existing_customers', e.target.checked)} /> Bestehende Kunden ausschließen</label>
                                <div className="md:col-span-2"><Button type="submit" disabled={profileForm.processing}>Profil speichern</Button></div>
                            </form>
                        )}

                        {profiles.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Noch kein Suchprofil angelegt.</p>
                        ) : (
                            <ul className="divide-y">
                                {profiles.map((profile) => (
                                    <li key={profile.id} className="flex flex-wrap items-center justify-between gap-2 py-3">
                                        <div>
                                            <p className="font-medium">{profile.name}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {profile.industries.join(', ')} · PLZ {profile.postal_code} · {profile.radius_km} km · max. {profile.max_results_per_run} Ergebnisse · {sourceLabels[profile.data_source] ?? profile.data_source}
                                            </p>
                                        </div>
                                        <Button size="sm" onClick={() => router.post(route('prospects.profiles.run', profile.id))}>
                                            Suche starten
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Potenzielle Kunden</CardTitle></CardHeader>
                    <CardContent>
                        {prospectList.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Noch keine Prospects. Starten Sie eine Suche.</p>
                        ) : (
                            <ul className="divide-y">
                                {prospectList.map((prospect) => (
                                    <li key={prospect.id} className="py-4 space-y-2">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium">{prospect.company_name}</p>
                                                    <Badge variant="secondary">{statusLabels[prospect.status] ?? prospect.status}</Badge>
                                                    {prospect.match_score != null && <Badge>Score {prospect.match_score}</Badge>}
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    {[prospect.postal_code, prospect.city].filter(Boolean).join(' ')}
                                                    {prospect.industry ? ` · ${prospect.industry}` : ''}
                                                </p>
                                                {prospect.match_reason && <p className="text-xs text-muted-foreground">{prospect.match_reason}</p>}
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                {prospect.status !== 'converted' && prospect.status !== 'opted_out' && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => {
                                                            setStatusEditId(statusEditId === prospect.id ? null : prospect.id);
                                                            setStatusValue('rejected');
                                                        }}
                                                    >
                                                        Status
                                                    </Button>
                                                )}
                                                {prospect.email && prospect.status !== 'converted' && (
                                                    <Button size="sm" variant="outline" onClick={() => setOutreachId(prospect.id)}>E-Mail</Button>
                                                )}
                                                {prospect.status !== 'converted' && (
                                                    <Button size="sm" onClick={() => router.post(route('prospects.convert', prospect.id), {
                                                        address: prospect.city ? `Adresse ${prospect.company_name}` : '—',
                                                        postal_code: prospect.postal_code ?? '00000',
                                                        city: prospect.city ?? 'Unbekannt',
                                                    })}>Als Kunde</Button>
                                                )}
                                                {prospect.source_url && (
                                                    <a href={prospect.source_url} target="_blank" rel="noreferrer" className="text-sm text-primary underline">Quelle</a>
                                                )}
                                            </div>
                                        </div>
                                        {statusEditId === prospect.id && (
                                            <div className="rounded border p-3 space-y-2">
                                                <select
                                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                                    value={statusValue}
                                                    onChange={(e) => setStatusValue(e.target.value)}
                                                >
                                                    <option value="interested">Interessiert</option>
                                                    <option value="rejected">Abgelehnt</option>
                                                    <option value="discarded">Verworfen</option>
                                                    <option value="contacted">Kontaktiert</option>
                                                </select>
                                                <Input
                                                    placeholder="Grund (optional, für KI-Feedback)"
                                                    value={feedbackReason}
                                                    onChange={(e) => setFeedbackReason(e.target.value)}
                                                />
                                                <div className="flex gap-2">
                                                    <Button type="button" size="sm" onClick={() => updateStatus(prospect.id)}>Speichern</Button>
                                                    <Button type="button" size="sm" variant="outline" onClick={() => setStatusEditId(null)}>Abbrechen</Button>
                                                </div>
                                            </div>
                                        )}
                                        {outreachId === prospect.id && (
                                            <form onSubmit={sendOutreach} className="rounded border p-3 space-y-2">
                                                <Input placeholder="Betreff" value={outreachForm.data.subject} onChange={(e) => outreachForm.setData('subject', e.target.value)} required />
                                                <Textarea placeholder="Nachricht" value={outreachForm.data.body} onChange={(e) => outreachForm.setData('body', e.target.value)} rows={4} required />
                                                <div className="flex gap-2">
                                                    <Button type="submit" size="sm" disabled={outreachForm.processing}>Senden</Button>
                                                    <Button type="button" size="sm" variant="outline" onClick={() => setOutreachId(null)}>Abbrechen</Button>
                                                </div>
                                            </form>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {recentRuns.length > 0 && (
                    <Card>
                        <CardHeader><CardTitle>Letzte Suchläufe</CardTitle></CardHeader>
                        <CardContent>
                            <ul className="divide-y text-sm">
                                {recentRuns.map((run) => (
                                    <li key={run.id} className="py-2 space-y-1">
                                        <div className="flex justify-between gap-4">
                                            <span>
                                                {run.profile?.name ?? 'Profil'} — {run.status}
                                                {run.data_source ? ` (${sourceLabels[run.data_source] ?? run.data_source})` : ''}
                                            </span>
                                            <span>
                                                {run.prospects_saved} gespeichert
                                                {run.candidates_found != null ? `, ${run.candidates_found} Kandidaten` : ''}
                                                {run.duplicates_skipped > 0 ? `, ${run.duplicates_skipped} Duplikate` : ''}
                                            </span>
                                        </div>
                                        {run.error_message && (
                                            <p className="text-xs text-red-600">{run.error_message}</p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
