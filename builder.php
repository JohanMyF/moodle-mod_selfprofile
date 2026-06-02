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
 * Builder page for the SelfProfile activity module.
 *
 * Stage 6b: accordion builder with always-available edit forms for categories
 * and statements, including moving statements between categories.
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
require_capability('mod/selfprofile:manage', $context);

$PAGE->set_url('/mod/selfprofile/builder.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($selfprofile->name) . ': ' . get_string('instrumentbuilder', 'mod_selfprofile'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$messages = [];
$opencategoryid = optional_param('opencategory', 0, PARAM_INT);
$editstatementid = optional_param('editstatement', 0, PARAM_INT);
$formurl = new moodle_url('/mod/selfprofile/builder.php', ['id' => $cm->id]);

if (optional_param('savescale', 0, PARAM_BOOL)) {
    require_sesskey();

    $scaletext = trim(required_param('scaletext', PARAM_NOTAGS));
    $scaleparse = selfprofile_builder_parse_scale_text($scaletext);

    if (!empty($scaleparse['error'])) {
        $messages[] = ['message' => get_string($scaleparse['error'], 'mod_selfprofile'), 'type' => 'error'];
    } else {
        selfprofile_builder_save_scale($selfprofile, $scaleparse['scale']);
        $selfprofile = $DB->get_record('selfprofile', ['id' => $cm->instance], '*', MUST_EXIST);
        $messages[] = ['message' => get_string('scalesaved', 'mod_selfprofile'), 'type' => 'success'];
    }
}

if (optional_param('addcategory', 0, PARAM_BOOL)) {
    require_sesskey();

    $categoryname = required_param('categoryname', PARAM_TEXT);
    $categoryid = optional_param('categoryid', '', PARAM_TEXT);
    $categorydescription = optional_param('categorydescription', '', PARAM_TEXT);
    $categoryid = selfprofile_builder_make_identifier($categoryid !== '' ? $categoryid : $categoryname);

    if ($categoryid === '' || trim($categoryname) === '') {
        $messages[] = ['message' => get_string('invalidcategorybuilder', 'mod_selfprofile'), 'type' => 'error'];
    } else if ($DB->record_exists('selfprofile_categories', ['selfprofileid' => $selfprofile->id, 'shortname' => $categoryid])) {
        $messages[] = ['message' => get_string('duplicatecategoryid', 'mod_selfprofile'), 'type' => 'error'];
    } else {
        $opencategoryid = selfprofile_builder_add_category($selfprofile, $categoryid, $categoryname, $categorydescription);
        $selfprofile = $DB->get_record('selfprofile', ['id' => $cm->instance], '*', MUST_EXIST);
        $messages[] = ['message' => get_string('categorysaved', 'mod_selfprofile'), 'type' => 'success'];
    }
}

if (optional_param('updatecategory', 0, PARAM_BOOL)) {
    require_sesskey();

    $categorydbid = required_param('categorydbid', PARAM_INT);
    $categoryname = required_param('categoryname', PARAM_TEXT);
    $categoryid = optional_param('categoryid', '', PARAM_TEXT);
    $categorydescription = optional_param('categorydescription', '', PARAM_TEXT);

    $category = $DB->get_record('selfprofile_categories', ['id' => $categorydbid, 'selfprofileid' => $selfprofile->id]);

    if (!$category || trim($categoryname) === '') {
        $messages[] = ['message' => get_string('invalidcategorybuilder', 'mod_selfprofile'), 'type' => 'error'];
    } else {
        $opencategoryid = (int) $category->id;
        $categoryid = selfprofile_builder_make_identifier($categoryid !== '' ? $categoryid : $categoryname);

        if ($categoryid === '') {
            $messages[] = ['message' => get_string('invalidcategorybuilder', 'mod_selfprofile'), 'type' => 'error'];
        } else if (selfprofile_builder_category_identifier_exists($selfprofile->id, $categoryid, $category->id)) {
            $messages[] = ['message' => get_string('duplicatecategoryid', 'mod_selfprofile'), 'type' => 'error'];
        } else {
            selfprofile_builder_update_category($selfprofile, $category, $categoryid, $categoryname, $categorydescription);
            $selfprofile = $DB->get_record('selfprofile', ['id' => $cm->instance], '*', MUST_EXIST);
            $messages[] = ['message' => get_string('categoryupdated', 'mod_selfprofile'), 'type' => 'success'];
        }
    }
}

if (optional_param('addstatement', 0, PARAM_BOOL)) {
    require_sesskey();

    $categoryid = required_param('categorydbid', PARAM_INT);
    $statementtext = required_param('statementtext', PARAM_TEXT);
    $statementimportid = optional_param('statementimportid', '', PARAM_TEXT);
    $scoringdirection = required_param('scoringdirection', PARAM_ALPHA);

    $category = $DB->get_record('selfprofile_categories', ['id' => $categoryid, 'selfprofileid' => $selfprofile->id]);

    if (!$category) {
        $messages[] = ['message' => get_string('invalidstatementbuilder', 'mod_selfprofile'), 'type' => 'error'];
    } else {
        $opencategoryid = (int) $category->id;
        $statementimportid = selfprofile_builder_make_identifier($statementimportid);

        if ($statementimportid === '') {
            $nextnumber = (int) $DB->count_records('selfprofile_statements', [
                'selfprofileid' => $selfprofile->id,
                'categoryid' => $category->id,
            ]) + 1;
            $statementimportid = selfprofile_builder_make_identifier($category->shortname . '_' . $nextnumber);
        }

        if (!in_array($scoringdirection, ['normal', 'reverse'], true)) {
            $messages[] = ['message' => get_string('invalidscoringdirection', 'mod_selfprofile'), 'type' => 'error'];
        } else if (trim($statementtext) === '') {
            $messages[] = ['message' => get_string('invalidstatementbuilder', 'mod_selfprofile'), 'type' => 'error'];
        } else if ($DB->record_exists('selfprofile_statements', ['selfprofileid' => $selfprofile->id, 'importid' => $statementimportid])) {
            $messages[] = ['message' => get_string('duplicatestatementid', 'mod_selfprofile'), 'type' => 'error'];
        } else {
            selfprofile_builder_add_statement($selfprofile, $category, $statementimportid, $statementtext, $scoringdirection);
            $selfprofile = $DB->get_record('selfprofile', ['id' => $cm->instance], '*', MUST_EXIST);
            $messages[] = ['message' => get_string('statementsaved', 'mod_selfprofile'), 'type' => 'success'];
        }
    }
}

if (optional_param('updatestatement', 0, PARAM_BOOL)) {
    require_sesskey();

    $statementdbid = required_param('statementdbid', PARAM_INT);
    $newcategoryid = required_param('newcategorydbid', PARAM_INT);
    $statementtext = required_param('statementtext', PARAM_TEXT);
    $statementimportid = optional_param('statementimportid', '', PARAM_TEXT);
    $scoringdirection = required_param('scoringdirection', PARAM_ALPHA);

    $statement = $DB->get_record('selfprofile_statements', ['id' => $statementdbid, 'selfprofileid' => $selfprofile->id]);
    $newcategory = $DB->get_record('selfprofile_categories', ['id' => $newcategoryid, 'selfprofileid' => $selfprofile->id]);

    if (!$statement || !$newcategory || trim($statementtext) === '') {
        $messages[] = ['message' => get_string('invalidstatementbuilder', 'mod_selfprofile'), 'type' => 'error'];
    } else {
        $opencategoryid = (int) $newcategory->id;
        $statementimportid = selfprofile_builder_make_identifier($statementimportid !== '' ? $statementimportid : $statement->importid);

        if ($statementimportid === '') {
            $statementimportid = selfprofile_builder_make_identifier($newcategory->shortname . '_' . $statement->id);
        }

        if (!in_array($scoringdirection, ['normal', 'reverse'], true)) {
            $messages[] = ['message' => get_string('invalidscoringdirection', 'mod_selfprofile'), 'type' => 'error'];
        } else if (selfprofile_builder_statement_identifier_exists($selfprofile->id, $statementimportid, $statement->id)) {
            $messages[] = ['message' => get_string('duplicatestatementid', 'mod_selfprofile'), 'type' => 'error'];
        } else {
            selfprofile_builder_update_statement($selfprofile, $statement, $newcategory, $statementimportid, $statementtext, $scoringdirection);
            $selfprofile = $DB->get_record('selfprofile', ['id' => $cm->instance], '*', MUST_EXIST);
            $messages[] = ['message' => get_string('statementupdated', 'mod_selfprofile'), 'type' => 'success'];
        }
    }
}


if (optional_param('deletestatement', 0, PARAM_BOOL)) {
    require_sesskey();

    $statementdbid = required_param('statementdbid', PARAM_INT);
    $statement = $DB->get_record('selfprofile_statements', ['id' => $statementdbid, 'selfprofileid' => $selfprofile->id]);

    if (!$statement) {
        $messages[] = ['message' => get_string('invalidstatementbuilder', 'mod_selfprofile'), 'type' => 'error'];
    } else {
        $opencategoryid = (int) $statement->categoryid;
        $DB->delete_records('selfprofile_statements', ['id' => $statement->id, 'selfprofileid' => $selfprofile->id]);
        selfprofile_builder_sync_config_from_database($selfprofile->id);
        $selfprofile = $DB->get_record('selfprofile', ['id' => $cm->instance], '*', MUST_EXIST);
        $messages[] = ['message' => get_string('itemdeleted', 'mod_selfprofile'), 'type' => 'success'];
    }
}

if (optional_param('deletecategory', 0, PARAM_BOOL)) {
    require_sesskey();

    $categorydbid = required_param('categorydbid', PARAM_INT);
    $category = $DB->get_record('selfprofile_categories', ['id' => $categorydbid, 'selfprofileid' => $selfprofile->id]);

    if (!$category) {
        $messages[] = ['message' => get_string('invalidcategorybuilder', 'mod_selfprofile'), 'type' => 'error'];
    } else {
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('selfprofile_statements', ['selfprofileid' => $selfprofile->id, 'categoryid' => $category->id]);
        $DB->delete_records('selfprofile_categories', ['id' => $category->id, 'selfprofileid' => $selfprofile->id]);
        $transaction->allow_commit();

        selfprofile_builder_sync_config_from_database($selfprofile->id);
        $selfprofile = $DB->get_record('selfprofile', ['id' => $cm->instance], '*', MUST_EXIST);
        $opencategoryid = 0;
        $messages[] = ['message' => get_string('categorydeleted', 'mod_selfprofile'), 'type' => 'success'];
    }
}

$scale = selfprofile_builder_get_scale($selfprofile);
$scaletext = selfprofile_builder_scale_to_text($scale);

if ($scaletext === '') {
    $scaletext = get_string('defaultscaletext', 'mod_selfprofile');
}

$categories = $DB->get_records('selfprofile_categories', ['selfprofileid' => $selfprofile->id], 'sortorder ASC, id ASC');
$statements = $DB->get_records('selfprofile_statements', ['selfprofileid' => $selfprofile->id, 'enabled' => 1], 'categoryid ASC, sortorder ASC, id ASC');

$statementsbycategory = [];
foreach ($statements as $statement) {
    $statementsbycategory[$statement->categoryid][] = $statement;
    if ($editstatementid > 0 && (int) $statement->id === $editstatementid) {
        $opencategoryid = (int) $statement->categoryid;
    }
}

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($selfprofile->name));
echo $OUTPUT->heading(get_string('instrumentbuilder', 'mod_selfprofile'), 3);

