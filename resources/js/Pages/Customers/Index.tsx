import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Customer } from '@/types/models';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Paginated<T> {
    data: T[];
}

interface Props {
    customers: Paginated<Customer>;
}

export default function Index({ customers }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        phone: '',
        address: '',
        postal_code: '',
        city: '',
        notes: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('customers.store'), { onSuccess: () => reset() });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Kunden</h2>}>
            <Head title="Kunden" />
            <div className="space-y-6 py-8">
                <Card>
                    <CardHeader><CardTitle>Neuer Kunde</CardTitle></CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-3 md:grid-cols-2">
                            <div><Label>Name</Label><Input value={data.name} onChange={(e) => setData('name', e.target.value)} required /></div>
                            <div><Label>E-Mail</Label><Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} /></div>
                            <div><Label>Telefon</Label><Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} /></div>
                            <div><Label>PLZ</Label><Input value={data.postal_code} onChange={(e) => setData('postal_code', e.target.value)} required /></div>
                            <div className="md:col-span-2"><Label>Adresse</Label><Input value={data.address} onChange={(e) => setData('address', e.target.value)} required /></div>
                            <div><Label>Stadt</Label><Input value={data.city} onChange={(e) => setData('city', e.target.value)} required /></div>
                            <div className="flex items-end"><Button type="submit" disabled={processing}>Speichern</Button></div>
                            {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Kundenliste ({customers.data.length})</CardTitle></CardHeader>
                    <CardContent>
                        <ul className="divide-y">
                            {customers.data.map((c) => (
                                <li key={c.id} className="py-3">
                                    <p className="font-medium">{c.name}</p>
                                    <p className="text-sm text-muted-foreground">{c.postal_code} {c.city} · {c.email}</p>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
