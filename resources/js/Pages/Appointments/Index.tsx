import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTimeDe } from '@/lib/datetime';
import { Head, router } from '@inertiajs/react';

interface AppointmentRow {
    id: number;
    status: string;
    scheduled_at: string | null;
    duration_minutes: number;
    customer: { name: string; postal_code: string };
    service_type: { name: string };
    staff_member: { name: string } | null;
}

interface Props {
    appointments: { data: AppointmentRow[] };
}

export default function Index({ appointments }: Props) {
    const triggerScheduling = () => {
        router.post(route('appointments.schedule'));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Termine</h2>}>
            <Head title="Termine" />
            <div className="space-y-6 py-8">
                <div className="flex justify-end">
                    <Button onClick={triggerScheduling}>KI-Planung starten</Button>
                </div>

                <Card>
                    <CardHeader><CardTitle>Terminübersicht</CardTitle></CardHeader>
                    <CardContent>
                        <ul className="divide-y">
                            {appointments.data.map((a) => (
                                <li key={a.id} className="flex items-center justify-between py-3">
                                    <div>
                                        <p className="font-medium">{a.customer.name} ({a.customer.postal_code})</p>
                                        <p className="text-sm text-muted-foreground">
                                            {a.service_type.name} · {a.staff_member?.name ?? '—'} · {a.scheduled_at ? formatDateTimeDe(a.scheduled_at) : 'offen'}
                                        </p>
                                    </div>
                                    <Badge variant="secondary">{a.status}</Badge>
                                </li>
                            ))}
                            {appointments.data.length === 0 && (
                                <p className="text-sm text-muted-foreground">Noch keine Termine. Starten Sie die KI-Planung.</p>
                            )}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