foreach ($messages as $message) {
    echo $OUTPUT->notification($message['message'], $message['type']);
}

$summarytable = new html_table();
$summarytable->attributes['class'] = 'generaltable selfprofile-builder-summary';
$summarytable->head = [get_string('builderitem', 'mod_selfprofile'), get_string('builderstatus', 'mod_selfprofile')];
$summarytable->data[] = [get_string('activityname', 'mod_selfprofile'), format_string($selfprofile->name)];
$summarytable->data[] = [get_string('likertscaleanchors', 'mod_selfprofile'), count($scale)];
$summarytable->data[] = [get_string('categoryplural', 'mod_selfprofile'), count($categories)];
$summarytable->data[] = [get_string('builderitems', 'mod_selfprofile'), count($statements)];
echo html_writer::table($summarytable);

echo html_writer::start_tag('details', ['id' => 'selfprofile-scale-editor', 'class' => 'selfprofile-builder-details']);
echo html_writer::tag('summary', get_string('editscale', 'mod_selfprofile') . ' (' . count($scale) . ')', ['class' => 'btn btn-primary btn-block text-left']);
echo $OUTPUT->box_start('generalbox selfprofile-builder-scale');
echo html_writer::tag('p', get_string('editscaleinstructions', 'mod_selfprofile'));

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'savescale', 'value' => 1]);
echo html_writer::tag('label', get_string('scalebuilder', 'mod_selfprofile'), ['for' => 'id_scaletext']);
echo html_writer::empty_tag('br');
echo html_writer::tag('textarea', s($scaletext), ['id' => 'id_scaletext', 'name' => 'scaletext', 'rows' => 6, 'cols' => 80, 'class' => 'form-control']);
echo html_writer::tag('p', get_string('scalebuilderexample', 'mod_selfprofile'), ['class' => 'form-text text-muted']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('savescale', 'mod_selfprofile'), 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo $OUTPUT->box_end();
echo html_writer::end_tag('details');

echo $OUTPUT->heading(get_string('categories', 'mod_selfprofile'), 4);

if (empty($categories)) {
    echo $OUTPUT->notification(get_string('nocategoriesyet', 'mod_selfprofile'), 'info');
} else {
    foreach ($categories as $category) {
        $categorystatements = $statementsbycategory[$category->id] ?? [];
        $detailsattrs = [
            'id' => 'category-' . $category->id,
            'class' => 'selfprofile-builder-details selfprofile-category-details',
        ];

        if ((int) $category->id === $opencategoryid) {
            $detailsattrs['open'] = 'open';
        }

        echo html_writer::start_tag('details', $detailsattrs);
        $itemlabel = count($categorystatements) === 1 ? get_string('builderitem', 'mod_selfprofile') : get_string('builderitems', 'mod_selfprofile');
        $categorysummary = format_string($category->name) . ' (' . count($categorystatements) . ' ' . $itemlabel . ')';
        echo html_writer::tag('summary', $categorysummary, ['class' => 'btn btn-info btn-block text-left selfprofile-category-summary']);
        echo selfprofile_builder_render_category_delete_form($formurl, $category, count($categorystatements));

        echo $OUTPUT->box_start('generalbox selfprofile-builder-category');

        if (!empty($category->description)) {
            echo html_writer::tag('p', s($category->description));
        }

        echo html_writer::tag('p', get_string('categoryid', 'mod_selfprofile') . ': ' . s($category->shortname), ['class' => 'text-muted']);

        echo selfprofile_builder_render_category_edit_form($formurl, $category);
        echo selfprofile_builder_render_statement_table_and_edit_forms($cm, $formurl, $category, $categorystatements, $categories, $editstatementid);
        echo selfprofile_builder_render_add_statement_form($formurl, $category);

        echo $OUTPUT->box_end();
        echo html_writer::end_tag('details');
    }
}

echo selfprofile_builder_render_add_category_form($formurl);

$viewurl = new moodle_url('/mod/selfprofile/view.php', ['id' => $cm->id]);
$settingsurl = new moodle_url('/course/modedit.php', ['update' => $cm->id, 'return' => 1]);

echo html_writer::div(
    html_writer::link($viewurl, get_string('viewactivity', 'mod_selfprofile'), ['class' => 'btn btn-secondary']) . ' ' .
    html_writer::link($settingsurl, get_string('editsettings', 'mod_selfprofile'), ['class' => 'btn btn-secondary']),
    'selfprofile-actions'
);

echo $OUTPUT->footer();

/**
 * Renders a category edit form.
 *
 * @param moodle_url $formurl Form URL.
 * @param stdClass $category Category record.
 * @return string
 */
function selfprofile_builder_render_category_edit_form(moodle_url $formurl, stdClass $category): string {
    $html = '';
    $html .= html_writer::start_tag('details', ['class' => 'selfprofile-builder-details selfprofile-edit-category-details']);
    $html .= html_writer::tag('summary', get_string('editcategory', 'mod_selfprofile'));

    $html .= html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl . '#category-' . $category->id]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'updatecategory', 'value' => 1]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'categorydbid', 'value' => $category->id]);

    $html .= html_writer::start_div('mb-3');
    $html .= html_writer::tag('label', get_string('categoryname', 'mod_selfprofile'));
    $html .= html_writer::empty_tag('input', ['type' => 'text', 'name' => 'categoryname', 'class' => 'form-control', 'maxlength' => 255, 'required' => 'required', 'value' => s($category->name)]);
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('mb-3');
    $html .= html_writer::tag('label', get_string('categoryid', 'mod_selfprofile'));
    $html .= html_writer::empty_tag('input', ['type' => 'text', 'name' => 'categoryid', 'class' => 'form-control', 'maxlength' => 100, 'value' => s($category->shortname)]);
    $html .= html_writer::tag('p', get_string('categoryid_helptext', 'mod_selfprofile'), ['class' => 'form-text text-muted']);
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('mb-3');
    $html .= html_writer::tag('label', get_string('categorydescription', 'mod_selfprofile'));
    $html .= html_writer::tag('textarea', s($category->description), ['name' => 'categorydescription', 'rows' => 3, 'cols' => 80, 'class' => 'form-control']);
    $html .= html_writer::end_div();

    $html .= html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('save'), 'class' => 'btn btn-primary']);

    $html .= html_writer::end_tag('form');
    $html .= html_writer::end_tag('details');

    return $html;
}

