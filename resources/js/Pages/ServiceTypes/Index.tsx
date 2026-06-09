import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ServiceType } from '@/types/models';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Props {
    serviceTypes: ServiceType[];
}

export default function Index({ serviceTypes }: Props) {
    const { data, setData, post, processing, reset } = useForm({
        name: '',
        duration_minutes: 60,
        is_recurring: false,
        interval_days: null as number | null,
        interval_months: null as number | null,
        description: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('service-types.store'), { onSuccess: () => reset() });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Servicearten</h2>}>
            <Head title="Servicearten" />
            <div className="space-y-6 py-8">
                <Card>
                    <CardHeader><CardTitle>Neue Serviceart</CardTitle></CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-3 md:grid-cols-2">
                            <div><Label>Name</Label><Input value={data.name} onChange={(e) => setData('name', e.target.value)} required /></div>
                            <div><Label>Dauer (Min.)</Label><Input type="number" value={data.duration_minutes} onChange={(e) => setData('duration_minutes', Number(e.target.value))} /></div>
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={data.is_recurring} onChange={(e) => setData('is_recurring', e.target.checked)} />
                                Wiederkehrend
                            </label>
                            <div><Label>Intervall (Tage)</Label><Input type="number" value={data.interval_days ?? ''} onChange={(e) => setData('interval_days', e.target.value ? Number(e.target.value) : null)} /></div>
                            <div className="flex items-end"><Button type="submit" disabled={processing}>Speichern</Button></div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Servicearten</CardTitle></CardHeader>
                    <CardContent>
                        <ul className="divide-y">
                            {serviceTypes.map((s) => (
                                <li key={s.id} className="flex justify-between py-3">
                                    <div>
                                        <p className="font-medium">{s.name}</p>
                                        <p className="text-sm text-muted-foreground">{s.duration_minutes} Min. {s.is_recurring ? '· wiederkehrend' : ''}</p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
