import { cn } from '@/lib/utils';
import { buildTime24, HOUR_OPTIONS, MINUTE_OPTIONS, parseTime24 } from '@/lib/datetime';

interface TimeInputProps {
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    required?: boolean;
    className?: string;
    id?: string;
}

const selectClassName =
    'flex h-10 rounded-md border border-input bg-background px-2 py-2 text-base ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

function TimeInput({ value, onChange, disabled, required, className, id }: TimeInputProps) {
    const { hour, minute } = parseTime24(value);

    const update = (nextHour: string, nextMinute: string) => {
        onChange(buildTime24(nextHour, nextMinute));
    };

    return (
        <div className={cn('flex items-center gap-1', className)} id={id}>
            <select
                aria-label="Stunde"
                className={cn(selectClassName, 'w-[4.5rem]')}
                value={hour}
                disabled={disabled}
                required={required}
                onChange={(e) => update(e.target.value, minute)}
            >
                {HOUR_OPTIONS.map((option) => (
                    <option key={option} value={option}>
                        {option}
                    </option>
                ))}
            </select>
            <span className="text-sm font-medium text-muted-foreground">:</span>
            <select
                aria-label="Minute"
                className={cn(selectClassName, 'w-[4.5rem]')}
                value={minute}
                disabled={disabled}
                required={required}
                onChange={(e) => update(hour, e.target.value)}
            >
                {MINUTE_OPTIONS.map((option) => (
                    <option key={option} value={option}>
                        {option}
                    </option>
                ))}
            </select>
            <span className="sr-only">Uhr</span>
        </div>
    );
}

export { TimeInput };
