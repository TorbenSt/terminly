<?php

namespace App\Http\Controllers;

use App\Models\StaffAvailability;
use App\Models\StaffMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StaffWorkingHoursController extends Controller
{
    public function index(Request $request): Response
    {
        $staffMember = $this->resolveStaffMember($request->user());
        $this->authorize('update', $staffMember);

        $staffMember->load('availabilities');

        return Inertia::render('Staff/WorkingHours', [
            'staffMember' => [
                'id' => $staffMember->id,
                'name' => $staffMember->name,
            ],
            'availabilities' => $staffMember->availabilities
                ->map(fn ($availability) => [
                    'day_of_week' => $availability->day_of_week,
                    'start_time' => substr((string) $availability->start_time, 0, 5),
                    'end_time' => substr((string) $availability->end_time, 0, 5),
                    'has_break' => $availability->break_start_time !== null && $availability->break_end_time !== null,
                    'break_start_time' => $availability->break_start_time
                        ? substr((string) $availability->break_start_time, 0, 5)
                        : '12:00',
                    'break_end_time' => $availability->break_end_time
                        ? substr((string) $availability->break_end_time, 0, 5)
                        : '13:00',
                ])
                ->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $staffMember = $this->resolveStaffMember($request->user());
        $this->authorize('update', $staffMember);

        $validated = $request->validate([
            'availabilities' => ['required', 'array', 'size:7'],
            'availabilities.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'availabilities.*.is_working' => ['required', 'boolean'],
            'availabilities.*.start_time' => ['nullable', 'date_format:H:i'],
            'availabilities.*.end_time' => ['nullable', 'date_format:H:i'],
            'availabilities.*.has_break' => ['required', 'boolean'],
            'availabilities.*.break_start_time' => ['nullable', 'date_format:H:i'],
            'availabilities.*.break_end_time' => ['nullable', 'date_format:H:i'],
        ]);

        foreach ($validated['availabilities'] as $index => $availability) {
            if (! $availability['is_working']) {
                continue;
            }

            if (empty($availability['start_time']) || empty($availability['end_time'])) {
                throw ValidationException::withMessages([
                    "availabilities.{$index}.start_time" => 'Bitte Start- und Endzeit angeben.',
                ]);
            }

            if ($availability['end_time'] <= $availability['start_time']) {
                throw ValidationException::withMessages([
                    "availabilities.{$index}.end_time" => 'Die Endzeit muss nach der Startzeit liegen.',
                ]);
            }

            if ($availability['has_break']) {
                if (empty($availability['break_start_time']) || empty($availability['break_end_time'])) {
                    throw ValidationException::withMessages([
                        "availabilities.{$index}.break_start_time" => 'Bitte Pausenbeginn und Pausenende angeben.',
                    ]);
                }

                if ($availability['break_end_time'] <= $availability['break_start_time']) {
                    throw ValidationException::withMessages([
                        "availabilities.{$index}.break_end_time" => 'Das Pausenende muss nach dem Pausenbeginn liegen.',
                    ]);
                }

                if ($availability['break_start_time'] <= $availability['start_time']
                    || $availability['break_end_time'] >= $availability['end_time']) {
                    throw ValidationException::withMessages([
                        "availabilities.{$index}.break_start_time" => 'Die Pause muss innerhalb der Arbeitszeit liegen.',
                    ]);
                }
            }
        }

        foreach ($validated['availabilities'] as $availability) {
            if ($availability['is_working']) {
                StaffAvailability::updateOrCreate(
                    [
                        'staff_member_id' => $staffMember->id,
                        'day_of_week' => $availability['day_of_week'],
                    ],
                    [
                        'start_time' => $availability['start_time'].':00',
                        'end_time' => $availability['end_time'].':00',
                        'break_start_time' => $availability['has_break']
                            ? $availability['break_start_time'].':00'
                            : null,
                        'break_end_time' => $availability['has_break']
                            ? $availability['break_end_time'].':00'
                            : null,
                    ]
                );
            } else {
                StaffAvailability::query()
                    ->where('staff_member_id', $staffMember->id)
                    ->where('day_of_week', $availability['day_of_week'])
                    ->delete();
            }
        }

        return back()->with('success', 'Arbeitszeiten gespeichert.');
    }

    private function resolveStaffMember($user): StaffMember
    {
        $staffMember = StaffMember::where('user_id', $user->id)->first();

        abort_unless($staffMember, 404, 'Kein Mitarbeiterprofil gefunden.');

        return $staffMember;
    }
}
