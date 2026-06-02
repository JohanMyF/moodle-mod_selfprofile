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
 * Library functions for the SelfProfile activity module.
 *
 * @package    mod_selfprofile
 * @copyright  2026 Johan Venter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Adds a new SelfProfile activity instance.
 *
 * @param stdClass $data Form data.
 * @param mod_selfprofile_mod_form|null $mform Moodle form.
 * @return int The new activity instance id.
 */
function selfprofile_add_instance(stdClass $data, ?mod_selfprofile_mod_form $mform = null): int {
    global $DB;

    $now = time();

    selfprofile_prepare_editor_fields($data);

    $record = new stdClass();
    $record->course = $data->course;
    $record->name = $data->name;
    $record->intro = $data->intro ?? '';
    $record->introformat = $data->introformat ?? FORMAT_HTML;
    $record->instructions = $data->instructions ?? '';
    $record->instructionsformat = $data->instructionsformat ?? FORMAT_HTML;
    $record->resultpreamble = $data->resultpreamble ?? '';
    $record->resultpreambleformat = $data->resultpreambleformat ?? FORMAT_HTML;
    $record->scalejson = '';
    $record->configjson = '';
    $record->showresults = !empty($data->showresults) ? 1 : 0;
    $record->timecreated = $now;
    $record->timemodified = $now;

    $id = $DB->insert_record('selfprofile', $record);

    $json = selfprofile_get_submitted_instrument_json($data);

    if ($json !== '') {
        selfprofile_import_json_from_form($id, $json);
    }

    return $id;
}

/**
 * Updates an existing SelfProfile activity instance.
 *
 * @param stdClass $data Form data.
 * @param mod_selfprofile_mod_form|null $mform Moodle form.
 * @return bool
 */
function selfprofile_update_instance(stdClass $data, ?mod_selfprofile_mod_form $mform = null): bool {
    global $DB;

    selfprofile_prepare_editor_fields($data);

    $record = new stdClass();
    $record->id = $data->instance;
    $record->course = $data->course;
    $record->name = $data->name;
    $record->intro = $data->intro ?? '';
    $record->introformat = $data->introformat ?? FORMAT_HTML;
    $record->instructions = $data->instructions ?? '';
    $record->instructionsformat = $data->instructionsformat ?? FORMAT_HTML;
    $record->resultpreamble = $data->resultpreamble ?? '';
    $record->resultpreambleformat = $data->resultpreambleformat ?? FORMAT_HTML;
    $record->showresults = !empty($data->showresults) ? 1 : 0;
    $record->timemodified = time();

    $DB->update_record('selfprofile', $record);

    $json = selfprofile_get_submitted_instrument_json($data);

    if ($json !== '') {
        selfprofile_import_json_from_form($record->id, $json);
    }

    return true;
}

/**
 * Deletes a SelfProfile activity instance.
 *
 * @param int $id Activity instance id.
 * @return bool
 */
function selfprofile_delete_instance(int $id): bool {
    global $DB;

    if (!$DB->record_exists('selfprofile', ['id' => $id])) {
        return false;
    }

    $attemptids = $DB->get_fieldset_select(
        'selfprofile_attempts',
        'id',
        'selfprofileid = ?',
        [$id]
    );

    if (!empty($attemptids)) {
        list($attemptsql, $attemptparams) = $DB->get_in_or_equal($attemptids, SQL_PARAMS_QM);
        $DB->delete_records_select('selfprofile_responses', 'attemptid ' . $attemptsql, $attemptparams);
    }

    $DB->delete_records('selfprofile_attempts', ['selfprofileid' => $id]);
    $DB->delete_records('selfprofile_statements', ['selfprofileid' => $id]);
    $DB->delete_records('selfprofile_categories', ['selfprofileid' => $id]);
    $DB->delete_records('selfprofile', ['id' => $id]);

    return true;
}

/**
 * Indicates support for Moodle features.
 *
 * @param string $feature Feature name.
 * @return bool|null
 */
function selfprofile_supports(string $feature): ?bool {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
            return true;

        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return false;

        default:
            return null;
    }
}

/**
 * Serves plugin files.
 *
 * This first version does not serve uploaded plugin files.
 *
 * @param stdClass $course Course record.
 * @param stdClass $cm Course module record.
 * @param context $context Context.
 * @param string $filearea File area.
 * @param array $args File arguments.
 * @param bool $forcedownload Whether the file should be forced to download.
 * @param array $options Additional options.
 * @return false
 */
function selfprofile_pluginfile(
    stdClass $course,
    stdClass $cm,
    context $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
): bool {
    return false;
}

/**
 * Converts editor fields from mod_form into database fields.
 *
 * @param stdClass $data Form data.
 * @return void
 */
