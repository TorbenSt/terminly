import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { completeSchedulingLabFlow } from '@/lib/scheduling-lab-return';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useEffect } from 'react';

interface Props {
    negotiation: {
        token: string;
        round: number;
        service_name: string;
    };
    schedulingLab?: boolean;
}

export default function NegotiationForm({ negotiation, schedulingLab = false }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const { data, setData, post, processing, errors } = useForm({
        feedback: '',
        request_manual_contact: false,
        scheduling_lab: schedulingLab,
    });

    useEffect(() => {
        if (flash?.success && schedulingLab) {
            completeSchedulingLabFlow(true);
        }
    }, [flash?.success, schedulingLab]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('public.negotiations.store', negotiation.token), {
            onSuccess: () => completeSchedulingLabFlow(schedulingLab),
        });
    };

    return (
        <div className="min-h-screen bg-gray-50 py-12">
            <Head title="Terminwünsche" />
            <div className="mx-auto max-w-lg px-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Ihre Terminwünsche</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            {negotiation.service_name} · Verhandlungsrunde {negotiation.round}
                        </p>
                    </CardHeader>
                    <CardContent>
                        {flash?.success && !schedulingLab && (
                            <p className="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800">{flash.success}</p>
                        )}
                        {schedulingLab && (
                            <p className="mb-4 rounded-md bg-teal-50 p-3 text-sm text-teal-800">
                                Testmodus: Nach dem Absenden kehren Sie zum Scheduling Lab zurück.
                            </p>
                        )}
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="feedback">
                                    Bitte teilen Sie uns Ihre Wunschtermine oder Einschränkungen mit
                                </Label>
                                <Textarea
                                    id="feedback"
                                    className="mt-2"
                                    rows={5}
                                    value={data.feedback}
                                    onChange={(e) => setData('feedback', e.target.value)}
                                    placeholder="z.B. nur vormittags, nicht am Freitag, bevorzugt 10–12 Uhr..."
                                    required
                                />
                                {errors.feedback && <p className="text-sm text-red-600">{errors.feedback}</p>}
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={data.request_manual_contact}
                                    onChange={(e) => setData('request_manual_contact', e.target.checked)}
                                />
                                Ich möchte persönlich kontaktiert werden (WhatsApp/Telefon)
                            </label>
                            <Button type="submit" disabled={processing}>
                                Wünsche absenden
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