/**
 * Renders statement table and inline edit forms.
 *
 * @param stdClass $cm Course module.
 * @param moodle_url $formurl Form URL.
 * @param stdClass $category Category record.
 * @param array $statements Statements.
 * @param array $categories Categories.
 * @return string
 */
function selfprofile_builder_render_statement_table_and_edit_forms(
    stdClass $cm,
    moodle_url $formurl,
    stdClass $category,
    array $statements,
    array $categories,
    int $editstatementid = 0
): string {
    if (empty($statements)) {
        return html_writer::div(get_string('nostatementsincategory', 'mod_selfprofile'), 'alert alert-info');
    }

    $forms = '';

    foreach ($statements as $statement) {
        $forms .= selfprofile_builder_render_statement_edit_form($formurl, $statement, $categories, (int) $statement->id === $editstatementid);
    }

    return $forms;
}

/**
 * Renders a statement edit form that is always present inside a collapsible details panel.
 *
 * @param moodle_url $formurl Form URL.
 * @param stdClass $statement Statement record.
 * @param array $categories Category records.
 * @return string
 */
function selfprofile_builder_render_statement_edit_form(moodle_url $formurl, stdClass $statement, array $categories, bool $open = false): string {
    $summary = selfprofile_builder_truncate_text((string) $statement->statement, 70);
    $directionkey = $statement->scoringdirection === 'reverse' ? 'scoringreverse' : 'scoringnormal';
    $summary .= ' — ' . get_string($directionkey, 'mod_selfprofile');

    $html = '';
    $detailsattrs = [
        'id' => 'edit-statement-' . $statement->id,
        'class' => 'selfprofile-builder-details selfprofile-edit-statement-details ml-3 mb-2',
    ];

    if ($open) {
        $detailsattrs['open'] = 'open';
    }

    $html .= html_writer::start_tag('details', $detailsattrs);
    $html .= html_writer::tag('summary', '▾ ' . $summary, ['class' => 'btn btn-outline-info btn-block text-left selfprofile-item-summary selfprofile-existing-item-summary']);
    $html .= selfprofile_builder_render_statement_delete_form($formurl, $statement);

    $html .= html_writer::tag('p', get_string('movestatementhelp', 'mod_selfprofile'), ['class' => 'alert alert-info']);

    $html .= html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl . '#edit-statement-' . $statement->id]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'updatestatement', 'value' => 1]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'statementdbid', 'value' => $statement->id]);

    $options = [];
    foreach ($categories as $category) {
        $options[$category->id] = format_string($category->name);
    }

    $html .= html_writer::start_div('mb-3');
    $html .= html_writer::tag('label', get_string('movestatementto', 'mod_selfprofile'));
    $html .= html_writer::select($options, 'newcategorydbid', $statement->categoryid, false, ['class' => 'form-control']);
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('mb-3');
    $html .= html_writer::tag('label', get_string('statementtext', 'mod_selfprofile'));
    $html .= html_writer::tag('textarea', s($statement->statement), ['name' => 'statementtext', 'rows' => 3, 'cols' => 80, 'class' => 'form-control', 'required' => 'required']);
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('mb-3');
    $html .= html_writer::tag('label', get_string('statementid', 'mod_selfprofile'));
    $html .= html_writer::empty_tag('input', ['type' => 'text', 'name' => 'statementimportid', 'class' => 'form-control', 'maxlength' => 100, 'value' => s($statement->importid)]);
    $html .= html_writer::tag('p', get_string('statementid_helptext', 'mod_selfprofile'), ['class' => 'form-text text-muted']);
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('mb-3');
    $html .= html_writer::tag('label', get_string('scoringdirection', 'mod_selfprofile'));
    $html .= html_writer::select(
        [
            'normal' => get_string('scoringnormal', 'mod_selfprofile'),
            'reverse' => get_string('scoringreverse', 'mod_selfprofile'),
        ],
        'scoringdirection',
        $statement->scoringdirection,
        false,
        [
            'class' => 'form-control selfprofile-scoring-direction-select',
            'title' => get_string('reversescoringwarning', 'mod_selfprofile'),
            'aria-label' => get_string('reversescoringwarning', 'mod_selfprofile'),
        ]
    );
    $html .= html_writer::end_div();

    $html .= html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('save'), 'class' => 'btn btn-primary']);

    $html .= html_writer::end_tag('form');
    $html .= html_writer::end_tag('details');

    return $html;
}


