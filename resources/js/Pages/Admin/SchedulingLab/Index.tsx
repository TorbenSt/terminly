import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { withSchedulingLabParam, isSchedulingLabPublicUrl, SCHEDULING_LAB_COMPLETE_EVENT } from '@/lib/scheduling-lab-return';
import { PageProps } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface ScenarioOption {
    value: string;
    label: string;
    description: string;
}

interface SandboxMessage {
    id: number;
    type: string;
    subject: string;
    body_html: string;
    meta: {
        public_url?: string;
        customer_name?: string;
        options?: { number: number; iso: string }[];
    } | null;
    created_at: string;
}

interface SandboxRun {
    id: number;
    mode: string;
    scenario: string | null;
    scenario_label: string | null;
    status: string;
    use_grok_live: boolean;
    snapshot_meta: Record<string, unknown> | null;
    grok_debug: Record<string, unknown> | null;
    validation_results: {
        proposal_id: number;
        customer: string;
        checks: { key: string; label: string; passed: boolean; detail: string }[];
    }[] | null;
    source_company: { id: number; name: string } | null;
    company: { id: number; name: string; snapshot_at: string | null };
    messages: SandboxMessage[];
}

interface Inspector {
    company: { id: number; name: string; is_sandbox: boolean; source_name?: string; snapshot_at?: string };
    counts: { staff: number; customers: number; due_services: number; confirmed_appointments: number };
    clusters: { region: string; jobs: number; suggested_date: string | null }[];
    staff: { id: number; name: string; services: string[] }[];
    customers: { id: number; name: string; postal_code: string; email: string }[];
}

interface CompanyOption {
    id: number;
    name: string;
    slug: string;
}

interface Props {
    enabled: boolean;
    run: SandboxRun | null;
    inspector: Inspector | null;
    scenarios: ScenarioOption[];
    companies: CompanyOption[];
}

