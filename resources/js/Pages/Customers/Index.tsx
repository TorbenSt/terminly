import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import BillingOverageWarning from '@/components/BillingOverageWarning';
import CustomerServiceAssignments from '@/components/CustomerServiceAssignments';
import { cn } from '@/lib/utils';
import { Customer, ServiceType } from '@/types/models';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ChevronDown, Search, X } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

interface Paginated<T> {
    data: T[];
}

interface Props {
    customers: Paginated<Customer>;
    serviceTypes: Pick<ServiceType, 'id' | 'name' | 'duration_minutes' | 'is_recurring'>[];
}

type CustomerFormData = {
    name: string;
    email: string;
    phone: string;
    address: string;
    postal_code: string;
    city: string;
    notes: string;
    is_active?: boolean;
};

function CustomerFormFields({
    data,
    setData,
    errors,
    idPrefix,
    showActive = false,
}: {
    data: CustomerFormData;
    setData: (key: keyof CustomerFormData, value: string | boolean) => void;
    errors: Partial<Record<keyof CustomerFormData, string>>;
    idPrefix: string;
    showActive?: boolean;
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
                {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
            </div>
            <div>
                <Label htmlFor={`${idPrefix}-email`}>E-Mail</Label>
                <Input
                    id={`${idPrefix}-email`}
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                />
            </div>
            <div>
                <Label htmlFor={`${idPrefix}-phone`}>Telefon</Label>
                <Input
                    id={`${idPrefix}-phone`}
                    value={data.phone}
                    onChange={(e) => setData('phone', e.target.value)}
                />
            </div>
            <div>
                <Label htmlFor={`${idPrefix}-postal_code`}>PLZ</Label>
                <Input
                    id={`${idPrefix}-postal_code`}
                    value={data.postal_code}
                    onChange={(e) => setData('postal_code', e.target.value)}
                    required
                />
            </div>
            <div className="md:col-span-2">
                <Label htmlFor={`${idPrefix}-address`}>Adresse</Label>
                <Input
                    id={`${idPrefix}-address`}
                    value={data.address}
                    onChange={(e) => setData('address', e.target.value)}
                    required
                />
            </div>
            <div>
                <Label htmlFor={`${idPrefix}-city`}>Stadt</Label>
                <Input
                    id={`${idPrefix}-city`}
                    value={data.city}
                    onChange={(e) => setData('city', e.target.value)}
                    required
                />
            </div>
            <div className="md:col-span-2">
                <Label htmlFor={`${idPrefix}-notes`}>Notizen</Label>
                <Textarea
                    id={`${idPrefix}-notes`}
                    rows={2}
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                />
            </div>
            {showActive && (
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

export default function Index({ customers, serviceTypes }: Props) {
    const { auth, flash } = usePage().props as {
        auth: { user: { roles: string[] } | null };
        flash?: { success?: string; error?: string };
    };
    const roles = auth.user?.roles ?? [];
    const canManage = roles.includes('company_admin');
    const canAssignServices = canManage || roles.includes('staff');
    const [editingId, setEditingId] = useState<number | null>(null);
    const [expandedCustomerId, setExpandedCustomerId] = useState<number | null>(null);
    const [nameSearch, setNameSearch] = useState('');
    const [selectedServiceTypeIds, setSelectedServiceTypeIds] = useState<number[]>([]);
    const [filterWithoutServices, setFilterWithoutServices] = useState(false);

    const hasActiveFilters =
        nameSearch.trim().length > 0 || selectedServiceTypeIds.length > 0 || filterWithoutServices;

    const filteredCustomers = useMemo(() => {
        const query = nameSearch.trim().toLowerCase();
        const hasServiceFilter = selectedServiceTypeIds.length > 0 || filterWithoutServices;

        return customers.data.filter((customer) => {
            if (query && !customer.name.toLowerCase().includes(query)) {
                return false;
            }

            if (!hasServiceFilter) {
                return true;
            }

            const assignedTypeIds = new Set(
                (customer.recurring_services ?? []).map((service) => service.service_type_id),
            );
            const matchesServiceType = selectedServiceTypeIds.some((id) => assignedTypeIds.has(id));
            const matchesWithoutServices = filterWithoutServices && assignedTypeIds.size === 0;

            return matchesServiceType || matchesWithoutServices;
        });
    }, [customers.data, nameSearch, selectedServiceTypeIds, filterWithoutServices]);

    const toggleServiceFilter = (serviceTypeId: number) => {
        setSelectedServiceTypeIds((current) =>
            current.includes(serviceTypeId)
                ? current.filter((id) => id !== serviceTypeId)
                : [...current, serviceTypeId],
        );
    };

    const clearFilters = () => {
        setNameSearch('');
        setSelectedServiceTypeIds([]);
        setFilterWithoutServices(false);
    };

    const createForm = useForm<CustomerFormData>({
        name: '',
        email: '',
        phone: '',
        address: '',
        postal_code: '',
        city: '',
        notes: '',
    });

    const editForm = useForm<CustomerFormData & { is_active: boolean }>({
        name: '',
        email: '',
        phone: '',
        address: '',
        postal_code: '',
        city: '',
        notes: '',
        is_active: true,
    });

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route('customers.store'), { onSuccess: () => createForm.reset() });
    };

    const toggleCustomer = (customerId: number) => {
        setExpandedCustomerId((current) => (current === customerId ? null : customerId));
    };

    const startEdit = (customer: Customer) => {
        setExpandedCustomerId(null);
        editForm.setData({
            name: customer.name,
            email: customer.email ?? '',
            phone: customer.phone ?? '',
            address: customer.address,
            postal_code: customer.postal_code,
            city: customer.city,
            notes: customer.notes ?? '',
            is_active: customer.is_active,
        });
        setEditingId(customer.id);
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();
        if (!editingId) return;
        editForm.patch(route('customers.update', editingId), {
            onSuccess: () => setEditingId(null),
        });
    };

    const deleteCustomer = (customer: Customer) => {
        if (!confirm(`„${customer.name}" wirklich löschen?`)) return;
        router.delete(route('customers.destroy', customer.id));
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Kunden</h2>}>
            <Head title="Kunden" />
            <div className="space-y-6 py-8">
                {flash?.success && (
                    <p className="rounded-md bg-green-50 p-3 text-sm text-green-800">{flash.success}</p>
                )}
                {flash?.error && (
                    <p className="rounded-md bg-red-50 p-3 text-sm text-red-800">{flash.error}</p>
                )}

                {canManage && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Neuer Kunde</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitCreate} className="grid gap-3 md:grid-cols-2">
                                <div className="md:col-span-2">
                                    <BillingOverageWarning type="customers" />
                                </div>
                                <CustomerFormFields
                                    data={createForm.data}
                                    setData={createForm.setData}
                                    errors={createForm.errors}
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
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>
                            Kundenliste (
                            {hasActiveFilters
                                ? `${filteredCustomers.length} von ${customers.data.length}`
                                : customers.data.length}
                            )
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-6 space-y-4">
                            <div>
                                <Label htmlFor="customer-search">Suche nach Name</Label>
                                <div className="relative mt-1">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        id="customer-search"
                                        type="search"
                                        placeholder="Kundennamen eingeben…"
                                        value={nameSearch}
                                        onChange={(e) => setNameSearch(e.target.value)}
                                        className="pl-9"
                                    />
                                </div>
                            </div>

                            {(serviceTypes.length > 0 || customers.data.some((c) => !c.recurring_services?.length)) && (
                                <div>
                                    <p className="mb-2 text-sm font-medium">Nach Service filtern</p>
                                    <div className="flex flex-wrap gap-2">
                                        {serviceTypes.map((type) => {
                                            const isSelected = selectedServiceTypeIds.includes(type.id);

                                            return (
                                                <button
                                                    key={type.id}
                                                    type="button"
                                                    onClick={() => toggleServiceFilter(type.id)}
                                                    aria-pressed={isSelected}
                                                    className={cn(
                                                        'inline-flex items-center rounded-md border px-2.5 py-1 text-xs font-semibold transition-colors',
                                                        isSelected
                                                            ? 'border-primary bg-primary text-primary-foreground'
                                                            : 'border-input bg-background hover:bg-muted',
                                                    )}
                                                >
                                                    {type.name}
                                                </button>
                                            );
                                        })}
                                        <button
                                            type="button"
                                            onClick={() => setFilterWithoutServices((current) => !current)}
                                            aria-pressed={filterWithoutServices}
                                            className={cn(
                                                'inline-flex items-center rounded-md border px-2.5 py-1 text-xs font-semibold transition-colors',
                                                filterWithoutServices
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-input bg-background hover:bg-muted',
                                            )}
                                        >
                                            Ohne Services
                                        </button>
                                    </div>
                                </div>
                            )}

                            {hasActiveFilters && (
                                <Button type="button" variant="ghost" size="sm" onClick={clearFilters}>
                                    <X className="mr-1 h-4 w-4" />
                                    Filter zurücksetzen
                                </Button>
                            )}
                        </div>

                        <ul className="space-y-2">
                            {filteredCustomers.map((customer) => {
                                const isExpanded = expandedCustomerId === customer.id;

                                return (
                                <li key={customer.id}>
                                    {editingId === customer.id ? (
                                        <div className="rounded-lg border border-transparent py-4">
                                        <form onSubmit={submitEdit} className="grid gap-3 md:grid-cols-2">
                                            <CustomerFormFields
                                                data={editForm.data}
                                                setData={editForm.setData}
                                                errors={editForm.errors}
                                                idPrefix={`edit-${customer.id}`}
                                                showActive
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
                                        </div>
                                    ) : (
                                        <div
                                            className={cn(
                                                'rounded-lg transition-all duration-300',
                                                isExpanded
                                                    ? 'border border-primary/25 bg-primary/5 p-4 shadow-sm ring-1 ring-primary/10'
                                                    : 'border border-transparent py-4',
                                            )}
                                        >
                                            <div className="flex items-start gap-3">
                                                <button
                                                    type="button"
                                                    onClick={() => toggleCustomer(customer.id)}
                                                    aria-expanded={isExpanded}
                                                    className={cn(
                                                        'mt-0.5 shrink-0 rounded-md p-1 transition-colors',
                                                        isExpanded
                                                            ? 'text-primary hover:bg-primary/10'
                                                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                                    )}
                                                >
                                                    <ChevronDown
                                                        className={cn(
                                                            'h-5 w-5 transition-transform duration-300',
                                                            isExpanded && 'rotate-180',
                                                        )}
                                                    />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => toggleCustomer(customer.id)}
                                                    className={cn(
                                                        'min-w-0 flex-1 rounded-lg text-left transition-colors',
                                                        isExpanded ? 'hover:bg-primary/10' : 'hover:bg-muted/40',
                                                    )}
                                                >
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p className="font-medium">{customer.name}</p>
                                                        {!customer.is_active && (
                                                            <Badge variant="secondary">Inaktiv</Badge>
                                                        )}
                                                        {(customer.recurring_services?.length ?? 0) > 0 ? (
                                                            <Badge variant="outline">
                                                                {customer.recurring_services!.length}{' '}
                                                                {customer.recurring_services!.length === 1
                                                                    ? 'Service'
                                                                    : 'Services'}
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="secondary">Keine Services</Badge>
                                                        )}
                                                        {(customer.recurring_services?.filter(
                                                            (s) => s.is_due && s.is_active,
                                                        ).length ?? 0) > 0 && (
                                                            <Badge>
                                                                {
                                                                    customer.recurring_services!.filter(
                                                                        (s) => s.is_due && s.is_active,
                                                                    ).length
                                                                }{' '}
                                                                fällig
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">
                                                        {customer.address}, {customer.postal_code} {customer.city}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {[customer.email, customer.phone]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </p>
                                                    {customer.notes && (
                                                        <p className="mt-1 text-sm text-muted-foreground line-clamp-1">
                                                            {customer.notes}
                                                        </p>
                                                    )}
                                                </button>
                                                {canManage && (
                                                    <div className="flex shrink-0 gap-2">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                startEdit(customer);
                                                            }}
                                                        >
                                                            Bearbeiten
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="destructive"
                                                            size="sm"
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                deleteCustomer(customer);
                                                            }}
                                                        >
                                                            Löschen
                                                        </Button>
                                                    </div>
                                                )}
                                            </div>

                                            <div
                                                className={cn(
                                                    'grid transition-all duration-300 ease-in-out',
                                                    isExpanded
                                                        ? 'mt-3 grid-rows-[1fr] opacity-100'
                                                        : 'grid-rows-[0fr] opacity-0',
                                                )}
                                            >
                                                <div className="overflow-hidden">
                                                    <CustomerServiceAssignments
                                                        customerId={customer.id}
                                                        services={customer.recurring_services ?? []}
                                                        serviceTypes={serviceTypes}
                                                        canAssign={canAssignServices}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </li>
                            );
                            })}
                            {customers.data.length === 0 && (
                                <p className="py-3 text-sm text-muted-foreground">Noch keine Kunden.</p>
                            )}
                            {customers.data.length > 0 && filteredCustomers.length === 0 && (
                                <p className="py-3 text-sm text-muted-foreground">
                                    Keine Kunden entsprechen Ihrer Suche oder den gewählten Filtern.
                                </p>
                            )}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