/**
 * Renders a category delete control.
 *
 * @param moodle_url $formurl Form URL.
 * @param stdClass $category Category record.
 * @param int $itemcount Number of items in the category.
 * @return string
 */
function selfprofile_builder_render_category_delete_form(moodle_url $formurl, stdClass $category, int $itemcount): string {
    $confirm = get_string('deletecategoryconfirm', 'mod_selfprofile', (object) [
        'name' => format_string($category->name),
        'count' => $itemcount,
    ]);

    $html = '';
    $html .= html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $formurl,
        'class' => 'selfprofile-delete-form selfprofile-category-delete-form',
        'onsubmit' => 'return confirm(' . json_encode($confirm) . ');',
    ]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'deletecategory', 'value' => 1]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'categorydbid', 'value' => $category->id]);
    $html .= html_writer::tag('button', '🗑', [
        'type' => 'submit',
        'class' => 'btn btn-link selfprofile-delete-button',
        'title' => get_string('deletecategory', 'mod_selfprofile'),
        'aria-label' => get_string('deletecategory', 'mod_selfprofile'),
    ]);
    $html .= html_writer::end_tag('form');

    return $html;
}

/**
 * Renders an item delete control.
 *
 * @param moodle_url $formurl Form URL.
 * @param stdClass $statement Statement record.
 * @return string
 */
