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
 * JSON export page for the SelfProfile activity module.
 *
 * @package    mod_selfprofile
 * @copyright  2026 Johan Venter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/selfprofile/lib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('selfprofile', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$selfprofile = $DB->get_record('selfprofile', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/selfprofile:export', $context);
require_sesskey();

$categories = $DB->get_records(
    'selfprofile_categories',
    ['selfprofileid' => $selfprofile->id],
    'sortorder ASC, id ASC'
);

$statements = $DB->get_records(
    'selfprofile_statements',
    ['selfprofileid' => $selfprofile->id, 'enabled' => 1],
    'sortorder ASC, id ASC'
);

$statementsbycategory = [];

foreach ($statements as $statement) {
    if (!isset($statementsbycategory[$statement->categoryid])) {
        $statementsbycategory[$statement->categoryid] = [];
    }

    $statementid = $statement->importid ?? '';

    if ($statementid === '') {
        $statementid = 'statement_' . $statement->id;
    }

    $scoringdirection = $statement->scoringdirection ?? 'normal';
    if (!in_array($scoringdirection, ['normal', 'reverse'], true)) {
        $scoringdirection = 'normal';
    }

    $statementsbycategory[$statement->categoryid][] = [
        'id' => $statementid,
        'text' => $statement->statement,
        'scoringdirection' => $scoringdirection,
    ];
}

$scale = json_decode($selfprofile->scalejson ?? '');

if (json_last_error() !== JSON_ERROR_NONE || !is_array($scale)) {
    $scale = [];
}

$exportcategories = [];

foreach ($categories as $category) {
    $exportcategories[] = [
        'id' => $category->shortname,
        'name' => $category->name,
        'description' => $category->description ?? '',
        'statements' => $statementsbycategory[$category->id] ?? [],
    ];
}

$export = [
    'schema' => 'mod_selfprofile',
    'schemaVersion' => 2,
    'name' => $selfprofile->name,
    'instructions' => $selfprofile->instructions ?? '',
    'resultpreamble' => $selfprofile->resultpreamble ?? '',
    'scale' => $scale,
    'categories' => $exportcategories,
    'metadata' => [
        'exportedFrom' => 'mod_selfprofile',
        'exportedAt' => gmdate('c'),
        'containsStudentData' => false,
    ],
];

$json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($json === false) {
    throw new moodle_exception('jsonexportnotavailable', 'mod_selfprofile');
}

$filenamebase = clean_param($selfprofile->name, PARAM_FILE);
if ($filenamebase === '') {
    $filenamebase = 'selfprofile';
}

$filename = $filenamebase . '-selfprofile-v2.json';

\mod_selfprofile\event\json_exported::create([
    'objectid' => $selfprofile->id,
    'context' => $context,
])->trigger();

send_file($json, $filename, 0, 0, true, true, 'application/json');
