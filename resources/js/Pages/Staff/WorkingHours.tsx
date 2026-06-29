import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TimeInput } from '@/components/ui/time-input';
import { Label } from '@/components/ui/label';
import { DAY_LABELS_LONG, WEEK_DAYS_MONDAY_FIRST } from '@/lib/datetime';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useMemo } from 'react';

interface AvailabilityInput {
    day_of_week: number;
    start_time: string;
    end_time: string;
    has_break: boolean;
    break_start_time: string;
    break_end_time: string;
}

interface AvailabilityRow extends AvailabilityInput {
    is_working: boolean;
}

interface Props {
    staffMember: { id: number; name: string };
    availabilities: AvailabilityInput[];
}

function buildRows(existing: AvailabilityInput[]): AvailabilityRow[] {
    return WEEK_DAYS_MONDAY_FIRST.map((day) => {
        const row = existing.find((availability) => availability.day_of_week === day);

        return {
            day_of_week: day,
            is_working: !!row,
            start_time: row?.start_time ?? '08:00',
            end_time: row?.end_time ?? '17:00',
            has_break: row?.has_break ?? false,
            break_start_time: row?.break_start_time ?? '12:00',
            break_end_time: row?.break_end_time ?? '13:00',
        };
    });
}

export default function WorkingHours({ staffMember, availabilities }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const initialRows = useMemo(() => buildRows(availabilities), [availabilities]);

    const { data, setData, patch, processing, errors } = useForm({
        availabilities: initialRows,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        patch(route('working-hours.update'));
    };

    const updateRow = (day: number, changes: Partial<AvailabilityRow>) => {
        setData(
            'availabilities',
            data.availabilities.map((row) => (row.day_of_week === day ? { ...row, ...changes } : row)),
        );
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Arbeitszeiten</h2>}>
            <Head title="Arbeitszeiten" />
            <div className="space-y-6">
                {flash?.success && (
                    <p className="rounded-md bg-green-50 p-3 text-sm text-green-800">{flash.success}</p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Standard-Arbeitszeiten</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Wöchentliche Verfügbarkeit für {staffMember.name}
                        </p>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            {data.availabilities.map((row) => (
                                <div key={row.day_of_week} className="space-y-3 rounded-lg border p-3">
                                    <label className="flex items-center gap-2 text-sm font-medium">
                                        <input
                                            type="checkbox"
                                            checked={row.is_working}
                                            onChange={(e) =>
                                                updateRow(row.day_of_week, { is_working: e.target.checked })
                                            }
                                        />
                                        {DAY_LABELS_LONG[row.day_of_week]}
                                    </label>

                                    <div className="flex flex-wrap gap-3">
                                        <div>
                                            <Label className="text-xs">Von</Label>
                                            <TimeInput
                                                value={row.start_time}
                                                disabled={!row.is_working}
                                                onChange={(start_time) =>
                                                    updateRow(row.day_of_week, { start_time })
                                                }
                                                required={row.is_working}
                                            />
                                        </div>
                                        <div>
                                            <Label className="text-xs">Bis</Label>
                                            <TimeInput
                                                value={row.end_time}
                                                disabled={!row.is_working}
                                                onChange={(end_time) =>
                                                    updateRow(row.day_of_week, { end_time })
                                                }
                                                required={row.is_working}
                                            />
                                        </div>
                                    </div>

                                    {row.is_working && (
                                        <div className="space-y-2 rounded-md bg-muted/40 p-3">
                                            <label className="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    checked={row.has_break}
                                                    onChange={(e) =>
                                                        updateRow(row.day_of_week, { has_break: e.target.checked })
                                                    }
                                                />
                                                Tägliche Pause
                                            </label>
                                            {row.has_break && (
                                                <div className="flex flex-wrap gap-3">
                                                    <div>
                                                        <Label className="text-xs">Pause von</Label>
                                                        <TimeInput
                                                            value={row.break_start_time}
                                                            onChange={(break_start_time) =>
                                                                updateRow(row.day_of_week, {
                                                                    break_start_time,
                                                                })
                                                            }
                                                            required
                                                        />
                                                    </div>
                                                    <div>
                                                        <Label className="text-xs">Pause bis</Label>
                                                        <TimeInput
                                                            value={row.break_end_time}
                                                            onChange={(break_end_time) =>
                                                                updateRow(row.day_of_week, {
                                                                    break_end_time,
                                                                })
                                                            }
                                                            required
                                                        />
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            ))}

                            {errors.availabilities && (
                                <p className="text-sm text-red-600">{errors.availabilities}</p>
                            )}

                            <Button type="submit" disabled={processing}>
                                Speichern
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
