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
 * Main view page for the SelfProfile activity module.
 *
 * @package    mod_selfprofile
 * @copyright  2026 Johan Venter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/selfprofile/lib.php');

/**
 * Renders a safe JSON import form for teachers.
 *
 * @param int $cmid Course module id.
 * @param bool $collapsed Whether to collapse the form by default.
 * @return string HTML.
 */
function selfprofile_render_json_import_form(int $cmid, bool $collapsed = false): string {
    $action = new moodle_url('/mod/selfprofile/view.php', ['id' => $cmid]);

    $inner = '';
    $inner .= html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $action,
        'enctype' => 'multipart/form-data',
        'class' => 'selfprofile-json-import-form',
    ]);
    $inner .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $inner .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'importjsonsubmit', 'value' => 1]);

    $inner .= html_writer::tag('p', get_string('jsonimportinstructions', 'mod_selfprofile'));

    $inner .= html_writer::tag('label', get_string('jsonfile', 'mod_selfprofile'), ['for' => 'selfprofile-jsonfile']);
    $inner .= html_writer::empty_tag('input', [
        'type' => 'file',
        'name' => 'jsonfile',
        'id' => 'selfprofile-jsonfile',
        'accept' => 'application/json,.json',
        'class' => 'form-control mb-3',
    ]);

    $inner .= html_writer::tag('label', get_string('jsonpaste', 'mod_selfprofile'), ['for' => 'selfprofile-jsonimport']);
    $inner .= html_writer::tag('textarea', '', [
        'name' => 'jsonimport',
        'id' => 'selfprofile-jsonimport',
        'class' => 'form-control mb-3',
        'rows' => 8,
    ]);

    $confirm = get_string('jsonimportconfirm', 'mod_selfprofile');
    $inner .= html_writer::tag('button', get_string('importjson', 'mod_selfprofile'), [
        'type' => 'submit',
        'class' => 'btn btn-primary',
        'onclick' => 'return confirm(' . json_encode($confirm) . ');',
    ]);
    $inner .= html_writer::end_tag('form');

    if ($collapsed) {
        return html_writer::tag(
            'details',
            html_writer::tag('summary', get_string('importjson', 'mod_selfprofile'), ['class' => 'btn btn-secondary']) .
                html_writer::div($inner, 'mt-3'),
            ['class' => 'selfprofile-json-import-details mt-3']
        );
    }

    return html_writer::div($inner, 'selfprofile-json-import mt-3');
}

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('selfprofile', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$selfprofile = $DB->get_record('selfprofile', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/selfprofile:view', $context);

$PAGE->set_url('/mod/selfprofile/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($selfprofile->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);


$importerror = '';

if (optional_param('importjsonsubmit', 0, PARAM_BOOL)) {
    require_capability('mod/selfprofile:manage', $context);
    require_sesskey();

    $json = trim(optional_param('jsonimport', '', PARAM_NOTAGS));

    if ($json === '' && !empty($_FILES['jsonfile']) && is_uploaded_file($_FILES['jsonfile']['tmp_name'])) {
        if ((int) $_FILES['jsonfile']['size'] <= 1048576) {
            $json = trim(file_get_contents($_FILES['jsonfile']['tmp_name']) ?: '');
        } else {
            $importerror = get_string('jsonfiletoolarge', 'mod_selfprofile');
        }
    }

    if ($json === '' && $importerror === '') {
        $importerror = get_string('jsonimportempty', 'mod_selfprofile');
    }

    if ($json !== '' && $importerror === '') {
        $validationerror = selfprofile_validate_schema_v2_json($json);

        if ($validationerror !== '') {
            $importerror = get_string($validationerror, 'mod_selfprofile');
        } else {
            selfprofile_import_json_from_form($selfprofile->id, $json);
            redirect(
                new moodle_url('/mod/selfprofile/view.php', ['id' => $cm->id]),
                get_string('jsonimported', 'mod_selfprofile'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    }
}

$event = \mod_selfprofile\event\course_module_viewed::create([
    'objectid' => $selfprofile->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('selfprofile', $selfprofile);
$event->trigger();

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($selfprofile->name));

if (!empty($selfprofile->intro)) {
    echo $OUTPUT->box(format_module_intro('selfprofile', $selfprofile, $cm->id), 'generalbox mod_introbox');
}

if (!empty($selfprofile->instructions)) {
    echo $OUTPUT->box_start('generalbox selfprofile-instructions');
    echo $OUTPUT->heading(get_string('instructions', 'mod_selfprofile'), 3);
    echo format_text($selfprofile->instructions, $selfprofile->instructionsformat, ['context' => $context]);
    echo $OUTPUT->box_end();
}

$categorycount = $DB->count_records('selfprofile_categories', ['selfprofileid' => $selfprofile->id]);
$statementcount = $DB->count_records('selfprofile_statements', ['selfprofileid' => $selfprofile->id, 'enabled' => 1]);

if ($categorycount === 0 || $statementcount === 0) {
    echo $OUTPUT->notification(get_string('noactivitycontent', 'mod_selfprofile'), 'warning');

    if (has_capability('mod/selfprofile:manage', $context)) {
        if ($importerror !== '') {
            echo $OUTPUT->notification($importerror, 'error');
        }
        echo selfprofile_render_json_import_form($cm->id);
    }

    echo $OUTPUT->footer();

    exit;
}

if (has_capability('mod/selfprofile:submit', $context)) {
    $attempt = $DB->get_record(
        'selfprofile_attempts',
        ['selfprofileid' => $selfprofile->id, 'userid' => $USER->id]
    );

    echo $OUTPUT->box_start('generalbox selfprofile-student-start');

    if ($attempt && $attempt->status === 'submitted') {
        echo $OUTPUT->notification(get_string('alreadysubmitted', 'mod_selfprofile'), 'success');

        if (!empty($selfprofile->showresults)) {
            $resultsurl = new moodle_url('/mod/selfprofile/results.php', ['id' => $cm->id]);
            echo html_writer::tag('p', get_string('resultsavailableaftersubmission', 'mod_selfprofile'));
            echo html_writer::link($resultsurl, get_string('yourresults', 'mod_selfprofile'), ['class' => 'btn btn-primary']);
        } else {
            echo html_writer::tag('p', get_string('resultsnotavailable', 'mod_selfprofile'));
        }
    } else {
        echo html_writer::tag('p', get_string('changesallowed', 'mod_selfprofile'));

        $starturl = new moodle_url('/mod/selfprofile/attempt.php', ['id' => $cm->id]);
        $label = $attempt ? get_string('continueactivity', 'mod_selfprofile') : get_string('startactivity', 'mod_selfprofile');

        echo html_writer::link($starturl, $label, ['class' => 'btn btn-primary']);
    }

    echo $OUTPUT->box_end();
}

if (has_capability('mod/selfprofile:manage', $context)) {
    echo $OUTPUT->box_start('generalbox selfprofile-teacher-summary');
    echo $OUTPUT->heading(get_string('pluginadministration', 'mod_selfprofile'), 3);

    $summary = html_writer::alist([
        get_string('category', 'mod_selfprofile') . ': ' . $categorycount,
        get_string('statement', 'mod_selfprofile') . ': ' . $statementcount,
    ]);

    echo $summary;

    $buttons = [];

    if (has_capability('mod/selfprofile:viewreports', $context)) {
        $reporturl = new moodle_url('/mod/selfprofile/report.php', ['id' => $cm->id]);
        $buttons[] = html_writer::link(
            $reporturl,
            get_string('selfprofile:viewreports', 'mod_selfprofile'),
            ['class' => 'btn btn-primary']
        );
    }

    if (has_capability('mod/selfprofile:export', $context)) {
        $exporturl = new moodle_url('/mod/selfprofile/export.php', [
            'id' => $cm->id,
            'sesskey' => sesskey(),
        ]);
        $buttons[] = html_writer::link($exporturl, get_string('exportjson', 'mod_selfprofile'), ['class' => 'btn btn-secondary']);
    }

    echo html_writer::div(implode(' ', $buttons), 'selfprofile-actions');

    if ($importerror !== '') {
        echo $OUTPUT->notification($importerror, 'error');
    }
    echo selfprofile_render_json_import_form($cm->id, true);

    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
