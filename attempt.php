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
 * Student attempt page for the SelfProfile activity module.
 *
 * @package    mod_selfprofile
 * @copyright  2026 Johan Venter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/selfprofile/lib.php');

$id = required_param('id', PARAM_INT);
$position = optional_param('position', 0, PARAM_INT);

$cm = get_coursemodule_from_id('selfprofile', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$selfprofile = $DB->get_record('selfprofile', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/selfprofile:submit', $context);

$PAGE->set_url('/mod/selfprofile/attempt.php', ['id' => $cm->id, 'position' => $position]);
$PAGE->set_title(format_string($selfprofile->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$statements = $DB->get_records(
    'selfprofile_statements',
    ['selfprofileid' => $selfprofile->id, 'enabled' => 1],
    'id ASC'
);

if (empty($statements)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($selfprofile->name));
    echo $OUTPUT->notification(get_string('nostatementsavailable', 'mod_selfprofile'), 'warning');
    echo $OUTPUT->continue_button(new moodle_url('/mod/selfprofile/view.php', ['id' => $cm->id]));
    echo $OUTPUT->footer();
    exit;
}

$scale = json_decode($selfprofile->scalejson ?? '');

if (json_last_error() !== JSON_ERROR_NONE || !is_array($scale) || empty($scale)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($selfprofile->name));
    echo $OUTPUT->notification(get_string('missingscale', 'mod_selfprofile'), 'warning');
    echo $OUTPUT->continue_button(new moodle_url('/mod/selfprofile/view.php', ['id' => $cm->id]));
    echo $OUTPUT->footer();
    exit;
}

$attempt = $DB->get_record(
    'selfprofile_attempts',
    ['selfprofileid' => $selfprofile->id, 'userid' => $USER->id]
);

if (!$attempt) {
    $statementids = array_keys($statements);
    shuffle($statementids);

    $attemptrecord = new stdClass();
    $attemptrecord->selfprofileid = $selfprofile->id;
    $attemptrecord->userid = $USER->id;
    $attemptrecord->status = 'inprogress';
    $attemptrecord->statementorderjson = json_encode(array_values($statementids));
    $attemptrecord->timecreated = time();
    $attemptrecord->timemodified = time();
    $attemptrecord->timesubmitted = null;

    $attemptid = $DB->insert_record('selfprofile_attempts', $attemptrecord);
    $attempt = $DB->get_record('selfprofile_attempts', ['id' => $attemptid], '*', MUST_EXIST);
}

if ($attempt->status === 'submitted') {
    redirect(new moodle_url('/mod/selfprofile/results.php', ['id' => $cm->id]));
}

$statementorder = json_decode($attempt->statementorderjson ?? '');

if (json_last_error() !== JSON_ERROR_NONE || !is_array($statementorder) || empty($statementorder)) {
    $statementorder = array_keys($statements);
    shuffle($statementorder);

    $attempt->statementorderjson = json_encode(array_values($statementorder));
    $attempt->timemodified = time();
    $DB->update_record('selfprofile_attempts', $attempt);
}

$statementorder = array_values(array_filter($statementorder, static function($statementid) use ($statements): bool {
    return isset($statements[$statementid]);
}));

if (empty($statementorder)) {
    $statementorder = array_keys($statements);
    shuffle($statementorder);

    $attempt->statementorderjson = json_encode(array_values($statementorder));
    $attempt->timemodified = time();
    $DB->update_record('selfprofile_attempts', $attempt);
}

$total = count($statementorder);

if ($total === 0) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($selfprofile->name));
    echo $OUTPUT->notification(get_string('nostatementsavailable', 'mod_selfprofile'), 'warning');
    echo $OUTPUT->continue_button(new moodle_url('/mod/selfprofile/view.php', ['id' => $cm->id]));
    echo $OUTPUT->footer();
    exit;
}

if ($position < 0) {
    $position = 0;
}

if ($position >= $total) {
    $position = $total - 1;
}

$responses = $DB->get_records_menu(
    'selfprofile_responses',
    ['attemptid' => $attempt->id],
    '',
    'statementid, scalevalue'
);