function selfprofile_builder_render_statement_delete_form(moodle_url $formurl, stdClass $statement): string {
    $confirm = get_string('deleteitemconfirm', 'mod_selfprofile');

    $html = '';
    $html .= html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $formurl . '#category-' . $statement->categoryid,
        'class' => 'selfprofile-delete-form selfprofile-item-delete-form',
        'onsubmit' => 'return confirm(' . json_encode($confirm) . ');',
    ]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'deletestatement', 'value' => 1]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'statementdbid', 'value' => $statement->id]);
    $html .= html_writer::tag('button', '🗑', [
        'type' => 'submit',
        'class' => 'btn btn-link selfprofile-delete-button',
        'title' => get_string('deleteitem', 'mod_selfprofile'),
        'aria-label' => get_string('deleteitem', 'mod_selfprofile'),
    ]);
    $html .= html_writer::end_tag('form');

    return $html;
}

/**
 * Renders add statement form.
 *
 * @param moodle_url $formurl Form URL.
 * @param stdClass $category Category record.
 * @return string
 */
function selfprofile_builder_render_add_statement_form(moodle_url $formurl, stdClass $category): string {
    $html = '';

    $html .= html_writer::start_tag('details', ['class' => 'selfprofile-builder-details selfprofile-add-statement-details']);
    $html .= html_writer::tag('summary', '+ ' . get_string('addstatementtocategory', 'mod_selfprofile', format_string($category->name)), ['class' => 'btn btn-outline-info btn-block text-left selfprofile-item-summary']);

    $html .= html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl . '#category-' . $category->id]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'addstatement', 'value' => 1]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'categorydbid', 'value' => $category->id]);

    $html .= html_writer::start_div('mb-3');
    $html .= html_writer::tag('label', get_string('statementtext', 'mod_selfprofile'));
    $html .= html_writer::tag('textarea', '', ['name' => 'statementtext', 'rows' => 3, 'cols' => 80, 'class' => 'form-control', 'required' => 'required']);
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('mb-3');
    $html .= html_writer::tag('label', get_string('statementid', 'mod_selfprofile'));
    $html .= html_writer::empty_tag('input', ['type' => 'text', 'name' => 'statementimportid', 'class' => 'form-control', 'maxlength' => 100]);
    $html .= html_writer::tag('p', get_string('statementid_helptext', 'mod_selfprofile'), ['class' => 'form-text text-muted']);
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('mb-3');
    $html .= html_writer::tag('label', get_string('scoringdirection', 'mod_selfprofile'));
    $html .= html_writer::select(
        [
            'normal' => get_string('scoringnormal', 'mod_selfprofile'),
            'reverse' => get_string('scoringreverse', 'mod_selfprofile'),
        ],
        'scoringdirection',
        'normal',
        false,
        [
            'class' => 'form-control selfprofile-scoring-direction-select',
            'title' => get_string('reversescoringwarning', 'mod_selfprofile'),
            'aria-label' => get_string('reversescoringwarning', 'mod_selfprofile'),
        ]
    );
    $html .= html_writer::end_div();

    $html .= html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('save'), 'class' => 'btn btn-primary']);
    $html .= html_writer::end_tag('form');
    $html .= html_writer::end_tag('details');

    return $html;
}

/**
 * Renders add category form.
 *
 * @param moodle_url $formurl Form URL.
 * @return string
 */
