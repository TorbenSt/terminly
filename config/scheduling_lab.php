<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduling Lab Feature Flag
    |--------------------------------------------------------------------------
    |
    | Explicitly enable the super-admin scheduling sandbox. Works on local,
    | staging, and production when set to true.
    |
    */

    'enabled' => (bool) env('SCHEDULING_LAB_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Snapshot Retention
    |--------------------------------------------------------------------------
    */

    'purge_after_days' => (int) env('SCHEDULING_LAB_PURGE_DAYS', 7),

    'snapshot_max_customers' => (int) env('SCHEDULING_LAB_MAX_CUSTOMERS', 50),

    'snapshot_max_appointments' => (int) env('SCHEDULING_LAB_MAX_APPOINTMENTS', 100),

];
