<?php

/*
|--------------------------------------------------------------------------
| Grade map (students × days) — EN
|--------------------------------------------------------------------------
| The chip carries the grade VALUE; colour marks the pass mark (below 5 =
| red), the amber accent marks a summative — same convention as the cabinet.
*/

return [
    'title' => 'Grade map',
    'hint_act' => 'Click a grade for details and the actions you are allowed — annulment and correction follow the usual flows.',
    'hint_read' => 'The class grades, day by day; the value is on the chip, the subject shows on click.',

    'student' => 'Student',
    'totals' => 'Total',
    'day_count' => '{1} :count grade on this day|[2,*] :count grades on this day',

    'legend_below' => 'Below 5',
    'legend_summative' => 'Summative',
    'legend_pending' => 'Correction pending',

    'totals_grades' => 'Grades',
    'totals_average' => 'Average',

    'switch_to_list' => 'Show list',
    'switch_to_map' => 'Show map',

    'open_record' => 'Open record',
    'action_denied' => 'You are not allowed to perform this operation on the chosen grade.',

    'scroll_left' => 'Scroll the days left',
    'scroll_right' => 'Scroll the days right',

    'empty_period' => 'No grades in the chosen period. Pick another period from the bar above.',
];