function selfprofile_builder_render_add_category_form(moodle_url $formurl): string {
    $html = '';
    $html .= html_writer::start_tag('details', ['id' => 'selfprofile-add-category', 'class' => 'selfprofile-builder-details selfprofile-add-category-details']);
    $html .= html_writer::tag('summary', '+ ' . get_string('addcategory', 'mod_selfprofile'), ['class' => 'btn btn-primary btn-block text-left']);

    $html .= html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl . '#selfprofile-add-category']);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'addcategory', 'value' => 1]);

    $html .= html_writer::start_div('mb-3');
    $html .= html_writer::tag('label', get_string('categoryname', 'mod_selfprofile'));
    $html .= html_writer::empty_tag('input', ['type' => 'text', 'name' => 'categoryname', 'class' => 'form-control', 'maxlength' => 255, 'required' => 'required']);
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('mb-3');
    $html .= html_writer::tag('label', get_string('categoryid', 'mod_selfprofile'));
    $html .= html_writer::empty_tag('input', ['type' => 'text', 'name' => 'categoryid', 'class' => 'form-control', 'maxlength' => 100]);
    $html .= html_writer::tag('p', get_string('categoryid_helptext', 'mod_selfprofile'), ['class' => 'form-text text-muted']);
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('mb-3');
    $html .= html_writer::tag('label', get_string('categorydescription', 'mod_selfprofile'));
    $html .= html_writer::tag('textarea', '', ['name' => 'categorydescription', 'rows' => 3, 'cols' => 80, 'class' => 'form-control']);
    $html .= html_writer::end_div();

    $html .= html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('save'), 'class' => 'btn btn-primary']);
    $html .= html_writer::end_tag('form');
    $html .= html_writer::end_tag('details');

    return $html;
}

/**
 * Truncates text for compact statement summaries.
 *
 * @param string $text Statement text.
 * @param int $maxlength Maximum visible length.
 * @return string
 */
function selfprofile_builder_truncate_text(string $text, int $maxlength): string {
    $text = trim($text);

    if (core_text::strlen($text) <= $maxlength) {
        return $text;
    }

    return core_text::substr($text, 0, $maxlength - 3) . '...';
}

/**
 * Returns the current scale from the activity.
 *
 * @param stdClass $selfprofile Activity instance.
 * @return array
 */
function selfprofile_builder_get_scale(stdClass $selfprofile): array {
    $scale = json_decode($selfprofile->scalejson ?? '');

    if (json_last_error() === JSON_ERROR_NONE && is_array($scale)) {
        return $scale;
    }

    $config = json_decode($selfprofile->configjson ?? '');

    if (json_last_error() === JSON_ERROR_NONE && is_object($config) && !empty($config->scale) && is_array($config->scale)) {
        return $config->scale;
    }

    return [];
}

/**
 * Converts a scale array to teacher-editable text.
 *
 * @param array $scale Scale anchors.
 * @return string
 */
function selfprofile_builder_scale_to_text(array $scale): string {
    $lines = [];

    foreach ($scale as $scaleitem) {
        if (is_object($scaleitem) && isset($scaleitem->value) && isset($scaleitem->label)) {
            $lines[] = (int) $scaleitem->value . '|' . (string) $scaleitem->label;
        } else if (is_array($scaleitem) && isset($scaleitem['value']) && isset($scaleitem['label'])) {
            $lines[] = (int) $scaleitem['value'] . '|' . (string) $scaleitem['label'];
        }
    }

    return implode("\n", $lines);
}

/**
 * Parses and validates scale text.
 *
 * @param string $scaletext Scale text.
 * @return array
 */
function selfprofile_builder_parse_scale_text(string $scaletext): array {
    $scale = [];
    $values = [];

    foreach (preg_split('/\R/u', trim($scaletext)) as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        $parts = array_map('trim', explode('|', $line, 2));

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '' || !preg_match('/^-?\d+$/', $parts[0])) {
            return ['error' => 'invalidscalebuilder'];
        }

        $value = (int) $parts[0];

        if (isset($values[$value])) {
            return ['error' => 'invalidscalebuilder'];
        }

        if (core_text::strlen($parts[1]) > 100) {
            return ['error' => 'scalelabeltoolong'];
        }

        $values[$value] = true;

        $scale[] = ['value' => $value, 'label' => clean_param($parts[1], PARAM_TEXT)];
    }

    if (count($scale) < 2 || count($scale) > 10) {
        return ['error' => 'invalidscalebuilder'];
    }

    usort($scale, static function(array $a, array $b): int {
        return $a['value'] <=> $b['value'];
    });

    $sortedvalues = array_column($scale, 'value');
    $step = null;

    for ($i = 1; $i < count($sortedvalues); $i++) {
        $currentstep = $sortedvalues[$i] - $sortedvalues[$i - 1];

        if ($currentstep <= 0) {
            return ['error' => 'invalidscalebuilder'];
        }

        if ($step === null) {
            $step = $currentstep;
        } else if ($step !== $currentstep) {
            return ['error' => 'invalidscalebuilder'];
        }
    }

    return ['scale' => $scale];
}

/**
 * Saves the scale to both scalejson and configjson.
 *
 * @param stdClass $selfprofile Activity instance.
 * @param array $scale Scale anchors.
 * @return void
 */
