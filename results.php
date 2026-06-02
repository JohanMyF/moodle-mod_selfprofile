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
 * Results page for the SelfProfile activity module.
 *
 * This page supports two routes:
 * - Students view their own results: results.php?id=cmid
 * - Teachers view a selected student's results by attempt id: results.php?id=cmid&attemptid=attemptid
 *
 * @package    mod_selfprofile
 * @copyright  2026 Johan Venter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/selfprofile/lib.php');

$id = required_param('id', PARAM_INT);
$attemptid = optional_param('attemptid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('selfprofile', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$selfprofile = $DB->get_record('selfprofile', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/selfprofile:view', $context);

$viewingotheruser = false;
$attempt = null;

if ($attemptid > 0) {
    require_capability('mod/selfprofile:viewreports', $context);

    $attempt = $DB->get_record('selfprofile_attempts', [
        'id' => $attemptid,
        'selfprofileid' => $selfprofile->id,
    ], '*', MUST_EXIST);

    $targetuserid = (int) $attempt->userid;
    $viewingotheruser = ($targetuserid !== (int) $USER->id);
} else {
    $targetuserid = (int) $USER->id;
}

$targetuser = $DB->get_record('user', ['id' => $targetuserid, 'deleted' => 0], '*', MUST_EXIST);

if ($viewingotheruser && !is_enrolled($context, $targetuser, '', true)) {
    throw new moodle_exception('invaliduser', 'error');
}

