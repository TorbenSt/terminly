import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/components/ui/button';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Props {
    companyName: string;
    token: string;
    alreadyOptedOut: boolean;
}

export default function ProspectOptOut({ companyName, token, alreadyOptedOut }: Props) {
    const form = useForm({});

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('public.prospect-opt-out.store', token));
    };

    return (
        <GuestLayout>
            <Head title="Abmeldung" />
            <div className="mx-auto max-w-md py-12">
                <h1 className="text-xl font-semibold">Keine weiteren Nachrichten</h1>
                <p className="mt-2 text-muted-foreground">
                    {alreadyOptedOut
                        ? `Sie haben sich bereits von Nachrichten von ${companyName} abgemeldet.`
                        : `Möchten Sie keine weiteren Nachrichten von ${companyName} erhalten?`}
                </p>
                {!alreadyOptedOut && (
                    <form onSubmit={submit} className="mt-6">
                        <Button type="submit" disabled={form.processing}>
                            Abmeldung bestätigen
                        </Button>
                    </form>
                )}
            </div>
        </GuestLayout>
    );
}
