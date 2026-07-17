<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ankunftsfenster für Terminvorschläge
    |--------------------------------------------------------------------------
    |
    | Fensterbreite = base + (increment × Anzahl vorheriger Termine am Tag),
    | begrenzt auf max. Der geplante Slot-Start ist der früheste Ankunftszeitpunkt.
    |
    */

    'arrival_window_base_minutes' => (int) env('ARRIVAL_WINDOW_BASE_MINUTES', 30),

    'arrival_window_increment_minutes' => (int) env('ARRIVAL_WINDOW_INCREMENT_MINUTES', 15),

    'arrival_window_max_minutes' => (int) env('ARRIVAL_WINDOW_MAX_MINUTES', 90),

    /*
    |--------------------------------------------------------------------------
    | Planungshorizont (AI-Kontext & Fallback)
    |--------------------------------------------------------------------------
    */

    // Compact AI context window (token + query cost)
    'ai_slot_horizon_days' => (int) env('SCHEDULING_AI_SLOT_HORIZON_DAYS', 28),

    // Existing tours / regional ranking horizon
    'ai_appointment_horizon_days' => (int) env('SCHEDULING_AI_APPOINTMENT_HORIZON_DAYS', 90),

    // Fallback/curator search when calendars are dense
    'candidate_search_weekdays' => (int) env('SCHEDULING_CANDIDATE_SEARCH_WEEKDAYS', 90),

    // Minimum calendar days before an offered appointment (1 = not same-day).
    // The effective date is then advanced to the next weekday.
    'min_lead_days' => (int) env('SCHEDULING_MIN_LEAD_DAYS', 1),

    // Default SLA / completion window after next_due_at (days).
    'default_completion_window_days' => (int) env('SCHEDULING_DEFAULT_COMPLETION_WINDOW_DAYS', 14),

];