function selfprofile_prepare_editor_fields(stdClass $data): void {
    if (isset($data->instructions_editor) && is_array($data->instructions_editor)) {
        $data->instructions = $data->instructions_editor['text'] ?? '';
        $data->instructionsformat = $data->instructions_editor['format'] ?? FORMAT_HTML;
    }

    if (isset($data->resultpreamble_editor) && is_array($data->resultpreamble_editor)) {
        $data->resultpreamble = $data->resultpreamble_editor['text'] ?? '';
        $data->resultpreambleformat = $data->resultpreamble_editor['format'] ?? FORMAT_HTML;
    }
}

/**
 * Gets the submitted instrument JSON from pasted JSON, uploaded JSON, or teacher builder fields.
 *
 * @param stdClass $data Form data.
 * @return string JSON string, or empty string if no instrument update was submitted.
 */
function selfprofile_get_submitted_instrument_json(stdClass $data): string {
    $json = trim((string) ($data->jsonimport ?? ''));

    if ($json !== '') {
        return $json;
    }

    if (!empty($data->jsonfile)) {
        $json = selfprofile_get_draft_file_content((int) $data->jsonfile);
        if (trim($json) !== '') {
            return trim($json);
        }
    }

    $scalebuilder = trim((string) ($data->scalebuilder ?? ''));
    $instrumentbuilder = trim((string) ($data->instrumentbuilder ?? ''));

    if ($scalebuilder !== '' || $instrumentbuilder !== '') {
        $built = selfprofile_build_json_from_teacher_fields(
            $data->name ?? 'SelfProfile',
            $data->instructions ?? '',
            $data->resultpreamble ?? '',
            $scalebuilder,
            $instrumentbuilder
        );

        if (empty($built['error']) && !empty($built['json'])) {
            return $built['json'];
        }
    }

    return '';
}

/**
 * Reads an uploaded JSON file from a Moodle draft file area.
 *
 * @param int $draftitemid Draft item id.
 * @return string File content or empty string.
 */
function selfprofile_get_draft_file_content(int $draftitemid): string {
    global $USER;

    if ($draftitemid <= 0 || empty($USER->id)) {
        return '';
    }

    $fs = get_file_storage();
    $usercontext = context_user::instance($USER->id);

    $files = $fs->get_area_files(
        $usercontext->id,
        'user',
        'draft',
        $draftitemid,
        'id DESC',
        false
    );

    foreach ($files as $file) {
        if (!$file->is_directory()) {
            return $file->get_content();
        }
    }

    return '';
}

/**
 * Builds schemaVersion 2 JSON from teacher-friendly text fields.
 *
 * Scale format:
 * value|label
 *
 * Instrument format:
 * category|categoryid|Category name|Description
 * statement|categoryid|normal|Statement text
 * statement|categoryid|statementid|reverse|Statement text
 *
 * @param string $name Activity name.
 * @param string $instructions Student instructions.
 * @param string $resultpreamble Results preamble.
 * @param string $scalebuilder Scale lines.
 * @param string $instrumentbuilder Instrument lines.
 * @return array
 */
