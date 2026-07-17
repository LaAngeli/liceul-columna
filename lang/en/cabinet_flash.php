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
    // VALIDATION messages (not toasts) on contestation submission: the grade is PICKED (not
    // described), and the reason explains WHY — context (subject, value, date, teacher) comes from the grade.
    'contestation_details_required' => 'A contestation needs a reason: explain why you consider the grade incorrect.',
    'contestation_grade_required' => 'Choose the grade you are contesting from the list.',
    'contestation_grade_invalid' => 'The chosen grade does not exist or does not belong to this student.',
    'contestation_grade_pending' => 'The chosen grade already has a pending correction — management will rule on it; a new contestation is not needed.',
    'request_duplicate_pending' => 'A request of this type is already pending for this student — the front office will process it; no need to resubmit.',
    'request_details_required' => 'The request needs details so it can be processed (the reason, destination or topic — see the hints in the form).',
    'invoire_past_period' => 'Leave is requested for future days. For absences that already happened, use "Absence excusal" on the Situation tab.',
    'attachment_upload_failed' => 'The supporting file upload failed. Please try again.',
    'request_withdrawn' => 'The request was withdrawn. You can file a new one at any time.',
    'email_change_via_staff' => 'The e-mail address is already set. To change it, contact the front office — it is the account login.',
    'motivation_duplicate_pending' => 'There is already a pending absence-excuse request covering this period.',
    'motivation_no_absences' => 'There are no unexcused absences in the selected period — check the dates.',
    'motivation_upload_failed' => 'The document upload failed. Please try again.',
];
