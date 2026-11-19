<?php

return [
    'private_assignee' => 'A private task can only be assigned to the person who created it.',
    'visibility_creator_only' => 'Only the person who created this item can change who sees it.',
    'rrule_required_due_date' => 'A repeating task needs a due date.',
    'rrule_ended' => 'This rule has no dates after the due date.',
    'rrule' => [
        'length' => 'The repeat rule is empty or too long.',
        'syntax' => 'The repeat rule could not be understood.',
        'unsupported_part' => 'The repeat rule uses a part that is not supported.',
        'freq' => 'Tasks can repeat daily, weekly, monthly or yearly.',
        'interval' => 'The repeat interval must be between 1 and 366.',
        'count' => 'A task can repeat at most 1000 times.',
        'count_and_until' => 'Use either a number of repeats or an end date, not both.',
        'until' => 'The end date of the repeat rule is not valid.',
        'byday' => 'The weekdays in the repeat rule are not valid.',
        'bymonthday' => 'The days of the month in the repeat rule are not valid.',
        'bymonth' => 'The months in the repeat rule are not valid.',
        'bysetpos' => 'The positions in the repeat rule are not valid.',
        'wkst' => 'The week start in the repeat rule is not valid.',
    ],
];