function selfprofile_builder_save_scale(stdClass $selfprofile, array $scale): void {
    global $DB;

    $config = selfprofile_builder_get_config($selfprofile);
    $config->schema = 'mod_selfprofile';
    $config->schemaVersion = 2;
    $config->name = $selfprofile->name;
    $config->instructions = $selfprofile->instructions ?? '';
    $config->resultpreamble = $selfprofile->resultpreamble ?? '';
    $config->scale = $scale;

    if (empty($config->categories) || !is_array($config->categories)) {
        $config->categories = [];
    }

    $config->metadata = (object) [
        'createdBy' => 'mod_selfprofile_builder',
        'containsStudentData' => false,
    ];

    $record = new stdClass();
    $record->id = $selfprofile->id;
    $record->scalejson = json_encode($scale);
    $record->configjson = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $record->timemodified = time();

    $DB->update_record('selfprofile', $record);
}

/**
 * Adds a category to the database and configjson.
 *
 * @param stdClass $selfprofile Activity instance.
 * @param string $categoryid Category identifier.
 * @param string $categoryname Category name.
 * @param string $categorydescription Category description.
 * @return int New category database id.
 */
function selfprofile_builder_add_category(stdClass $selfprofile, string $categoryid, string $categoryname, string $categorydescription): int {
    global $DB;

    $sortorder = (int) $DB->count_records('selfprofile_categories', ['selfprofileid' => $selfprofile->id]);

    $record = new stdClass();
    $record->selfprofileid = $selfprofile->id;
    $record->shortname = $categoryid;
    $record->name = clean_param($categoryname, PARAM_TEXT);
    $record->description = clean_param($categorydescription, PARAM_TEXT);
    $record->sortorder = $sortorder;
    $record->timecreated = time();
    $record->timemodified = time();

    $categorydbid = $DB->insert_record('selfprofile_categories', $record);
    selfprofile_builder_sync_config_from_database($selfprofile->id);

    return $categorydbid;
}

/**
 * Updates a category.
 *
 * @param stdClass $selfprofile Activity instance.
 * @param stdClass $category Existing category.
 * @param string $categoryid Category identifier.
 * @param string $categoryname Category name.
 * @param string $categorydescription Category description.
 * @return void
 */
function selfprofile_builder_update_category(stdClass $selfprofile, stdClass $category, string $categoryid, string $categoryname, string $categorydescription): void {
    global $DB;

    $record = new stdClass();
    $record->id = $category->id;
    $record->shortname = $categoryid;
    $record->name = clean_param($categoryname, PARAM_TEXT);
    $record->description = clean_param($categorydescription, PARAM_TEXT);
    $record->timemodified = time();

    $DB->update_record('selfprofile_categories', $record);
    selfprofile_builder_sync_config_from_database($selfprofile->id);
}

/**
 * Adds a statement to a category.
 *
 * @param stdClass $selfprofile Activity instance.
 * @param stdClass $category Category record.
 * @param string $statementimportid Statement identifier.
 * @param string $statementtext Statement text.
 * @param string $scoringdirection Scoring direction.
 * @return void
 */
function selfprofile_builder_add_statement(stdClass $selfprofile, stdClass $category, string $statementimportid, string $statementtext, string $scoringdirection): void {
    global $DB;

    $sortorder = (int) $DB->count_records('selfprofile_statements', [
        'selfprofileid' => $selfprofile->id,
        'categoryid' => $category->id,
    ]);

    $record = new stdClass();
    $record->selfprofileid = $selfprofile->id;
    $record->categoryid = $category->id;
    $record->importid = $statementimportid;
    $record->statement = clean_param($statementtext, PARAM_TEXT);
    $record->scoringdirection = $scoringdirection;
    $record->sortorder = $sortorder;
    $record->enabled = 1;
    $record->timecreated = time();
    $record->timemodified = time();

    $DB->insert_record('selfprofile_statements', $record);
    selfprofile_builder_sync_config_from_database($selfprofile->id);
}

/**
 * Updates a statement and optionally moves it to another category.
 *
 * @param stdClass $selfprofile Activity instance.
 * @param stdClass $statement Existing statement.
 * @param stdClass $newcategory New category.
 * @param string $statementimportid Statement identifier.
 * @param string $statementtext Statement text.
 * @param string $scoringdirection Scoring direction.
 * @return void
 */
function selfprofile_builder_update_statement(
    stdClass $selfprofile,
    stdClass $statement,
    stdClass $newcategory,
    string $statementimportid,
    string $statementtext,
    string $scoringdirection
): void {
    global $DB;

    $sortorder = (int) $statement->sortorder;

    if ((int) $statement->categoryid !== (int) $newcategory->id) {
        $sortorder = (int) $DB->count_records('selfprofile_statements', [
            'selfprofileid' => $selfprofile->id,
            'categoryid' => $newcategory->id,
        ]);
    }

    $record = new stdClass();
    $record->id = $statement->id;
    $record->categoryid = $newcategory->id;
    $record->importid = $statementimportid;
    $record->statement = clean_param($statementtext, PARAM_TEXT);
    $record->scoringdirection = $scoringdirection;
    $record->sortorder = $sortorder;
    $record->timemodified = time();

    $DB->update_record('selfprofile_statements', $record);
    selfprofile_builder_sync_config_from_database($selfprofile->id);
}

/**
 * Checks if a category identifier already exists.
 *
 * @param int $selfprofileid Activity instance id.
 * @param string $categoryid Category identifier.
 * @param int $excludeid Excluded category id.
 * @return bool
 */
