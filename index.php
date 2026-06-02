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
 * Course index page for the SelfProfile activity module.
 *
 * @package    mod_selfprofile
 * @copyright  2026 Johan Venter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$coursecontext = context_course::instance($course->id);

$PAGE->set_url('/mod/selfprofile/index.php', ['id' => $course->id]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('modulenameplural', 'mod_selfprofile'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('modulenameplural', 'mod_selfprofile'));

echo $OUTPUT->header();

$instances = get_all_instances_in_course('selfprofile', $course);

if (empty($instances)) {
    echo $OUTPUT->notification(get_string('noactivitycontent', 'mod_selfprofile'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('name', 'mod_selfprofile'),
    get_string('summary'),
];
$table->attributes['class'] = 'generaltable mod_index';

foreach ($instances as $instance) {
    $modulecontext = context_module::instance($instance->coursemodule);

    if (!has_capability('mod/selfprofile:view', $modulecontext)) {
        continue;
    }

    $link = html_writer::link(
        new moodle_url('/mod/selfprofile/view.php', ['id' => $instance->coursemodule]),
        format_string($instance->name)
    );

    $summary = '';
    if (!empty($instance->intro)) {
        $summary = format_module_intro('selfprofile', $instance, $instance->coursemodule);
    }

    $table->data[] = [$link, $summary];
}

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('noactivitycontent', 'mod_selfprofile'), 'info');
} else {
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
