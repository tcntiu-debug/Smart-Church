<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Single Campus Mode
    |--------------------------------------------------------------------------
    |
    | TCN Ikorodu is one church again: Ikorodu is the only campus in use, so
    | every campus filter in the app is dead code. In this mode queries no
    | longer look at `campus_id` at all, new rows leave the column empty, and
    | the ids that were already on the rows have been retired (see
    | docs/SINGLE-CAMPUS.md). The column itself is only kept on the tables so
    | the data stays usable if a second campus is ever opened.
    |
    | The filters are not deleted one by one: every call site is written as
    | ->forCurrentCampus(). Set SINGLE_CAMPUS=false and they all come back to
    | life exactly as they were - no code change, no redeploy of the
    | controllers (the retired ids have to be restored first, see
    | docs/SINGLE-CAMPUS.md).
    |
    */

    'single' => filter_var(env('SINGLE_CAMPUS', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Default Campus
    |--------------------------------------------------------------------------
    |
    | The campus the church runs. Nothing is stamped with it - new rows leave
    | `campus_id` empty - so it is what everything that still has to name a
    | campus uses: the birthday digest, the fallback when the campus table has
    | no usable row, and the undo step of the data retirement. It is also the
    | campus new rows are stamped with again once SINGLE_CAMPUS=false.
    |
    */

    'default_id' => (int) env('DEFAULT_CAMPUS_ID', 1),

    'default_name' => env('DEFAULT_CAMPUS_NAME', 'Ikorodu'),

    /*
    |--------------------------------------------------------------------------
    | Multi-campus mode (SINGLE_CAMPUS=false)
    |--------------------------------------------------------------------------
    |
    | With the switch off everything comes back: a logged-in user only sees their
    | own campus, a Super User sees every campus, and a user whose campus_id is
    | 0/NULL has no campus to filter by and therefore sees everything - exactly
    | the behaviour the app had before this switch existed. See
    | docs/SINGLE-CAMPUS.md.

    */

];
