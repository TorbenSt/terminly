import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateLongDe, formatTime24 } from '@/lib/datetime';
import { Head, router } from '@inertiajs/react';

interface Props {
    date: string;
    staffMember: { id: number; name: string };
    slots: { start: string; end: string }[];
    appointments: { id: number; customer: string; service: string; status: string; time: string | null }[];
}

export default function Calendar({ date, staffMember, slots, appointments }: Props) {
    const changeDate = (offset: number) => {
        const d = new Date(date);
        d.setDate(d.getDate() + offset);
        router.get(route('staff.calendar'), { date: d.toISOString().slice(0, 10) });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Mein Kalender</h2>}>
            <Head title="Kalender" />
            <div className="space-y-6 py-8">
                <div className="flex items-center gap-4">
                    <button type="button" onClick={() => changeDate(-1)} className="rounded border px-3 py-1">←</button>
                    <span className="font-medium">{staffMember.name} · {formatDateLongDe(date)}</span>
                    <button type="button" onClick={() => changeDate(1)} className="rounded border px-3 py-1">→</button>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader><CardTitle>Verfügbare Slots</CardTitle></CardHeader>
                        <CardContent>
                            <ul className="space-y-1 text-sm">
                                {slots.map((s, i) => (
                                    <li key={i} className="rounded bg-green-50 px-2 py-1">
                                        {formatTime24(s.start)} – {formatTime24(s.end)}
                                    </li>
                                ))}
                                {slots.length === 0 && <p className="text-muted-foreground">Keine freien Slots.</p>}
                            </ul>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Termine</CardTitle></CardHeader>
                        <CardContent>
                            <ul className="space-y-2">
                                {appointments.map((a) => (
                                    <li key={a.id} className="flex items-center justify-between rounded border p-2">
                                        <div>
                                            <p className="font-medium">
                                                {a.time ? formatTime24(a.time) : '—'} – {a.customer}
                                            </p>
                                            <p className="text-sm text-muted-foreground">{a.service}</p>
                                        </div>
                                        <Badge>{a.status}</Badge>
                                    </li>
                                ))}
                                {appointments.length === 0 && <p className="text-sm text-muted-foreground">Keine Termine.</p>}
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
