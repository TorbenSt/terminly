import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CustomerRecurringService, ServiceType } from '@/types/models';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Props {
    customerId: number;
    services: CustomerRecurringService[];
    serviceTypes: Pick<ServiceType, 'id' | 'name' | 'duration_minutes' | 'is_recurring'>[];
    canAssign: boolean;
}

export default function CustomerServiceAssignments({
    customerId,
    services,
    serviceTypes,
    canAssign,
}: Props) {
    const [editingServiceId, setEditingServiceId] = useState<number | null>(null);

    const assignForm = useForm({
        service_type_id: '',
        next_due_at: new Date().toISOString().slice(0, 10),
    });

    const editForm = useForm({
        next_due_at: '',
        is_active: true,
    });

    const assignedIds = new Set(services.map((s) => s.service_type_id));
    const availableTypes = serviceTypes.filter((t) => !assignedIds.has(t.id));

    const submitAssign = (e: FormEvent) => {
        e.preventDefault();
        assignForm.post(route('customers.recurring-services.store', customerId), {
            onSuccess: () => {
                assignForm.reset();
                assignForm.setData('next_due_at', new Date().toISOString().slice(0, 10));
            },
        });
    };

    const startEditService = (service: CustomerRecurringService) => {
        editForm.setData({
            next_due_at: service.next_due_at,
            is_active: service.is_active,
        });
        setEditingServiceId(service.id);
    };

    const submitEditService = (e: FormEvent) => {
        e.preventDefault();
        if (!editingServiceId) return;
        editForm.patch(
            route('customers.recurring-services.update', [customerId, editingServiceId]),
            { onSuccess: () => setEditingServiceId(null) },
        );
    };

    const removeService = (service: CustomerRecurringService) => {
        if (!confirm(`Zuweisung „${service.service_name}" entfernen?`)) return;
        router.delete(route('customers.recurring-services.destroy', [customerId, service.id]));
    };

    return (
        <div className="mt-3 rounded-lg border bg-muted/30 p-3">
            <p className="mb-2 text-sm font-medium">Zugewiesene Services</p>

            {services.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Keine Services zugewiesen – Planung nicht möglich.
                </p>
            ) : (
                <ul className="space-y-2">
                    {services.map((service) => (
                        <li key={service.id} className="rounded border bg-background p-2 text-sm">
                            {editingServiceId === service.id ? (
                                <form onSubmit={submitEditService} className="flex flex-wrap items-end gap-2">
                                    <div>
                                        <Label className="text-xs">Nächster Termin / Fälligkeit</Label>
                                        <Input
                                            type="date"
                                            value={editForm.data.next_due_at}
                                            onChange={(e) => editForm.setData('next_due_at', e.target.value)}
                                            required
                                        />
                                    </div>
                                    <label className="flex items-center gap-1 text-xs">
                                        <input
                                            type="checkbox"
                                            checked={editForm.data.is_active}
                                            onChange={(e) => editForm.setData('is_active', e.target.checked)}
                                        />
                                        Aktiv
                                    </label>
                                    <Button type="submit" size="sm" disabled={editForm.processing}>
                                        Speichern
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setEditingServiceId(null)}
                                    >
                                        Abbrechen
                                    </Button>
                                </form>
                            ) : (
                                <div className="flex items-center justify-between gap-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">{service.service_name}</span>
                                        <span className="text-muted-foreground">
                                            {service.duration_minutes} Min.
                                        </span>
                                        {!service.is_recurring && (
                                            <Badge variant="outline">Einmalig</Badge>
                                        )}
                                        {service.is_due && service.is_active && (
                                            <Badge>Fällig</Badge>
                                        )}
                                        {!service.is_active && (
                                            <Badge variant="secondary">Inaktiv</Badge>
                                        )}
                                        <span className="text-muted-foreground">
                                            Fällig: {new Date(service.next_due_at).toLocaleDateString('de-DE')}
                                        </span>
                                    </div>
                                    {canAssign && (
                                        <div className="flex gap-1">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => startEditService(service)}
                                            >
                                                Bearbeiten
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="destructive"
                                                onClick={() => removeService(service)}
                                            >
                                                Entfernen
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {canAssign && availableTypes.length > 0 && (
                <form onSubmit={submitAssign} className="mt-3 flex flex-wrap items-end gap-2 border-t pt-3">
                    <div>
                        <Label className="text-xs">Serviceart</Label>
                        <select
                            className="flex h-10 rounded-md border border-input bg-background px-3 text-sm"
                            value={assignForm.data.service_type_id}
                            onChange={(e) => assignForm.setData('service_type_id', e.target.value)}
                            required
                        >
                            <option value="">Auswählen…</option>
                            {availableTypes.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.name} ({type.duration_minutes} Min.
                                    {type.is_recurring ? ', wiederkehrend' : ', einmalig'})
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <Label className="text-xs">Fällig am</Label>
                        <Input
                            type="date"
                            value={assignForm.data.next_due_at}
                            onChange={(e) => assignForm.setData('next_due_at', e.target.value)}
                            required
                        />
                    </div>
                    <Button type="submit" size="sm" disabled={assignForm.processing}>
                        Zuweisen
                    </Button>
                </form>
            )}
        </div>
    );
}
