<?php

/*
|--------------------------------------------------------------------------
| Absence map (Absences → class context) — EN
|--------------------------------------------------------------------------
*/

return [
    'title' => 'Absence map',
    'hint_status' => 'Click an absence to set its status — it saves right away.',
    'hint_read' => 'The class absences, day by day; the status is set by the homeroom teacher.',

    'student' => 'Student',
    'totals' => 'Total',
    'day_count' => '{1} :count absence on this day|[2,*] :count absences on this day',

    'whole_day' => 'Whole day',

    // The single chip marker; colour carries the status, the subject shows on hover. Same sign in
    // every language on purpose: it is a symbol, not a word.
    'marker' => 'A',

    'switch_to_list' => 'Show list',
    'switch_to_map' => 'Show map',

    'open_record' => 'Open record',

    'empty_period' => 'No absences in the chosen period. Pick another period from the bar above.',
    'scroll_left' => 'Scroll the days left',
    'scroll_right' => 'Scroll the days right',

    'status_reset' => 'The absence is back to "no status".',
    'status_denied' => 'Your account cannot change the status of this absence.',
];