function selfprofile_build_json_from_teacher_fields(
    string $name,
    string $instructions,
    string $resultpreamble,
    string $scalebuilder,
    string $instrumentbuilder
): array {
    $scale = [];
    $scalevalues = [];

    foreach (preg_split('/\R/u', trim($scalebuilder)) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $parts = array_map('trim', explode('|', $line, 2));

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '' || !is_numeric($parts[0])) {
            return ['error' => 'invalidscalebuilder', 'field' => 'scalebuilder'];
        }

        $value = (int) $parts[0];

        if (isset($scalevalues[$value])) {
            return ['error' => 'invalidscalebuilder', 'field' => 'scalebuilder'];
        }

        $scalevalues[$value] = true;

        $scale[] = [
            'value' => $value,
            'label' => clean_param($parts[1], PARAM_TEXT),
        ];
    }

    if (count($scale) < 2 || count($scale) > 10) {
        return ['error' => 'invalidscalebuilder', 'field' => 'scalebuilder'];
    }

    usort($scale, static function(array $a, array $b): int {
        return $a['value'] <=> $b['value'];
    });

    $values = array_column($scale, 'value');
    $step = null;
    for ($i = 1; $i < count($values); $i++) {
        $currentstep = $values[$i] - $values[$i - 1];

        if ($currentstep <= 0) {
            return ['error' => 'invalidscalebuilder', 'field' => 'scalebuilder'];
        }

        if ($step === null) {
            $step = $currentstep;
        } else if ($step !== $currentstep) {
            return ['error' => 'invalidscalebuilder', 'field' => 'scalebuilder'];
        }
    }

    $categories = [];
    $categoryorder = [];
    $statementids = [];

    foreach (preg_split('/\R/u', trim($instrumentbuilder)) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $parts = array_map('trim', explode('|', $line));
        $type = strtolower($parts[0] ?? '');

        if ($type === 'category') {
            if (count($parts) < 4) {
                return ['error' => 'invalidinstrumentbuilder', 'field' => 'instrumentbuilder'];
            }

            $categoryid = selfprofile_make_shortname($parts[1]);

            if ($categoryid === '' || isset($categories[$categoryid])) {
                return ['error' => 'invalidinstrumentbuilder', 'field' => 'instrumentbuilder'];
            }

            $categories[$categoryid] = [
                'id' => $categoryid,
                'name' => clean_param($parts[2], PARAM_TEXT),
                'description' => clean_param($parts[3], PARAM_TEXT),
                'statements' => [],
            ];
            $categoryorder[] = $categoryid;
            continue;
        }

        if ($type === 'statement') {
            if (count($parts) < 4) {
                return ['error' => 'invalidinstrumentbuilder', 'field' => 'instrumentbuilder'];
            }

            $categoryid = selfprofile_make_shortname($parts[1]);

            if (!isset($categories[$categoryid])) {
                return ['error' => 'invalidinstrumentbuilder', 'field' => 'instrumentbuilder'];
            }

            if (in_array(strtolower($parts[2]), ['normal', 'reverse'], true)) {
                $direction = strtolower($parts[2]);
                $text = implode('|', array_slice($parts, 3));
                $statementid = selfprofile_make_shortname($categoryid . '_' . (count($categories[$categoryid]['statements']) + 1));
            } else {
                if (count($parts) < 5) {
                    return ['error' => 'invalidinstrumentbuilder', 'field' => 'instrumentbuilder'];
                }
                $statementid = selfprofile_make_shortname($parts[2]);
                $direction = strtolower($parts[3]);
                $text = implode('|', array_slice($parts, 4));
            }

            if (!in_array($direction, ['normal', 'reverse'], true)) {
                return ['error' => 'invalidscoringdirection', 'field' => 'instrumentbuilder'];
            }

            if ($statementid === '' || isset($statementids[$statementid])) {
                return ['error' => 'invalidinstrumentbuilder', 'field' => 'instrumentbuilder'];
            }

            $statementids[$statementid] = true;
            $text = clean_param($text, PARAM_TEXT);

            if ($text === '') {
                return ['error' => 'invalidinstrumentbuilder', 'field' => 'instrumentbuilder'];
            }

            $categories[$categoryid]['statements'][] = [
                'id' => $statementid,
                'text' => $text,
                'scoringdirection' => $direction,
            ];
            continue;
        }

        return ['error' => 'invalidinstrumentbuilder', 'field' => 'instrumentbuilder'];
    }

    if (empty($categories)) {
        return ['error' => 'missingcategories', 'field' => 'instrumentbuilder'];
    }

    $orderedcategories = [];
    foreach ($categoryorder as $categoryid) {
        if (empty($categories[$categoryid]['statements'])) {
            return ['error' => 'missingstatements', 'field' => 'instrumentbuilder'];
        }
        $orderedcategories[] = $categories[$categoryid];
    }

    $export = [
        'schema' => 'mod_selfprofile',
        'schemaVersion' => 2,
        'name' => clean_param($name, PARAM_TEXT),
        'instructions' => clean_text($instructions),
        'resultpreamble' => clean_text($resultpreamble),
        'scale' => $scale,
        'categories' => $orderedcategories,
        'metadata' => [
            'createdBy' => 'mod_selfprofile_form_builder',
            'containsStudentData' => false,
        ],
    ];

    $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        return ['error' => 'invalidjson', 'field' => 'instrumentbuilder'];
    }

    return ['json' => $json];
}

/**
 * Validates schemaVersion 2 JSON.
 *
 * @param string $json Raw JSON.
 * @return string Empty string if valid, otherwise language string key.
 */
