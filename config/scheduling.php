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

];
