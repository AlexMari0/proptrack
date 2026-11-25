<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing Timezone
    |--------------------------------------------------------------------------
    |
    | Billing days (due dates, overdue, "due in N days" reminders) are counted
    | in this timezone, independent of the application timezone (UTC).
    | See docs/adr/0001-overdue-is-derived.md.
    |
    */

    'timezone' => env('BILLING_TIMEZONE', 'Asia/Jakarta'),

];
