<?php

/*
|--------------------------------------------------------------------------
| Panel section guides (the "i" icon next to the heading) — EN
|--------------------------------------------------------------------------
| Keys are registered in App\Support\PanelGuide.
|
| HOW TO WRITE THESE (rewritten 2026-08-04, at the client's request — the
| first version read as heavy going):
|   1. The first sentence says WHAT the section is, in everyday words.
|   2. The second (rarely a third) says what you need to know so you don't
|      use it wrongly — as an effect you can see, not as inner machinery.
|   3. No system words ("server", "transaction", "index"), no CAPITALS for
|      emphasis, no sentences packing three ideas between dashes.
|   4. Neutral tone: describe, don't lecture.
|
| The reader is a teacher or a secretary, not a developer.
*/

return [
    'aria_label' => 'About this section',

    // ── Catalogue ──────────────────────────────────────────────────────────────────────────
    'class_register' => 'The whole class on one screen: you enter a lesson\'s grades and absences, then save once. You only see the classes and subjects you teach.',

    'grades' => 'Grades are never deleted. A wrong grade is annulled with a reason: it stays in the history but no longer counts towards averages and no longer shows in the family cabinet. To change a grade\'s value you request a correction, and management approves it.',

    'absences' => 'Student absences, by day and subject. Excusing is not ticked here: the family requests it from their own cabinet, and the homeroom teacher approves it under "Absence excusals". The family has 5 working days from the date of the absence.',

    'homework' => 'The assignments you have given your classes. An assignment is seen only by that class, and only under the subject you added it to. Links must be internet addresses; references to a textbook go in the printed-resources field.',

    'academic_records' => 'Each student\'s record, year by year. It is not filled in by hand: it writes itself when you close the year, from the term averages. If the student passed a retake exam, that exam\'s grade becomes the yearly one.',

    'students' => 'Each student\'s details: name, register number, foreign language, account. A student appears in the catalogue, the timetable and the cabinet only after being enrolled in a class. The "Archive" button also shows students who have left, with the reason.',

    'subjects' => 'The school\'s list of subjects and the classes each one is taught in. This decides which lessons carry over when you open a new year: only subjects that are also taught at the next level are copied.',

    'school_classes' => 'The classes of each school year, together with their homeroom teachers. A class may go without one for a while; those without appear as a task on the main page so they are not forgotten.',

    'corigenta_exams' => 'Students\' retake exams. They are not added by hand — they appear on their own when a student is left with a failed subject. Here you set the date, session and board, then enter the grade obtained, which becomes the yearly grade for that subject.',

    // ── Approvals ──────────────────────────────────────────────────────────────────────────
    'grade_corrections' => 'Teachers\' requests to change a grade already entered. The grade only changes once management approves the request, and the averages are recalculated then. Rejected requests also stay in the list.',

    'absence_motivations' => 'Families\' requests to excuse absences. When you approve a request, every absence in the requested period becomes excused at once — you do not tick them one by one. A family may request an excusal within 5 working days of the absence.',

    'homework_corrections' => 'The list of changes made to assignments: what changed, by whom and when. Assignments are corrected directly, without approval, because they change nobody\'s average.',

    // ── Configuration ──────────────────────────────────────────────────────────────────────
    'configuration' => 'All the school\'s settings, grouped by category. The order matters: the school year and terms first, then classes and subjects, and enrolments last.',

    'academic_years' => 'The school years and the operations that belong to them. "Open the new year" copies the classes and lessons into the next year, one level up; students move separately, from Enrolments. "Close out the cohort" removes the final-year classes from the register, and "Archive" writes the year\'s averages into the student records.',

    'terms' => 'The start and end dates of each term. If you move them, grades and absences left outside the new period move to the right term automatically; the page shows how many there are before you save.',

    'enrollments' => 'Which student is in which class, and from what date. When you move a student to another class, grades already entered stay with the old class. When a student leaves the school you do not delete them — you enter the date and reason for leaving.',

    'holidays' => 'Days with no lessons: public holidays, school breaks and days set by the school. They do not count as working days, so they change the deadlines within which families can request an absence excusal.',

    'schedules' => 'The timetables that appear on the school website. You write them here, and they show on the site under Calendar. Until a timetable is marked as published, it stays visible only inside the panel.',

    'summative_designations' => 'Here you choose which subjects have a summative assessment, for each class. A summative grade is worth half of the term average. While a class has no subject chosen, a summative may be entered for any subject; after the first choice, only subjects on the list are accepted.',

    'grading_rules' => 'How averages are calculated. The rules come from regulation and cannot be changed here. The page is useful when you need to explain to a parent why an average is, say, 7.46 and not 7.5.',

    'lessons' => 'The detailed timetable: which lesson, on which day, with which teacher and in which room. It feeds "My day" in the student cabinet, and it is also what tells you what share of a subject\'s lessons a student has missed.',

    'corigenta_sessions' => 'The periods when retake exams are held. A session goes through three steps: you propose it, the head approves it, then it is published. Families see it only after publication.',

    'exam_commissions' => 'The teachers who examine at retakes. Without a board in place an exam cannot be given a date; the page lists separately the subjects still without one.',

    'canteen_menus' => 'The canteen menu, day by day. Families see it too, straight from their cabinet, so a day left empty is noticed outside the school as well.',

    // ── Communication ──────────────────────────────────────────────────────────────────────
    'messages' => 'Your messages inside the school. Families may write to their child\'s teachers and homeroom teacher; to reach management there is the audience request. Everyone sees only the conversations they take part in.',

    'announcements' => 'Announcements to a chosen group: one class, all families, or all staff. The list of recipients is settled at the moment of publication, so the number in the confirmation is the real one. Graduates and accounts with no enrolled student no longer receive announcements meant for families.',

    'calendar_events' => 'Events you add yourself, alongside those that appear on their own from the catalogue: summative assessments, deadlines, exams. Choose who the event is for, otherwise it stays visible only inside the panel.',

    'calendar' => 'All the school\'s important dates in one place: terms, exams, deadlines, audiences. Nothing is filled in here — the information comes from the other sections.',

    // ── Front office & administration ──────────────────────────────────────────────────────
    'document_requests' => 'Requests families send from their cabinet: certificates, leave requests, transfers, appeals. Each request has a PDF that only the family and the front office can open. For appeals you also see the grade the request refers to.',

    'admission_requests' => 'Admission applications received through the website. It is recorded who handled each one and with what answer, and from an accepted application you can go straight on to enrolling the student, without entering the details again.',

    'documents' => 'The school\'s useful documents. Each document is visible only to the roles it is meant for. Uploading and publishing are the operational administrator\'s job.',

    'reports' => 'The school\'s reports. For those containing student names, it is recorded who downloaded them and when. Official documents are always produced in Romanian, whatever language you are working in.',

    'users' => 'The people in the school and their accounts. The record and the account are created together, from a single form. You can only create the roles your own role is allowed to create, and an account is not deleted but suspended.',

    'role_matrix' => 'What each role can do. The table shows the real permissions — the same ones the system checks on every action.',

    'audits' => 'The history of changes: who, when and what they changed. Rows cannot be edited and cannot be deleted. They are kept for as long as student files are.',

    'consents' => 'A record of who has acknowledged the privacy notice. When the notice changes, acknowledgement is asked for again, which is why you see the date of the last one. A missing acknowledgement does not block access to the catalogue.',

    'restore_center' => 'From here you can bring back what you deleted in the panel. Accounts are not listed, because they are not deleted but suspended. If something with the same details has been created in the meantime, the restore cannot go through until you resolve the overlap.',

    // ── Fields: only where the rule cannot be guessed from the screen ──────────────────────
    'fields' => [
        'grade_evaluation_type' => 'A summative assessment can only be entered for subjects chosen for the class under "Subjects with a summative". A summative grade is worth half of the term average.',

        'grade_graded_on' => 'This date decides which term the grade falls into, not the day you enter it. A grade dated after the school year has ended is not accepted.',

        'absence_occurred_on' => 'The family\'s 5 working days to request an excusal are counted from this date. If days off are added in the meantime, the deadline extends automatically.',

        'enrollment_departure_reason' => 'The reason matters, not just the date. Only "graduation" keeps the student\'s access to their own record and to certificates; with a transfer or an expulsion, that access closes.',

        'summative_class' => 'While the class has no subject chosen here, a summative may be entered for any of its subjects. After the first choice, only the subjects on the list are accepted.',

        'summative_bulk' => 'Only the missing pairs are added; existing ones are left alone. Note that a class with no subject chosen so far becomes restricted as soon as the first one is added.',
    ],
];
