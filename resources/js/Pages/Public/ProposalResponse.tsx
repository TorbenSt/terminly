import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, router, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Option {
    number: number;
    label: string;
    iso: string;
}

interface Props {
    proposal: {
        token: string;
        round: number;
        options: Option[];
        service_name: string;
        duration_minutes: number;
    };
}

export default function ProposalResponse({ proposal }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const [selected, setSelected] = useState<number | null>(null);

    const accept = (e: FormEvent) => {
        e.preventDefault();
        if (!selected) return;
        router.post(route('public.proposals.accept', proposal.token), { option: selected });
    };

    return (
        <div className="min-h-screen bg-gray-50 py-12">
            <Head title="Terminvorschläge" />
            <div className="mx-auto max-w-lg px-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Terminvorschläge – {proposal.service_name}</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Dauer ca. {proposal.duration_minutes} Minuten · Runde {proposal.round}
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {flash?.success && (
                            <p className="rounded-md bg-green-50 p-3 text-sm text-green-800">{flash.success}</p>
                        )}
                        <form onSubmit={accept} className="space-y-3">
                            {proposal.options.map((option) => (
                                <label
                                    key={option.number}
                                    className={`flex cursor-pointer items-center gap-3 rounded-lg border p-4 ${
                                        selected === option.number ? 'border-primary bg-primary/5' : ''
                                    }`}
                                >
                                    <input
                                        type="radio"
                                        name="option"
                                        value={option.number}
                                        checked={selected === option.number}
                                        onChange={() => setSelected(option.number)}
                                    />
                                    <span className="font-medium">Option {option.number}: {option.label} Uhr</span>
                                </label>
                            ))}
                            <div className="flex gap-3 pt-2">
                                <Button type="submit" disabled={!selected}>
                                    Termin bestätigen
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => router.post(route('public.proposals.reject', proposal.token))}
                                >
                                    Keine Option passt
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
