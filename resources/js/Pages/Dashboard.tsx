import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AppointmentListItem } from '@/types/models';
import { Head, Link } from '@inertiajs/react';

interface Props {
    stats: {
        mode?: string;
        due_recurring?: number;
        open_negotiations?: number;
        confirmed_today?: number;
        active_staff?: number;
        prospect_search_enabled?: boolean;
        new_prospects?: number;
    };
    recentAppointments?: AppointmentListItem[];
}

export default function Dashboard({ stats, recentAppointments = [] }: Props) {
    if (stats.mode === 'super_admin') {
        return (
            <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Super Admin</h2>}>
                <Head title="Dashboard" />
                <div className="space-y-6">
                    <Card className="mx-auto max-w-xl">
                        <CardContent className="pt-6">
                            <p className="mb-4 text-muted-foreground">Als Super-Admin verwalten Sie Unternehmen.</p>
                            <Link href={route('admin.companies.index')} className="text-primary underline">
                                Unternehmen verwalten →
                            </Link>
                        </CardContent>
                    </Card>
                </div>
            </AuthenticatedLayout>
        );
    }

    const cards = [
        { label: 'Fällige Wartungen', value: stats.due_recurring ?? 0 },
        { label: 'Offene Verhandlungen', value: stats.open_negotiations ?? 0 },
        { label: 'Termine heute', value: stats.confirmed_today ?? 0 },
        { label: 'Aktive Mitarbeiter', value: stats.active_staff ?? 0 },
    ];

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Dashboard</h2>}>
            <Head title="Dashboard" />
            <div className="space-y-6">
                <div className="grid gap-4 md:grid-cols-4">
                    {cards.map((card) => (
                        <Card key={card.label}>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">{card.label}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-3xl font-bold">{card.value}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {stats.prospect_search_enabled && (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Kundensuche</CardTitle>
                            <Link href={route('prospects.index')} className="text-sm text-primary underline">
                                Öffnen →
                            </Link>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{stats.new_prospects ?? 0}</p>
                            <p className="text-sm text-muted-foreground">neue potenzielle Kunden</p>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Aktuelle Termine</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="divide-y">
                            {recentAppointments.map((a) => (
                                <li key={a.id} className="flex items-center justify-between py-3">
                                    <div>
                                        <p className="font-medium">{a.customer}</p>
                                        <p className="text-sm text-muted-foreground">{a.service}</p>
                                    </div>
                                    <Badge variant="secondary">{a.status}</Badge>
                                </li>
                            ))}
                            {recentAppointments.length === 0 && (
                                <p className="text-sm text-muted-foreground">Noch keine Termine.</p>
                            )}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
