<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// at your option any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Teacher report page for the SelfProfile activity module.
 *
 * @package    mod_selfprofile
 * @copyright  2026 Johan Venter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/mod/selfprofile/lib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('selfprofile', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$selfprofile = $DB->get_record('selfprofile', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/selfprofile:viewreports', $context);

$PAGE->set_url('/mod/selfprofile/report.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($selfprofile->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($selfprofile->name));
echo $OUTPUT->heading(get_string('reports', 'mod_selfprofile'), 3);

$enabledstatementcount = $DB->count_records(
    'selfprofile_statements',
    ['selfprofileid' => $selfprofile->id, 'enabled' => 1]
);

if ($enabledstatementcount === 0) {
    echo $OUTPUT->notification(get_string('nostatementsavailable', 'mod_selfprofile'), 'warning');
    echo $OUTPUT->continue_button(new moodle_url('/mod/selfprofile/view.php', ['id' => $cm->id]));
    echo $OUTPUT->footer();
    exit;
}

$sql = "SELECT a.id,
               a.userid,
               a.status,
               a.timecreated,
               a.timemodified,
               a.timesubmitted,
               u.firstname,
               u.lastname,
               u.firstnamephonetic,
               u.lastnamephonetic,
               u.middlename,
               u.alternatename,
               u.email,
               COUNT(r.id) AS responsecount
          FROM {selfprofile_attempts} a
          JOIN {user} u
            ON u.id = a.userid
     LEFT JOIN {selfprofile_responses} r
            ON r.attemptid = a.id
         WHERE a.selfprofileid = :selfprofileid
      GROUP BY a.id,
               a.userid,
               a.status,
               a.timecreated,
               a.timemodified,
               a.timesubmitted,
               u.firstname,
               u.lastname,
               u.firstnamephonetic,
               u.lastnamephonetic,
               u.middlename,
               u.alternatename,
               u.email
      ORDER BY u.lastname ASC, u.firstname ASC";

$attempts = $DB->get_records_sql($sql, ['selfprofileid' => $selfprofile->id]);

if (empty($attempts)) {
    echo $OUTPUT->notification(get_string('noreportdata', 'mod_selfprofile'), 'info');
    echo $OUTPUT->continue_button(new moodle_url('/mod/selfprofile/view.php', ['id' => $cm->id]));
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable selfprofile-report-table';
$table->head = [
    get_string('student', 'mod_selfprofile'),
    get_string('attemptstatus', 'mod_selfprofile'),
    get_string('progress', 'mod_selfprofile'),
    get_string('timesubmitted', 'mod_selfprofile'),
    get_string('actions', 'mod_selfprofile'),
];

foreach ($attempts as $attempt) {
    $studentname = fullname($attempt);
    $profileurl = new moodle_url('/user/view.php', ['id' => $attempt->userid, 'course' => $course->id]);
    $studentlink = html_writer::link($profileurl, s($studentname));

    $statusstring = $attempt->status === 'submitted'
        ? get_string('submitted', 'mod_selfprofile')
        : get_string('inprogress', 'mod_selfprofile');

    $progressdata = (object) [
        'answered' => (int) $attempt->responsecount,
        'total' => $enabledstatementcount,
    ];
    $progress = get_string('answeredcount', 'mod_selfprofile', $progressdata);

    $timesubmitted = '-';
    if (!empty($attempt->timesubmitted)) {
        $timesubmitted = userdate($attempt->timesubmitted);
    }

    $actions = [];

    if ($attempt->status === 'submitted' && (int) $attempt->responsecount > 0) {
        $resultsurl = new moodle_url('/mod/selfprofile/results.php', [
            'id' => $cm->id,
            'attemptid' => $attempt->id,
        ]);

        $actions[] = html_writer::link(
            $resultsurl,
            get_string('viewresults', 'mod_selfprofile'),
            ['class' => 'btn btn-secondary btn-sm']
        );

        $pdfurl = new moodle_url('/mod/selfprofile/pdf.php', [
            'id' => $cm->id,
            'attemptid' => $attempt->id,
        ]);

        $actions[] = html_writer::link(
            $pdfurl,
            get_string('downloadpdf', 'mod_selfprofile'),
            ['class' => 'btn btn-primary btn-sm']
        );
    } else {
        $actions[] = html_writer::span(get_string('resultsnotavailable', 'mod_selfprofile'), 'text-muted');
    }

    $table->data[] = [
        $studentlink,
        $statusstring,
        $progress,
        $timesubmitted,
        implode(' ', $actions),
    ];
}

echo html_writer::table($table);

$backurl = new moodle_url('/mod/selfprofile/view.php', ['id' => $cm->id]);
echo html_writer::div(
    html_writer::link($backurl, get_string('viewactivity', 'mod_selfprofile'), ['class' => 'btn btn-secondary']),
    'selfprofile-actions'
);

echo $OUTPUT->footer();
