import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DAY_LABELS_SHORT } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import { useEffect, useMemo, useState } from 'react';

interface YearCalendarProps {
    selectedDate: string;
    onSelectDate: (date: string) => void;
}

const WEEKDAY_HEADERS = [1, 2, 3, 4, 5, 6, 0] as const;

const MONTH_LABELS = Array.from({ length: 12 }, (_, month) =>
    new Date(2000, month, 1).toLocaleDateString('de-DE', { month: 'long' }),
);

function parseDateLocal(value: string): Date {
    return new Date(`${value}T12:00:00`);
}

function toDateString(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function getMondayFirstWeekdayIndex(year: number, month: number): number {
    const weekday = new Date(year, month, 1).getDay();

    return weekday === 0 ? 6 : weekday - 1;
}

function getDaysInMonth(year: number, month: number): number {
    return new Date(year, month + 1, 0).getDate();
}

export default function YearCalendar({ selectedDate, onSelectDate }: YearCalendarProps) {
    const selected = useMemo(() => parseDateLocal(selectedDate), [selectedDate]);
    const today = useMemo(() => toDateString(new Date()), []);
    const [displayYear, setDisplayYear] = useState(() => selected.getFullYear());

    useEffect(() => {
        setDisplayYear(selected.getFullYear());
    }, [selectedDate, selected]);

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
                <CardTitle className="text-base font-medium">Jahresübersicht</CardTitle>
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={() => setDisplayYear((year) => year - 1)}
                        aria-label="Vorheriges Jahr"
                    >
                        ←
                    </Button>
                    <span className="min-w-16 text-center font-medium">{displayYear}</span>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={() => setDisplayYear((year) => year + 1)}
                        aria-label="Nächstes Jahr"
                    >
                        →
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {MONTH_LABELS.map((monthLabel, month) => {
                        const leadingEmpty = getMondayFirstWeekdayIndex(displayYear, month);
                        const daysInMonth = getDaysInMonth(displayYear, month);
                        const days = Array.from({ length: daysInMonth }, (_, index) => index + 1);

                        return (
                            <div key={monthLabel} className="space-y-2">
                                <p className="text-sm font-medium capitalize">{monthLabel}</p>
                                <div className="grid grid-cols-7 gap-0.5 text-center text-xs text-muted-foreground">
                                    {WEEKDAY_HEADERS.map((weekday) => (
                                        <span key={weekday} className="py-1 font-medium">
                                            {DAY_LABELS_SHORT[weekday]}
                                        </span>
                                    ))}
                                    {Array.from({ length: leadingEmpty }, (_, index) => (
                                        <span key={`empty-${index}`} aria-hidden />
                                    ))}
                                    {days.map((day) => {
                                        const dateValue = toDateString(new Date(displayYear, month, day));
                                        const isSelected = dateValue === selectedDate;
                                        const isToday = dateValue === today;

                                        return (
                                            <Button
                                                key={dateValue}
                                                type="button"
                                                variant={isSelected ? 'default' : 'ghost'}
                                                size="sm"
                                                className={cn(
                                                    'h-7 w-full min-w-0 px-0 text-xs font-normal',
                                                    !isSelected && isToday && 'ring-1 ring-primary',
                                                )}
                                                aria-pressed={isSelected}
                                                aria-label={parseDateLocal(dateValue).toLocaleDateString('de-DE', {
                                                    weekday: 'long',
                                                    day: 'numeric',
                                                    month: 'long',
                                                    year: 'numeric',
                                                })}
                                                onClick={() => onSelectDate(dateValue)}
                                            >
                                                {day}
                                            </Button>
                                        );
                                    })}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
}
