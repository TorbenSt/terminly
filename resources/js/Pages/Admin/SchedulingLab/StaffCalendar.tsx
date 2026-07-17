import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import YearCalendar from '@/components/YearCalendar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateLongDe, formatTime24 } from '@/lib/datetime';
import { Head, Link, router } from '@inertiajs/react';

function parseDateLocal(value: string): Date {
    return new Date(`${value}T12:00:00`);
}

function toDateString(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

interface CalendarEntry {
    id: number | string;
    postal_code: string;
    service: string;
    status: string;
    status_label: string;
    time: string | null;
    option?: number;
    round?: number;
}

interface Props {
    date: string;
    appointmentDates: string[];
    run: {
        company_name: string;
        scenario_label: string | null;
    };
    staffMember: {
        id: number;
        name: string;
        services: string[];
        stamm_customers: { id: number; name: string }[];
    };
    slots: { start: string; end: string }[];
    appointments: CalendarEntry[];
    proposalOptions: CalendarEntry[];
}

export default function StaffCalendar({
    date,
    appointmentDates,
    run,
    staffMember,
    slots,
    appointments,
    proposalOptions,
}: Props) {
    const calendarUrl = route('admin.scheduling-lab.staff-calendar', staffMember.id);

    const selectDate = (nextDate: string) => {
        router.get(calendarUrl, { date: nextDate }, { preserveScroll: true });
    };

    const changeDate = (offset: number) => {
        const d = parseDateLocal(date);
        d.setDate(d.getDate() + offset);
        selectDate(toDateString(d));
    };

    const entries = [...appointments, ...proposalOptions].sort((a, b) =>
        (a.time ?? '').localeCompare(b.time ?? ''),
    );

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold">Sandbox-Kalender · {staffMember.name}</h2>
                    <p className="text-sm text-muted-foreground">
                        {run.company_name}
                        {run.scenario_label ? ` · ${run.scenario_label}` : ''}
                    </p>
                </div>
            }
        >
            <Head title={`Kalender ${staffMember.name}`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center gap-4">
                    <button type="button" onClick={() => changeDate(-1)} className="rounded border px-3 py-1">
                        ←
                    </button>
                    <span className="font-medium">{formatDateLongDe(date)}</span>
                    <button type="button" onClick={() => changeDate(1)} className="rounded border px-3 py-1">
                        →
                    </button>
                    <Link
                        href={route('admin.scheduling-lab.index')}
                        className="text-sm text-primary underline"
                    >
                        Zurück zum Scheduling Lab
                    </Link>
                </div>

                <div className="space-y-1 text-sm">
                    {(staffMember.services?.length ?? 0) > 0 && (
                        <p>
                            <span className="text-muted-foreground">Quali:</span>{' '}
                            {staffMember.services.join(', ')}
                        </p>
                    )}
                    {(staffMember.stamm_customers?.length ?? 0) > 0 && (
                        <p>
                            <span className="text-muted-foreground">Stammkunden:</span>{' '}
                            {staffMember.stamm_customers.map((c) => c.name).join(', ')}
                        </p>
                    )}
                </div>

                <YearCalendar
                    selectedDate={date}
                    onSelectDate={selectDate}
                    appointmentDates={appointmentDates}
                />

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Verfügbare Slots</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-1 text-sm">
                                {slots.map((slot, index) => (
                                    <li key={index} className="rounded bg-green-50 px-2 py-1">
                                        {formatTime24(slot.start)} – {formatTime24(slot.end)}
                                    </li>
                                ))}
                                {slots.length === 0 && (
                                    <p className="text-muted-foreground">Keine freien Slots an diesem Tag.</p>
                                )}
                            </ul>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Termine & Vorschläge (PLZ)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-2">
                                {entries.map((entry) => (
                                    <li
                                        key={entry.id}
                                        className={`flex items-center justify-between rounded border p-2 ${
                                            entry.status === 'proposal_option' ? 'border-dashed bg-amber-50/50' : ''
                                        }`}
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {entry.time ? formatTime24(entry.time) : '—'} · PLZ{' '}
                                                {entry.postal_code}
                                            </p>
                                            <p className="text-sm text-muted-foreground">{entry.service}</p>
                                            {entry.round && entry.round > 1 && (
                                                <p className="text-xs text-muted-foreground">
                                                    Verhandlungsrunde {entry.round}
                                                </p>
                                            )}
                                        </div>
                                        <Badge variant={entry.status === 'proposal_option' ? 'outline' : 'secondary'}>
                                            {entry.status_label}
                                        </Badge>
                                    </li>
                                ))}
                                {entries.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Keine Termine oder offenen Vorschläge an diesem Tag.
                                    </p>
                                )}
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