$pageparams = ['id' => $cm->id];
if ($attemptid > 0) {
    $pageparams['attemptid'] = $attemptid;
}
$PAGE->set_url('/mod/selfprofile/results.php', $pageparams);
$PAGE->set_title(format_string($selfprofile->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($selfprofile->name));

if ($viewingotheruser) {
    echo $OUTPUT->heading(get_string('resultsforuser', 'mod_selfprofile', fullname($targetuser)), 3);
} else {
    echo $OUTPUT->heading(get_string('yourresults', 'mod_selfprofile'), 3);
}

$viewurl = new moodle_url('/mod/selfprofile/view.php', ['id' => $cm->id]);
$attempturl = new moodle_url('/mod/selfprofile/attempt.php', ['id' => $cm->id]);
$reporturl = new moodle_url('/mod/selfprofile/report.php', ['id' => $cm->id]);

if (empty($selfprofile->showresults) && !$viewingotheruser && !has_capability('mod/selfprofile:viewreports', $context)) {
    echo $OUTPUT->notification(get_string('resultsnotavailable', 'mod_selfprofile'), 'info');
    echo $OUTPUT->continue_button($viewurl);
    echo $OUTPUT->footer();
    exit;
}

if (!$attempt) {
    $attempt = $DB->get_record(
        'selfprofile_attempts',
        ['selfprofileid' => $selfprofile->id, 'userid' => $targetuserid]
    );
}

if (!$attempt) {
    echo $OUTPUT->notification(get_string('resultsnotavailable', 'mod_selfprofile'), 'info');
    echo $OUTPUT->continue_button($viewingotheruser ? $reporturl : $viewurl);
    echo $OUTPUT->footer();
    exit;
}

$enabledstatementcount = $DB->count_records(
    'selfprofile_statements',
    ['selfprofileid' => $selfprofile->id, 'enabled' => 1]
);

$responsecount = $DB->count_records(
    'selfprofile_responses',
    ['attemptid' => $attempt->id]
);

if ($responsecount === 0) {
    echo $OUTPUT->notification(get_string('resultsnotavailable', 'mod_selfprofile'), 'warning');
    echo $OUTPUT->continue_button($viewingotheruser ? $reporturl : $attempturl);
    echo $OUTPUT->footer();
    exit;
}

if ($attempt->status !== 'submitted') {
    echo $OUTPUT->notification(get_string('resultsavailableaftersubmission', 'mod_selfprofile'), 'info');

    $progressdata = (object) [
        'answered' => $responsecount,
        'total' => $enabledstatementcount,
    ];
    echo $OUTPUT->box(get_string('answeredcount', 'mod_selfprofile', $progressdata), 'generalbox selfprofile-progress');

    echo $OUTPUT->continue_button($viewingotheruser ? $reporturl : $attempturl);
    echo $OUTPUT->footer();
    exit;
}

if ($responsecount < $enabledstatementcount) {
    echo $OUTPUT->notification(get_string('notallstatementsanswered', 'mod_selfprofile'), 'warning');

    $progressdata = (object) [
        'answered' => $responsecount,
        'total' => $enabledstatementcount,
    ];
    echo $OUTPUT->box(get_string('answeredcount', 'mod_selfprofile', $progressdata), 'generalbox selfprofile-progress');

    echo $OUTPUT->continue_button($viewingotheruser ? $reporturl : $attempturl);
    echo $OUTPUT->footer();
    exit;
}

$scale = json_decode($selfprofile->scalejson ?? '');
$minscale = 0;
$maxscale = 5;
$scalerange = 5;

if (json_last_error() === JSON_ERROR_NONE && is_array($scale) && !empty($scale)) {
    $values = [];

    foreach ($scale as $scaleitem) {
        if (is_object($scaleitem) && isset($scaleitem->value) && is_numeric($scaleitem->value)) {
            $values[] = (int) $scaleitem->value;
        }
    }

    if (!empty($values)) {
        $minscale = min($values);
        $maxscale = max($values);
        $scalerange = max(1, $maxscale - $minscale);
    }
}

$sql = "SELECT c.id,
               c.name,
               c.description,
               AVG(CASE
                   WHEN s.scoringdirection = 'reverse'
                   THEN (:scalemax + :scalemin - r.scalevalue)
                   ELSE r.scalevalue
               END) AS averagescore,
               COUNT(r.id) AS responsecount
          FROM {selfprofile_categories} c
          JOIN {selfprofile_statements} s
            ON s.categoryid = c.id
           AND s.enabled = 1
          JOIN {selfprofile_responses} r
            ON r.statementid = s.id
           AND r.attemptid = :attemptid
         WHERE c.selfprofileid = :selfprofileid
      GROUP BY c.id, c.name, c.description, c.sortorder
      ORDER BY averagescore DESC, c.sortorder ASC";

$results = $DB->get_records_sql($sql, [
    'attemptid' => $attempt->id,
    'selfprofileid' => $selfprofile->id,
    'scalemax' => $maxscale,
    'scalemin' => $minscale,
]);

if (empty($results)) {
    echo $OUTPUT->notification(get_string('resultsnotavailable', 'mod_selfprofile'), 'info');
    echo $OUTPUT->continue_button($viewingotheruser ? $reporturl : $viewurl);
    echo $OUTPUT->footer();
    exit;
}

if (!empty($selfprofile->resultpreamble)) {
    echo $OUTPUT->box_start('generalbox selfprofile-result-preamble');
    echo $OUTPUT->heading(get_string('howtoreadresults', 'mod_selfprofile'), 3);
    echo format_text($selfprofile->resultpreamble, $selfprofile->resultpreambleformat, ['context' => $context]);
    echo $OUTPUT->box_end();
}

if ($viewingotheruser) {
    $submittedtext = '-';
    if (!empty($attempt->timesubmitted)) {
        $submittedtext = userdate($attempt->timesubmitted);
    }

    echo $OUTPUT->box(
        get_string('student', 'mod_selfprofile') . ': ' . fullname($targetuser) . html_writer::empty_tag('br') .
        get_string('timesubmitted', 'mod_selfprofile') . ': ' . $submittedtext,
        'generalbox selfprofile-result-user-summary'
    );
}

echo $OUTPUT->box(get_string('highesttolowest', 'mod_selfprofile'), 'generalbox selfprofile-results-intro');

$table = new html_table();
$table->head = [
    get_string('rank', 'mod_selfprofile'),
    get_string('category', 'mod_selfprofile'),
    get_string('averagescore', 'mod_selfprofile'),
    get_string('categoryscore', 'mod_selfprofile'),
];
$table->attributes['class'] = 'generaltable selfprofile-results-table';

$rank = 1;

foreach ($results as $result) {
    if ((int) $result->responsecount === 0 || $result->averagescore === null) {
        continue;
    }

    $average = round((float) $result->averagescore, 2);

    $progressvalue = max(0, $average - $minscale);

    $progress = html_writer::tag(
        'progress',
        s((string) $average),
        [
            'value' => $progressvalue,
            'max' => $scalerange,
            'class' => 'selfprofile-result-progress',
        ]
    );

    $categoryname = format_string($result->name);

    if (!empty($result->description)) {
        $categoryname .= html_writer::tag(
            'div',
            s($result->description),
            ['class' => 'selfprofile-category-description']
        );
    }

    $table->data[] = [
        $rank,
        $categoryname,
        format_float($average, 2),
        $progress,
    ];

    $rank++;
}

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('resultsnotavailable', 'mod_selfprofile'), 'info');
    echo $OUTPUT->continue_button($viewingotheruser ? $reporturl : $viewurl);
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::table($table);

$buttons = [];

$pdfparams = ['id' => $cm->id];
if ($attemptid > 0) {
    $pdfparams['attemptid'] = $attemptid;
}
$pdfurl = new moodle_url('/mod/selfprofile/pdf.php', $pdfparams);
$buttons[] = html_writer::link(
    $pdfurl,
    get_string('downloadpdf', 'mod_selfprofile'),
    ['class' => 'btn btn-primary']
);

if ($viewingotheruser) {
    $buttons[] = html_writer::link(
        $reporturl,
        get_string('reports', 'mod_selfprofile'),
        ['class' => 'btn btn-secondary']
    );
}

$buttons[] = html_writer::link(
    $viewurl,
    get_string('viewactivity', 'mod_selfprofile'),
    ['class' => 'btn btn-secondary']
);

echo html_writer::div(implode(' ', $buttons), 'selfprofile-actions');

echo $OUTPUT->footer();
