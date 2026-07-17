import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ServiceType } from '@/types/models';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Props {
    serviceTypes: ServiceType[];
}

function ServiceTypeFormFields({
    data,
    setData,
    idPrefix,
}: {
    data: {
        name: string;
        duration_minutes: number;
        is_recurring: boolean;
        interval_days: number | null;
        interval_months: number | null;
        completion_window_days: number;
        description: string;
        is_active?: boolean;
    };
    setData: (key: string, value: string | number | boolean | null) => void;
    idPrefix: string;
}) {
    return (
        <>
            <div>
                <Label htmlFor={`${idPrefix}-name`}>Name</Label>
                <Input
                    id={`${idPrefix}-name`}
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                />
            </div>
            <div>
                <Label htmlFor={`${idPrefix}-duration`}>Dauer (Min.)</Label>
                <Input
                    id={`${idPrefix}-duration`}
                    type="number"
                    min={15}
                    max={480}
                    value={data.duration_minutes}
                    onChange={(e) => setData('duration_minutes', Number(e.target.value))}
                    required
                />
            </div>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={data.is_recurring}
                    onChange={(e) => setData('is_recurring', e.target.checked)}
                />
                Wiederkehrend
            </label>
            {data.is_recurring && (
                <>
                    <div>
                        <Label htmlFor={`${idPrefix}-days`}>Intervall (Tage)</Label>
                        <Input
                            id={`${idPrefix}-days`}
                            type="number"
                            min={1}
                            value={data.interval_days ?? ''}
                            onChange={(e) =>
                                setData('interval_days', e.target.value ? Number(e.target.value) : null)
                            }
                        />
                    </div>
                    <div>
                        <Label htmlFor={`${idPrefix}-months`}>Intervall (Monate)</Label>
                        <Input
                            id={`${idPrefix}-months`}
                            type="number"
                            min={1}
                            value={data.interval_months ?? ''}
                            onChange={(e) =>
                                setData('interval_months', e.target.value ? Number(e.target.value) : null)
                            }
                        />
                    </div>
                </>
            )}
            <div>
                <Label htmlFor={`${idPrefix}-window`}>Erfüllungsfenster (Tage)</Label>
                <Input
                    id={`${idPrefix}-window`}
                    type="number"
                    min={1}
                    max={365}
                    value={data.completion_window_days}
                    onChange={(e) => setData('completion_window_days', Number(e.target.value))}
                    required
                />
                <p className="mt-1 text-xs text-muted-foreground">
                    Frist nach Fälligkeit für die Planung (z. B. 14).
                </p>
            </div>
            <div className="md:col-span-2">
                <Label htmlFor={`${idPrefix}-description`}>Beschreibung</Label>
                <Textarea
                    id={`${idPrefix}-description`}
                    rows={2}
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                />
            </div>
            {data.is_active !== undefined && (
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                    />
                    Aktiv
                </label>
            )}
        </>
    );
}

export default function Index({ serviceTypes }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string; error?: string } };
    const [editingId, setEditingId] = useState<number | null>(null);

    const createForm = useForm({
        name: '',
        duration_minutes: 60,
        is_recurring: false,
        interval_days: null as number | null,
        interval_months: null as number | null,
        completion_window_days: 14,
        description: '',
    });

    const editForm = useForm({
        name: '',
        duration_minutes: 60,
        is_recurring: false,
        interval_days: null as number | null,
        interval_months: null as number | null,
        completion_window_days: 14,
        description: '',
        is_active: true,
    });

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route('service-types.store'), { onSuccess: () => createForm.reset() });
    };

    const startEdit = (service: ServiceType) => {
        editForm.setData({
            name: service.name,
            duration_minutes: service.duration_minutes,
            is_recurring: service.is_recurring,
            interval_days: service.interval_days,
            interval_months: service.interval_months,
            completion_window_days: service.completion_window_days ?? 14,
            description: service.description ?? '',
            is_active: service.is_active,
        });
        setEditingId(service.id);
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();
        if (!editingId) return;
        editForm.patch(route('service-types.update', editingId), {
            onSuccess: () => setEditingId(null),
        });
    };

    const deleteService = (service: ServiceType) => {
        if (!confirm(`„${service.name}" wirklich löschen?`)) return;
        router.delete(route('service-types.destroy', service.id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Servicearten</h2>}>
            <Head title="Servicearten" />
            <div className="space-y-6">
                {flash?.success && (
                    <p className="rounded-md bg-green-50 p-3 text-sm text-green-800">{flash.success}</p>
                )}
                {flash?.error && (
                    <p className="rounded-md bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Neue Serviceart</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submitCreate} className="grid gap-3 md:grid-cols-2">
                            <ServiceTypeFormFields
                                data={createForm.data}
                                setData={createForm.setData}
                                idPrefix="create"
                            />
                            <div className="flex items-end md:col-span-2">
                                <Button type="submit" disabled={createForm.processing}>
                                    Speichern
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Servicearten ({serviceTypes.length})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="divide-y">
                            {serviceTypes.map((service) => (
                                <li key={service.id} className="py-4">
                                    {editingId === service.id ? (
                                        <form onSubmit={submitEdit} className="grid gap-3 md:grid-cols-2">
                                            <ServiceTypeFormFields
                                                data={editForm.data}
                                                setData={editForm.setData}
                                                idPrefix={`edit-${service.id}`}
                                            />
                                            <div className="flex gap-2 md:col-span-2">
                                                <Button type="submit" disabled={editForm.processing}>
                                                    Aktualisieren
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() => setEditingId(null)}
                                                >
                                                    Abbrechen
                                                </Button>
                                            </div>
                                        </form>
                                    ) : (
                                        <div className="flex items-start justify-between gap-4">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium">{service.name}</p>
                                                    {!service.is_active && (
                                                        <Badge variant="secondary">Inaktiv</Badge>
                                                    )}
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    {service.duration_minutes} Min.
                                                    {service.is_recurring && (
                                                        <>
                                                            {' '}
                                                            · wiederkehrend
                                                            {service.interval_days && ` (${service.interval_days} Tage)`}
                                                            {service.interval_months && ` (${service.interval_months} Mon.)`}
                                                        </>
                                                    )}
                                                    {' '}
                                                    · Fenster {service.completion_window_days ?? 14} Tage
                                                </p>
                                                {service.description && (
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        {service.description}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="flex shrink-0 gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => startEdit(service)}
                                                >
                                                    Bearbeiten
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() => deleteService(service)}
                                                >
                                                    Löschen
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </li>
                            ))}
                            {serviceTypes.length === 0 && (
                                <p className="py-3 text-sm text-muted-foreground">Noch keine Servicearten.</p>
                            )}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