function selfprofile_validate_schema_v2_json(string $json): string {
    $decoded = json_decode(trim($json));

    if (json_last_error() !== JSON_ERROR_NONE || !is_object($decoded)) {
        return 'invalidjson';
    }

    if (empty($decoded->schema) || $decoded->schema !== 'mod_selfprofile') {
        return 'invalidjsonschema';
    }

    if (empty($decoded->schemaVersion) || (int) $decoded->schemaVersion !== 2) {
        return 'invalidjsonschema';
    }

    if (empty($decoded->scale) || !is_array($decoded->scale) || count($decoded->scale) < 2 || count($decoded->scale) > 10) {
        return 'missingscale';
    }

    $scalevalues = [];
    foreach ($decoded->scale as $scaleitem) {
        if (!is_object($scaleitem) || !isset($scaleitem->value) || !isset($scaleitem->label) || !is_numeric($scaleitem->value)) {
            return 'missingscale';
        }

        $value = (int) $scaleitem->value;
        if (isset($scalevalues[$value])) {
            return 'missingscale';
        }
        $scalevalues[$value] = true;
    }

    if (empty($decoded->categories) || !is_array($decoded->categories) || count($decoded->categories) > 40) {
        return 'missingcategories';
    }

    $statementcount = 0;
    $usedcategoryids = [];
    $usedstatementids = [];

    foreach ($decoded->categories as $category) {
        if (!is_object($category) || empty($category->id) || empty($category->name)) {
            return 'missingcategories';
        }

        $categoryid = selfprofile_make_shortname($category->id);
        if ($categoryid === '' || isset($usedcategoryids[$categoryid])) {
            return 'missingcategories';
        }
        $usedcategoryids[$categoryid] = true;

        if (empty($category->statements) || !is_array($category->statements)) {
            return 'missingstatements';
        }

        foreach ($category->statements as $statement) {
            $statementcount++;

            if (!is_object($statement) || empty($statement->id) || empty($statement->text)) {
                return 'missingstatements';
            }

            $statementid = selfprofile_make_shortname($statement->id);
            if ($statementid === '' || isset($usedstatementids[$statementid])) {
                return 'missingstatements';
            }
            $usedstatementids[$statementid] = true;

            $direction = selfprofile_clean_scoringdirection($statement->scoringdirection ?? 'normal');
            if (!in_array($direction, ['normal', 'reverse'], true)) {
                return 'invalidscoringdirection';
            }
        }
    }

    if ($statementcount > 500) {
        return 'toomanystatements';
    }

    return '';
}

/**
 * Imports a schemaVersion 2 SelfProfile JSON structure submitted through the activity form.
 *
 * @param int $selfprofileid Activity instance id.
 * @param string $json Raw JSON from the form.
 * @return void
 */
function selfprofile_import_json_from_form(int $selfprofileid, string $json): void {
    global $DB;

    $json = trim($json);

    if (selfprofile_validate_schema_v2_json($json) !== '') {
        return;
    }

    $decoded = json_decode($json);

    $transaction = $DB->start_delegated_transaction();

    $DB->delete_records('selfprofile_statements', ['selfprofileid' => $selfprofileid]);
    $DB->delete_records('selfprofile_categories', ['selfprofileid' => $selfprofileid]);

    $scalejson = json_encode($decoded->scale);

    $update = new stdClass();
    $update->id = $selfprofileid;
    $update->scalejson = $scalejson ?: '';
    $update->configjson = $json;
    $update->timemodified = time();
    $DB->update_record('selfprofile', $update);

    $categorysort = 0;

    foreach ($decoded->categories as $category) {
        $categoryrecord = new stdClass();
        $categoryrecord->selfprofileid = $selfprofileid;
        $categoryrecord->shortname = selfprofile_make_shortname($category->id);
        $categoryrecord->name = clean_param((string) $category->name, PARAM_TEXT);
        $categoryrecord->description = clean_param((string) ($category->description ?? ''), PARAM_TEXT);
        $categoryrecord->sortorder = $categorysort++;
        $categoryrecord->timecreated = time();
        $categoryrecord->timemodified = time();

        $categoryid = $DB->insert_record('selfprofile_categories', $categoryrecord);

        $statementsort = 0;

        foreach ($category->statements as $statement) {
            $statementrecord = new stdClass();
            $statementrecord->selfprofileid = $selfprofileid;
            $statementrecord->categoryid = $categoryid;
            $statementrecord->importid = selfprofile_make_shortname($statement->id);
            $statementrecord->statement = clean_param((string) $statement->text, PARAM_TEXT);
            $statementrecord->scoringdirection = selfprofile_clean_scoringdirection($statement->scoringdirection ?? 'normal');
            $statementrecord->sortorder = $statementsort++;
            $statementrecord->enabled = 1;
            $statementrecord->timecreated = time();
            $statementrecord->timemodified = time();

            $DB->insert_record('selfprofile_statements', $statementrecord);
        }
    }

    $transaction->allow_commit();
}

/**
 * Cleans and normalises scoring direction.
 *
 * @param mixed $value Raw scoring direction.
 * @return string
 */
function selfprofile_clean_scoringdirection($value): string {
    $value = clean_param((string) $value, PARAM_ALPHA);
    $value = strtolower($value);

    if ($value === 'reverse') {
        return 'reverse';
    }

    return 'normal';
}

/**
 * Creates a stable shortname from imported JSON id values.
 *
 * @param mixed $value Raw id or name.
 * @return string
 */
function selfprofile_make_shortname($value): string {
    $shortname = clean_param((string) $value, PARAM_ALPHANUMEXT);
    $shortname = strtolower($shortname);

    if ($shortname === '') {
        $shortname = 'item';
    }

    return substr($shortname, 0, 100);
}