if (optional_param('saveanswer', 0, PARAM_BOOL) && confirm_sesskey()) {
    if ($attempt->status === 'submitted') {
        redirect(
            new moodle_url('/mod/selfprofile/attempt.php', ['id' => $cm->id, 'position' => $position]),
            get_string('changeslocked', 'mod_selfprofile'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }

    $statementid = required_param('statementid', PARAM_INT);
    $scalevalue = required_param('scalevalue', PARAM_INT);

    if (!isset($statements[$statementid])) {
        throw new moodle_exception('invalidrecord', 'error');
    }

    $allowedvalues = [];
    foreach ($scale as $scaleitem) {
        if (is_object($scaleitem) && isset($scaleitem->value)) {
            $allowedvalues[] = (int) $scaleitem->value;
        }
    }

    if (!in_array($scalevalue, $allowedvalues, true)) {
        throw new moodle_exception('invaliddata', 'error');
    }

    $existing = $DB->get_record(
        'selfprofile_responses',
        ['attemptid' => $attempt->id, 'statementid' => $statementid]
    );

    if ($existing) {
        $existing->scalevalue = $scalevalue;
        $existing->timemodified = time();
        $DB->update_record('selfprofile_responses', $existing);
    } else {
        $responserecord = new stdClass();
        $responserecord->attemptid = $attempt->id;
        $responserecord->statementid = $statementid;
        $responserecord->scalevalue = $scalevalue;
        $responserecord->timecreated = time();
        $responserecord->timemodified = time();
        $DB->insert_record('selfprofile_responses', $responserecord);
    }

    $attempt->timemodified = time();
    $DB->update_record('selfprofile_attempts', $attempt);

    $nextposition = min($position + 1, $total - 1);

    redirect(
        new moodle_url('/mod/selfprofile/attempt.php', ['id' => $cm->id, 'position' => $nextposition]),
        get_string('responsesaved', 'mod_selfprofile'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if (optional_param('submitattempt', 0, PARAM_BOOL) && confirm_sesskey()) {
    if (empty($statementorder)) {
        $answeredcurrent = 0;
    } else {
        [$statementsql, $statementparams] = $DB->get_in_or_equal(
            array_map('intval', $statementorder),
            SQL_PARAMS_NAMED,
            'statementid'
        );
        $countparams = array_merge(['attemptid' => $attempt->id], $statementparams);

        $answeredcurrent = $DB->count_records_select(
            'selfprofile_responses',
            "attemptid = :attemptid AND statementid {$statementsql}",
            $countparams
        );
    }

    if ($answeredcurrent < $total) {
        redirect(
            new moodle_url('/mod/selfprofile/attempt.php', ['id' => $cm->id, 'position' => $position]),
            get_string('notallstatementsanswered', 'mod_selfprofile'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }

    if ($attempt->status !== 'submitted') {
        $attempt->status = 'submitted';
        $attempt->timemodified = time();
        $attempt->timesubmitted = time();
        $DB->update_record('selfprofile_attempts', $attempt);
    }

    redirect(
        new moodle_url('/mod/selfprofile/results.php', ['id' => $cm->id]),
        get_string('activitysubmitted', 'mod_selfprofile'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$responses = $DB->get_records_menu(
    'selfprofile_responses',
    ['attemptid' => $attempt->id],
    '',
    'statementid, scalevalue'
);

$currentresponses = array_intersect_key($responses, array_flip($statementorder));
$answered = count($currentresponses);
$currentstatementid = $statementorder[$position];
$currentstatement = $statements[$currentstatementid];

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($selfprofile->name));

if (!empty($selfprofile->instructions)) {
    echo $OUTPUT->box_start('generalbox selfprofile-instructions');
    echo $OUTPUT->heading(get_string('instructions', 'mod_selfprofile'), 3);
    echo format_text($selfprofile->instructions, $selfprofile->instructionsformat, ['context' => $context]);
    echo $OUTPUT->box_end();
}

$progressdata = (object) [
    'answered' => $answered,
    'total' => $total,
];

echo $OUTPUT->box(
    get_string('answeredcount', 'mod_selfprofile', $progressdata),
    'generalbox selfprofile-progress'
);

echo $OUTPUT->box_start('generalbox selfprofile-statement-card');
echo $OUTPUT->heading(get_string('statement', 'mod_selfprofile') . ' ' . ($position + 1) . ' / ' . $total, 3);

echo html_writer::tag('p', format_string($currentstatement->statement), ['class' => 'selfprofile-statement']);

$formurl = new moodle_url('/mod/selfprofile/attempt.php', ['id' => $cm->id, 'position' => $position]);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'statementid', 'value' => $currentstatementid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'saveanswer', 'value' => 1]);

echo html_writer::tag('p', get_string('selectresponse', 'mod_selfprofile'));

$currentvalue = $responses[$currentstatementid] ?? null;

foreach ($scale as $scaleitem) {
    if (!is_object($scaleitem) || !isset($scaleitem->value) || !isset($scaleitem->label)) {
        continue;
    }

    $value = (int) $scaleitem->value;
    $radioid = 'selfprofile-scale-' . $value;
    $attributes = [
        'type' => 'radio',
        'name' => 'scalevalue',
        'id' => $radioid,
        'value' => $value,
        'required' => 'required',
    ];

    if ((string) $currentvalue === (string) $value) {
        $attributes['checked'] = 'checked';
    }

    echo html_writer::start_div('form-check');
    echo html_writer::empty_tag('input', $attributes);
    echo ' ';
    echo html_writer::tag(
        'label',
        s((string) $scaleitem->label),
        ['for' => $radioid, 'class' => 'form-check-label']
    );
    echo html_writer::end_div();
}

echo html_writer::empty_tag('br');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('saveandcontinue', 'mod_selfprofile'),
    'class' => 'btn btn-primary',
]);

echo html_writer::end_tag('form');

echo $OUTPUT->box_end();

$navbuttons = [];

if ($position > 0) {
    $previousurl = new moodle_url('/mod/selfprofile/attempt.php', ['id' => $cm->id, 'position' => $position - 1]);
    $navbuttons[] = html_writer::link($previousurl, get_string('previousstatement', 'mod_selfprofile'), ['class' => 'btn btn-secondary']);
}

if ($position < ($total - 1)) {
    $nexturl = new moodle_url('/mod/selfprofile/attempt.php', ['id' => $cm->id, 'position' => $position + 1]);
    $navbuttons[] = html_writer::link($nexturl, get_string('nextstatement', 'mod_selfprofile'), ['class' => 'btn btn-secondary']);
}

if (!empty($navbuttons)) {
    echo html_writer::div(implode(' ', $navbuttons), 'selfprofile-navigation');
}

if ($answered >= $total) {
    $submiturl = new moodle_url('/mod/selfprofile/attempt.php', ['id' => $cm->id, 'position' => $position]);

    echo $OUTPUT->box_start('generalbox selfprofile-submit-box');
    echo html_writer::tag('p', get_string('allstatementsanswered', 'mod_selfprofile'));

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $submiturl]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'submitattempt', 'value' => 1]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('submitactivity', 'mod_selfprofile'),
        'class' => 'btn btn-success',
    ]);
    echo html_writer::end_tag('form');

    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
