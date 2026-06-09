import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Company {
    id: number;
    name: string;
    slug: string;
    email: string | null;
    phone: string | null;
    timezone: string;
    is_active: boolean;
}

interface Props {
    companies: Company[];
}

export default function Index({ companies }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        phone: '',
        timezone: 'Europe/Berlin',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('admin.companies.store'), {
            onSuccess: () => reset(),
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Unternehmen</h2>}
        >
            <Head title="Unternehmen" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <Card>
                        <CardHeader>
                            <CardTitle>Neues Unternehmen</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        required
                                    />
                                    {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
                                </div>
                                <div>
                                    <Label htmlFor="email">E-Mail</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="phone">Telefon</Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
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
                            <CardTitle>Alle Unternehmen</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y">
                                {companies.map((company) => (
                                    <li key={company.id} className="flex justify-between py-3">
                                        <div>
                                            <p className="font-medium">{company.name}</p>
                                            <p className="text-sm text-muted-foreground">{company.slug}</p>
                                        </div>
                                        <span className="text-sm text-muted-foreground">{company.timezone}</span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