function selfprofile_builder_category_identifier_exists(int $selfprofileid, string $categoryid, int $excludeid): bool {
    global $DB;

    return $DB->record_exists_select(
        'selfprofile_categories',
        'selfprofileid = ? AND shortname = ? AND id <> ?',
        [$selfprofileid, $categoryid, $excludeid]
    );
}

/**
 * Checks if a statement identifier already exists.
 *
 * @param int $selfprofileid Activity instance id.
 * @param string $statementid Statement identifier.
 * @param int $excludeid Excluded statement id.
 * @return bool
 */
function selfprofile_builder_statement_identifier_exists(int $selfprofileid, string $statementid, int $excludeid): bool {
    global $DB;

    return $DB->record_exists_select(
        'selfprofile_statements',
        'selfprofileid = ? AND importid = ? AND id <> ?',
        [$selfprofileid, $statementid, $excludeid]
    );
}

/**
 * Syncs configjson from database content.
 *
 * @param int $selfprofileid Activity instance id.
 * @return void
 */
function selfprofile_builder_sync_config_from_database(int $selfprofileid): void {
    global $DB;

    $selfprofile = $DB->get_record('selfprofile', ['id' => $selfprofileid], '*', MUST_EXIST);
    
if (optional_param('deletestatement', 0, PARAM_BOOL)) {
    require_sesskey();

    $statementdbid = required_param('statementdbid', PARAM_INT);
    $statement = $DB->get_record('selfprofile_statements', ['id' => $statementdbid, 'selfprofileid' => $selfprofile->id]);

    if (!$statement) {
        $messages[] = ['message' => get_string('invalidstatementbuilder', 'mod_selfprofile'), 'type' => 'error'];
    } else {
        $opencategoryid = (int) $statement->categoryid;
        $DB->delete_records('selfprofile_statements', ['id' => $statement->id, 'selfprofileid' => $selfprofile->id]);
        selfprofile_builder_sync_config_from_database($selfprofile->id);
        $selfprofile = $DB->get_record('selfprofile', ['id' => $cm->instance], '*', MUST_EXIST);
        $messages[] = ['message' => get_string('itemdeleted', 'mod_selfprofile'), 'type' => 'success'];
    }
}

if (optional_param('deletecategory', 0, PARAM_BOOL)) {
    require_sesskey();

    $categorydbid = required_param('categorydbid', PARAM_INT);
    $category = $DB->get_record('selfprofile_categories', ['id' => $categorydbid, 'selfprofileid' => $selfprofile->id]);

    if (!$category) {
        $messages[] = ['message' => get_string('invalidcategorybuilder', 'mod_selfprofile'), 'type' => 'error'];
    } else {
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('selfprofile_statements', ['selfprofileid' => $selfprofile->id, 'categoryid' => $category->id]);
        $DB->delete_records('selfprofile_categories', ['id' => $category->id, 'selfprofileid' => $selfprofile->id]);
        $transaction->allow_commit();

        selfprofile_builder_sync_config_from_database($selfprofile->id);
        $selfprofile = $DB->get_record('selfprofile', ['id' => $cm->instance], '*', MUST_EXIST);
        $opencategoryid = 0;
        $messages[] = ['message' => get_string('categorydeleted', 'mod_selfprofile'), 'type' => 'success'];
    }
}

$scale = selfprofile_builder_get_scale($selfprofile);
    $categories = $DB->get_records('selfprofile_categories', ['selfprofileid' => $selfprofileid], 'sortorder ASC, id ASC');
    $statements = $DB->get_records('selfprofile_statements', ['selfprofileid' => $selfprofileid, 'enabled' => 1], 'categoryid ASC, sortorder ASC, id ASC');

    $statementsbycategory = [];
    foreach ($statements as $statement) {
        $statementsbycategory[$statement->categoryid][] = [
            'id' => $statement->importid ?: 'statement_' . $statement->id,
            'text' => $statement->statement,
            'scoringdirection' => $statement->scoringdirection ?: 'normal',
        ];
    }

    $categorydata = [];
    foreach ($categories as $category) {
        $categorydata[] = [
            'id' => $category->shortname,
            'name' => $category->name,
            'description' => $category->description ?? '',
            'statements' => $statementsbycategory[$category->id] ?? [],
        ];
    }

    $config = new stdClass();
    $config->schema = 'mod_selfprofile';
    $config->schemaVersion = 2;
    $config->name = $selfprofile->name;
    $config->instructions = $selfprofile->instructions ?? '';
    $config->resultpreamble = $selfprofile->resultpreamble ?? '';
    $config->scale = $scale;
    $config->categories = $categorydata;
    $config->metadata = (object) [
        'createdBy' => 'mod_selfprofile_builder',
        'containsStudentData' => false,
    ];

    $record = new stdClass();
    $record->id = $selfprofileid;
    $record->configjson = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $record->timemodified = time();

    $DB->update_record('selfprofile', $record);
}

/**
 * Gets config object.
 *
 * @param stdClass $selfprofile Activity instance.
 * @return stdClass
 */
function selfprofile_builder_get_config(stdClass $selfprofile): stdClass {
    $config = json_decode($selfprofile->configjson ?? '');

    if (json_last_error() === JSON_ERROR_NONE && is_object($config)) {
        return $config;
    }

    return new stdClass();
}

/**
 * Creates a safe identifier.
 *
 * @param string $value Raw identifier.
 * @return string
 */
function selfprofile_builder_make_identifier(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    $value = preg_replace('/_+/', '_', $value);
    $value = trim($value, '_');

    return substr($value, 0, 100);
}
