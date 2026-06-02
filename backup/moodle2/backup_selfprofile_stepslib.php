<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Backup structure step for mod_selfprofile.
 *
 * @package    mod_selfprofile
 * @copyright  2026 Johan Venter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the backup structure for a SelfProfile activity.
 */
class backup_selfprofile_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the activity structure for backup.
     *
     * @return backup_nested_element The root element.
     */
    protected function define_structure(): backup_nested_element {
        $userinfo = $this->get_setting_value('userinfo');

        $selfprofile = new backup_nested_element('selfprofile', ['id'], [
            'name',
            'intro',
            'introformat',
            'instructions',
            'instructionsformat',
            'resultpreamble',
            'resultpreambleformat',
            'scalejson',
            'configjson',
            'showresults',
            'timecreated',
            'timemodified',
        ]);

        $categories = new backup_nested_element('categories');
        $category = new backup_nested_element('category', ['id'], [
            'shortname',
            'name',
            'description',
            'sortorder',
            'timecreated',
            'timemodified',
        ]);

        $statements = new backup_nested_element('statements');
        $statement = new backup_nested_element('statement', ['id'], [
            'importid',
            'statement',
            'scoringdirection',
            'sortorder',
            'enabled',
            'timecreated',
            'timemodified',
        ]);

        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', ['id'], [
            'userid',
            'status',
            'statementorderjson',
            'timecreated',
            'timemodified',
            'timesubmitted',
        ]);

        $responses = new backup_nested_element('responses');
        $response = new backup_nested_element('response', ['id'], [
            'statementid',
            'scalevalue',
            'timecreated',
            'timemodified',
        ]);

        $selfprofile->add_child($categories);
        $categories->add_child($category);
        $category->add_child($statements);
        $statements->add_child($statement);
        $selfprofile->add_child($attempts);
        $attempts->add_child($attempt);
        $attempt->add_child($responses);
        $responses->add_child($response);

        $selfprofile->set_source_table('selfprofile', ['id' => backup::VAR_ACTIVITYID]);
        $category->set_source_table('selfprofile_categories', ['selfprofileid' => backup::VAR_PARENTID]);
        $statement->set_source_table('selfprofile_statements', ['categoryid' => backup::VAR_PARENTID]);

        if ($userinfo) {
            $attempt->set_source_table('selfprofile_attempts', ['selfprofileid' => backup::VAR_PARENTID]);
            $response->set_source_table('selfprofile_responses', ['attemptid' => backup::VAR_PARENTID]);
        }

        $attempt->annotate_ids('user', 'userid');
        $selfprofile->annotate_files('mod_selfprofile', 'intro', null);

        return $this->prepare_activity_structure($selfprofile);
    }
}