export default function Index({
    enabled,
    run,
    inspector,
    scenarios,
    companies,
}: PageProps<Record<string, unknown>> & Props) {
    const { flash } = usePage().props as { flash?: { success?: string; error?: string } };
    const [mode, setMode] = useState<'scenario' | 'company'>('scenario');
    const [selectedMessage, setSelectedMessage] = useState<SandboxMessage | null>(run?.messages[0] ?? null);
    const [customerPreviewUrl, setCustomerPreviewUrl] = useState<string | null>(null);
    const emailBodyRef = useRef<HTMLDivElement>(null);

    const closeCustomerView = useCallback(() => {
        setCustomerPreviewUrl(null);
    }, []);

    const openCustomerView = useCallback((url: string) => {
        setSelectedMessage(null);
        setCustomerPreviewUrl(withSchedulingLabParam(url));
    }, []);

    useEffect(() => {
        const handleLabComplete = (event: MessageEvent) => {
            if (event.origin !== window.location.origin) {
                return;
            }

            if (event.data?.type !== SCHEDULING_LAB_COMPLETE_EVENT) {
                return;
            }

            closeCustomerView();
            setSelectedMessage(null);
            router.reload({ only: ['run', 'inspector'] });
        };

        window.addEventListener('message', handleLabComplete);

        return () => window.removeEventListener('message', handleLabComplete);
    }, [closeCustomerView]);

    useEffect(() => {
        const container = emailBodyRef.current;
        if (!container) {
            return;
        }

        const handleEmailLinkClick = (event: MouseEvent) => {
            const target = (event.target as HTMLElement).closest('a');
            if (!target?.href) {
                return;
            }

            if (!isSchedulingLabPublicUrl(target.href)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            openCustomerView(target.href);
        };

        container.addEventListener('click', handleEmailLinkClick);

        return () => container.removeEventListener('click', handleEmailLinkClick);
    }, [selectedMessage, openCustomerView]);

    const scenarioForm = useForm({
        scenario: scenarios[0]?.value ?? 'simple_maintenance',
        use_grok_live: true,
    });

    const snapshotForm = useForm({
        company_id: companies[0]?.id?.toString() ?? '',
        use_grok_live: true,
        mark_due_today: false,
        anonymize_emails: true,
    });

    const submitScenario = (e: React.FormEvent) => {
        e.preventDefault();
        scenarioForm.post(route('admin.scheduling-lab.scenario'));
    };

    const submitSnapshot = (e: React.FormEvent) => {
        e.preventDefault();
        snapshotForm.post(route('admin.scheduling-lab.snapshot'));
    };

    const selectMessage = (message: SandboxMessage) => {
        closeCustomerView();
        setSelectedMessage(message);
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Scheduling Lab</h2>}>
            <Head title="Scheduling Lab" />

            <div className="space-y-6">
                {!enabled && (
                    <Card className="border-amber-300 bg-amber-50">
                        <CardContent className="pt-6 text-sm text-amber-900">
                            Scheduling Lab ist deaktiviert. Setzen Sie <code>SCHEDULING_LAB_ENABLED=true</code> in der
                            .env.
                        </CardContent>
                    </Card>
                )}

                {run && (
                    <div className="rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">
                        Testlauf: <strong>{run.company.name}</strong>
                        {run.source_company && (
                            <>
                                {' '}
                                (Snapshot von <strong>{run.source_company.name}</strong>)
                            </>
                        )}
                        {run.company.snapshot_at && (
                            <> · Stand {new Date(run.company.snapshot_at).toLocaleString('de-DE')}</>
                        )}
                        <span className="ml-2 text-teal-700">— Produktivdaten werden nicht verändert.</span>
                    </div>
                )}

                {flash?.success && (
                    <p className="rounded-md bg-green-50 p-3 text-sm text-green-800">{flash.success}</p>
                )}
                {flash?.error && (
                    <p className="rounded-md bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-1">
                        <CardHeader>
                            <CardTitle>Setup</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant={mode === 'scenario' ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => setMode('scenario')}
                                >
                                    Szenario
                                </Button>
                                <Button
                                    type="button"
                                    variant={mode === 'company' ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => setMode('company')}
                                >
                                    Echte Firma
                                </Button>
                            </div>

                            {mode === 'scenario' ? (
                                <form onSubmit={submitScenario} className="space-y-3">
                                    <div>
                                        <Label>Szenario</Label>
                                        <select
                                            className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            value={scenarioForm.data.scenario}
                                            onChange={(e) => scenarioForm.setData('scenario', e.target.value)}
                                        >
                                            {scenarios.map((s) => (
                                                <option key={s.value} value={s.value}>
                                                    {s.label}
                                                </option>
                                            ))}
                                        </select>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {scenarios.find((s) => s.value === scenarioForm.data.scenario)?.description}
                                        </p>
                                    </div>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={scenarioForm.data.use_grok_live}
                                            onChange={(e) =>
                                                scenarioForm.setData('use_grok_live', e.target.checked)
                                            }
                                        />
                                        Grok live nutzen
                                    </label>
                                    <Button type="submit" disabled={scenarioForm.processing || !enabled}>
                                        Szenario aufsetzen
                                    </Button>
                                </form>
                            ) : (
                                <form onSubmit={submitSnapshot} className="space-y-3">
                                    <div>
                                        <Label>Firma</Label>
                                        <select
                                            className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            value={snapshotForm.data.company_id}
                                            onChange={(e) => snapshotForm.setData('company_id', e.target.value)}
                                        >
                                            {companies.map((c) => (
                                                <option key={c.id} value={c.id}>
                                                    {c.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={snapshotForm.data.use_grok_live}
                                            onChange={(e) =>
                                                snapshotForm.setData('use_grok_live', e.target.checked)
                                            }
                                        />
                                        Grok live nutzen
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={snapshotForm.data.mark_due_today}
                                            onChange={(e) =>
                                                snapshotForm.setData('mark_due_today', e.target.checked)
                                            }
                                        />
                                        Fällige Services auf heute setzen
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={snapshotForm.data.anonymize_emails}
                                            onChange={(e) =>
                                                snapshotForm.setData('anonymize_emails', e.target.checked)
                                            }
                                        />
                                        Kunden-E-Mails anonymisieren
                                    </label>
                                    <Button type="submit" disabled={snapshotForm.processing || !enabled}>
                                        Snapshot erstellen
                                    </Button>
                                </form>
                            )}

                            {run && (
                                <div className="flex flex-col gap-2 border-t pt-4">
                                    <Button
                                        onClick={() => router.post(route('admin.scheduling-lab.run'))}
                                        disabled={!enabled || run.status === 'running'}
                                    >
                                        KI-Planung starten
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={() => router.post(route('admin.scheduling-lab.reset'))}
                                    >
                                        Sandbox zurücksetzen
                                    </Button>
                                    <p className="text-xs text-muted-foreground">
                                        Status: <Badge variant="secondary">{run.status}</Badge>
                                        {!run.use_grok_live && ' · Fallback-Modus'}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-1">
                        <CardHeader>
                            <CardTitle>Test-Posteingang</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {!run?.messages.length && (
                                <p className="text-sm text-muted-foreground">
                                    Noch keine Nachrichten. Starten Sie die KI-Planung.
                                </p>
                            )}
                            {run?.messages.map((message) => (
                                <button
                                    key={message.id}
                                    type="button"
                                    onClick={() => selectMessage(message)}
                                    className={`w-full rounded-lg border p-3 text-left text-sm transition ${
                                        selectedMessage?.id === message.id
                                            ? 'border-primary bg-primary/5'
                                            : 'hover:bg-muted/50'
                                    }`}
                                >
                                    <p className="font-medium">{message.subject}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {message.meta?.customer_name ?? message.type} ·{' '}
                                        {new Date(message.created_at).toLocaleString('de-DE')}
                                    </p>
                                </button>
                            ))}

                            {selectedMessage && !customerPreviewUrl && (
                                <div className="space-y-3 border-t pt-3">
                                    <div
                                        ref={emailBodyRef}
                                        className="prose prose-sm max-w-none rounded-md border bg-white p-3 [&_a]:cursor-pointer"
                                        dangerouslySetInnerHTML={{ __html: selectedMessage.body_html }}
                                    />
                                    {selectedMessage.meta?.public_url && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="w-full"
                                            onClick={() =>
                                                openCustomerView(selectedMessage.meta!.public_url!)
                                            }
                                        >
                                            Als Kunde antworten
                                        </Button>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-1">
                        <CardHeader>
                            <CardTitle>Daten & Validierung</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            {inspector && (
                                <div className="space-y-2">
                                    <p>
                                        <strong>{inspector.counts.staff}</strong> MA ·{' '}
                                        <strong>{inspector.counts.customers}</strong> Kunden ·{' '}
                                        <strong>{inspector.counts.due_services}</strong> fällig ·{' '}
                                        <strong>{inspector.counts.confirmed_appointments}</strong> blockiert
                                    </p>
                                    {inspector.clusters.length > 0 && (
                                        <div>
                                            <p className="font-medium">PLZ-Cluster</p>
                                            <ul className="list-inside list-disc text-muted-foreground">
                                                {inspector.clusters.map((c) => (
                                                    <li key={c.region}>
                                                        {c.region}: {c.jobs} Job(s)
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                    {inspector.staff.length > 0 && (
                                        <div>
                                            <p className="font-medium">Mitarbeiter-Kalender</p>
                                            <ul className="space-y-2">
                                                {inspector.staff.map((member) => (
                                                    <li
                                                        key={member.id}
                                                        className="flex items-center justify-between gap-2 rounded-md border px-2 py-1.5"
                                                    >
                                                        <span>
                                                            {member.name}
                                                            {member.services.length > 0 && (
                                                                <span className="text-muted-foreground">
                                                                    {' '}
                                                                    · {member.services.join(', ')}
                                                                </span>
                                                            )}
                                                        </span>
                                                        <a
                                                            href={route(
                                                                'admin.scheduling-lab.staff-calendar',
                                                                member.id,
                                                            )}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="shrink-0 text-sm font-medium text-primary underline"
                                                        >
                                                            Kalender
                                                        </a>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                </div>
                            )}

                            {run?.validation_results?.map((result) => (
                                <div key={result.proposal_id} className="rounded-md border p-3">
                                    <p className="font-medium">{result.customer}</p>
                                    <ul className="mt-2 space-y-1">
                                        {result.checks.map((check) => (
                                            <li
                                                key={check.key}
                                                className={check.passed ? 'text-green-700' : 'text-red-700'}
                                            >
                                                {check.passed ? '✓' : '✗'} {check.label}: {check.detail}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}

                            {run?.grok_debug && (
                                <details className="rounded-md border p-2">
                                    <summary className="cursor-pointer font-medium">Grok Debug</summary>
                                    <pre className="mt-2 max-h-48 overflow-auto text-xs">
                                        {JSON.stringify(run.grok_debug, null, 2)}
                                    </pre>
                                </details>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {customerPreviewUrl && (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Kunden-Ansicht</CardTitle>
                            <Button variant="ghost" size="sm" onClick={closeCustomerView}>
                                Schließen
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <iframe
                                title="Kunden-Ansicht"
                                src={customerPreviewUrl}
                                className="h-[600px] w-full rounded-lg border bg-white"
                            />
                        </CardContent>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
