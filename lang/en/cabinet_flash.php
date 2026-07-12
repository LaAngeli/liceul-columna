<?php

declare(strict_types=1);

/*
 * Confirmation (toast) messages shown in the student/parent cabinet after successful actions.
 */

return [
    'motivation_sent' => 'The absence justification request was sent to the class teacher.',
    'motivation_sent_exception' => 'The request (exception, past the deadline) was forwarded to the deputy head for student affairs.',
    'status_acknowledged' => 'Your acknowledgement has been recorded.',
    'request_generated' => 'The request was generated and sent to the front office.',
    // VALIDATION message (not a toast): anti-duplicate on document request submission.
    'request_duplicate_pending' => 'A request of this type is already pending for this student — the front office will process it; no need to resubmit.',
];
