import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { compareWeekdaysMondayFirst, DAY_LABELS_SHORT, formatTime24 } from '@/lib/datetime';
import { ServiceType, StaffMember } from '@/types/models';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Props {
    staffMembers: StaffMember[];
    serviceTypes: Pick<ServiceType, 'id' | 'name'>[];
}

export default function Index({ staffMembers, serviceTypes }: Props) {
    const { data, setData, post, processing, reset } = useForm({
        name: '',
        email: '',
        phone: '',
        buffer_minutes: 15,
        service_type_ids: [] as number[],
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('staff.store'), { onSuccess: () => reset() });
    };

    const toggleService = (id: number) => {
        setData(
            'service_type_ids',
            data.service_type_ids.includes(id)
                ? data.service_type_ids.filter((x) => x !== id)
                : [...data.service_type_ids, id],
        );
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Mitarbeiter</h2>}>
            <Head title="Mitarbeiter" />
            <div className="space-y-6 py-8">
                <Card>
                    <CardHeader><CardTitle>Neuer Mitarbeiter</CardTitle></CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-3">
                            <div className="grid gap-3 md:grid-cols-2">
                                <div><Label>Name</Label><Input value={data.name} onChange={(e) => setData('name', e.target.value)} required /></div>
                                <div><Label>E-Mail</Label><Input value={data.email} onChange={(e) => setData('email', e.target.value)} /></div>
                            </div>
                            <div>
                                <Label>Qualifikationen</Label>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {serviceTypes.map((s) => (
                                        <label key={s.id} className="flex items-center gap-1 rounded border px-2 py-1 text-sm">
                                            <input type="checkbox" checked={data.service_type_ids.includes(s.id)} onChange={() => toggleService(s.id)} />
                                            {s.name}
                                        </label>
                                    ))}
                                </div>
                            </div>
                            <Button type="submit" disabled={processing}>Anlegen</Button>
                        </form>
                    </CardContent>
                </Card>

                {staffMembers.map((staff) => (
                    <Card key={staff.id}>
                        <CardHeader>
                            <CardTitle>{staff.name}</CardTitle>
                            <p className="text-sm text-muted-foreground">
                                Puffer: {staff.buffer_minutes} Min. · Qualifikationen: {staff.service_types?.map((s) => s.name).join(', ') || '—'}
                            </p>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-2 text-sm font-medium">Wochenverfügbarkeit</p>
                            <div className="grid grid-cols-2 gap-2 text-sm md:grid-cols-4">
                                {[...(staff.availabilities ?? [])]
                                    .sort((a, b) => compareWeekdaysMondayFirst(a.day_of_week, b.day_of_week))
                                    .map((a) => (
                                    <div key={a.id} className="rounded border p-2">
                                        <span className="font-medium">{DAY_LABELS_SHORT[a.day_of_week]}</span>
                                        <p>
                                            {formatTime24(String(a.start_time))} – {formatTime24(String(a.end_time))}
                                            {a.break_start_time && a.break_end_time && (
                                                <>
                                                    <br />
                                                    Pause: {formatTime24(String(a.break_start_time))} –{' '}
                                                    {formatTime24(String(a.break_end_time))}
                                                </>
                                            )}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
