<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Single Campus Mode
    |--------------------------------------------------------------------------
    |
    | TCN Ikorodu is one church again: Ikorodu is the only campus in use, so
    | every campus filter in the app is dead code. In this mode queries no
    | longer look at `campus_id` at all - the column is only kept on the tables
    | (and filled on new rows) so the data stays usable if a second campus is
    | ever opened.
    |
    | The filters are not deleted one by one: every call site is written as
    | ->forCurrentCampus(). Set SINGLE_CAMPUS=false and they all come back to
    | life exactly as they were - no code change, no redeploy of the controllers.
    |
    */

    'single' => filter_var(env('SINGLE_CAMPUS', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Default Campus
    |--------------------------------------------------------------------------
    |
    | The campus id stamped on new rows while the campus pickers are gone, and
    | the id used by anything that still has to name a campus (the campus table
    | itself, birthday reminder logs, in-app notifications). It is also the
    | display name used when the campus table has no row at all.
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
