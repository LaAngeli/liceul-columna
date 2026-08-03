<?php

/*
|--------------------------------------------------------------------------
| Panel section guides (the "i" icon next to the heading) — EN
|--------------------------------------------------------------------------
| Keys are registered in App\Support\PanelGuide. Writing rule: don't describe
| what is already on screen — explain WHY the section exists, which decision it
| supports, and the rule you would otherwise get wrong. Two or three sentences:
| this is a tooltip, not a manual.
*/

return [
    'aria_label' => 'About this section',

    // ── Catalogue ──────────────────────────────────────────────────────────────────────────
    'class_register' => 'The whole class on one screen, so a lesson\'s grades and absences go in with a single save — the individual forms stay for one-off corrections. You only see the classes and subjects that are yours: the filter is applied on the server, not hidden in the interface.',

    'grades' => 'A grade is never deleted. A mistake is ANNULLED with a reason (it stays in the history, drops out of the averages, disappears from the family cabinet), and changing a value goes through a request approved by management. An individual grade is a whole number from 1 to 10 — decimals only appear in averages.',

    'absences' => 'Every unexcused absence automatically gets a deadline: the date of the absence plus 5 working days. Excusing is not ticked here — the family requests it from their cabinet and the homeroom teacher validates it under Approvals. Deferral risk counts ALL absences in a subject, excused or not.',

    'homework' => 'An assignment is visible to exactly the class and subject it is tied to, not to the whole school. Links must be real addresses — free text is rejected; textbook references belong in the printed-resources field, so the student sees them next to the link.',

    'academic_records' => 'The official transcript by grade level — read, not written. It fills itself when the year is closed, from the term averages: the yearly figure is the arithmetic mean of the two terms, without rounding. Where a retake exam was passed, its grade is the yearly result, not the failed average.',

    'students' => 'The student\'s registry record. A student without an enrolment EXISTS but does not work: they appear in no catalogue, no timetable and no cabinet. "Archive" also shows the files of those who left, with the reason — graduation, transfer, withdrawal or expulsion.',

    'subjects' => 'The nomenclature also sets the grade levels at which each subject is taught. Opening a new year depends on it: teaching assignments whose subject is no longer taught at the higher level are NOT copied — at cycle boundaries (IV→V, IX→X) the curriculum changes.',

    'school_classes' => 'The class is what assignments, the timetable, the catalogue and the cabinet all rest on. A homeroom teacher may be missing temporarily (vacancy, import residue) — the field deliberately allows it, and classes left without one are surfaced as a task on the dashboard.',

    'corigenta_exams' => 'Exams are not created by hand: they appear automatically when a student is marked for a retake. Here they are SCHEDULED (session, date, board) and the result is recorded. The exam grade becomes the official yearly result for that subject.',

    // ── Approvals ──────────────────────────────────────────────────────────────────────────
    'grade_corrections' => 'A grade\'s value is not changed by whoever entered it: the teacher requests, management approves, and only then does the grade change — with averages recalculated automatically. Rejected requests stay in the archive too: the trail of a correction is itself evidence.',

    'absence_motivations' => 'The family files the request from their cabinet; the homeroom teacher validates it here. Approval marks ALL absences in the requested period as excused — they are not ticked one by one. The family has 5 working days from the absence, and that deadline shifts if the holiday calendar changes.',

    'homework_corrections' => 'The register of homework corrections: what changed, by whom and when. Correcting an assignment is DIRECT — unlike grades it needs no approval, because it affects nobody\'s average. Oversight remains through the audit log.',

    // ── Configuration ──────────────────────────────────────────────────────────────────────
    'configuration' => 'Every institutional setting, grouped by category. The order matters: the school year and terms first, then classes and subjects, and only then enrolments — each step rests on the one before it.',

    'academic_years' => '"Open the new year" moves the STRUCTURE up one level (classes and teaching assignments); students follow separately, via Promotion under Enrolments. "Close out the cohort" takes the final-year classes out of the register — the step that also starts the statutory retention period for their files. "Archive" writes the year\'s averages into the transcript.',

    'terms' => 'Term boundaries decide which term each grade and absence falls into. Moving them REALIGNS the catalogue: records left outside the new boundaries are redistributed on save — the page tells you in advance how many there are.',

    'enrollments' => 'The register of belonging: who, in which class, from what date. Transferring between classes leaves grades already given on the OLD class (the history stays correct) while the catalogue continues on the new one. Departure is not deletion — the row stays, with its date and reason.',

    'holidays' => 'Days off are not decorative: they drop out of the working-day count, which shifts absence-excusal deadlines that are already open. A day off outside the school year would never show in the calendar but would stay active in the calculations — which is why the form rejects it.',

    'schedules' => 'The single source for published timetables: what you edit here appears on the public site, under Calendar. Publishing is a decision separate from editing — until published, a timetable stays internal. The data is at CLASS level, with no personal details, which is why it can be read publicly.',

    'summative_designations' => 'Designates which subject and class sit a summative assessment. It bears directly on grades: the summative counts for 50% of the term average. In a class that is already configured, a summative in an undesignated subject is refused on save.',

    'grading_rules' => 'The averaging formula CANNOT be changed from the panel: it is legislation, not configuration. The page exists so a homeroom teacher can explain to a parent why the average is 7.46 and not 7.5 — the values shown are read from the same constants used in the calculation.',

    'lessons' => 'The lesson-level timetable (day, period, room, teacher) — the one that feeds "My day" in the student cabinet and provides the denominator for deferral risk: the absence percentage is measured against scheduled lessons. It is produced from the published timetables.',

    'corigenta_sessions' => 'Retake sessions go through three steps: proposed as a draft, approved by the head\'s order, and only then published. Until publication the family sees nothing — a date announced and then moved is worse than one announced late.',

    'exam_commissions' => 'The boards that examine retakes. Without an assigned board an exam cannot be scheduled — which is why the page separately lists the subjects still "to be covered".',

    'canteen_menus' => 'The day\'s menu, written by the operational administrator and read by the whole school. It is the only configuration section families consult directly from their cabinet — so a day left blank is visible from the outside.',

    // ── Communication ──────────────────────────────────────────────────────────────────────
    'messages' => 'Internal mail, filtered on the server: a family writes to their own child\'s teacher or homeroom teacher, and reaches management through an audience request. There is no "administration sees everything" — each account sees only its own threads.',

    'announcements' => 'The audience is resolved at PUBLICATION, not while writing — which is why the recipient count in the confirmation is the real one, not an estimate. Graduates and accounts left without an enrolled student no longer fall under "all families".',

    'calendar_events' => 'Manually added events, alongside those that arrive from the catalogue on their own (summative assessments, deadlines, sessions). An event\'s audience decides who sees it in their cabinet — an event with no audience chosen stays internal.',

    'calendar' => 'Every dated source in the school on one axis: the year\'s structure, exams, deadlines, audiences. It is not a separate calendar to fill in — it reads what already exists in the catalogue, so it cannot drift out of step with it.',

    // ── Front office & administration ──────────────────────────────────────────────────────
    'document_requests' => 'Requests filed by families from their cabinet. The PDF is generated on submission and stored PRIVATELY — it contains a minor\'s data, so it has no public URL and downloading goes through an access check. An appeal carries the disputed grade with it, so nobody has to guess.',

    'admission_requests' => 'Admission applications arriving from the public site. Processing leaves a trail (who, when, with what outcome), and accepted applications continue straight into onboarding — without re-entering data the family already filled in.',

    'documents' => 'The library of useful documents. Each document is visible only to the roles it is meant for, and the filter runs on the SERVER — a document you may not have is not merely hidden, it is unreachable. Uploading and publishing belong to the operational administrator.',

    'reports' => 'Institutional reports. Those containing student names are logged on export (Law 133): a trail remains of who took what, and when. Official documents are always rendered in Romanian regardless of interface language — they are legal records, not screens.',

    'users' => 'The person and their account in one place: the record, the access and the catalogue integration are created in a single transaction. The hierarchy is enforced on the server — you can only create the roles your own role is entitled to create. An account is never deleted, only suspended.',

    'role_matrix' => 'Who can do what, by role. This is not hand-written documentation: the cells are read from the very capabilities the server checks on every action, so the matrix cannot fall behind the code.',

    'audits' => 'The audit log: who, when, what changed, from what to what. Rows cannot be edited or deleted from the panel — a mutable log would stop being evidence. It is kept as long as student files are.',

    'consents' => 'The record of the privacy notice (Law 133): who acknowledged it, and in which version. When the version changes, acknowledgement is asked for again — which is why the column shows the last update rather than a plain tick. A missing acknowledgement does not block the catalogue, but it is visible here.',

    'restore_center' => 'Whatever was deleted from the panel can be restored here. Accounts do not appear: they are not deleted, they are suspended. One caution when restoring — unique indexes also see deleted rows, so a duplicate created in the meantime blocks the return until the conflict is resolved.',
];
