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
 * Restore structure step for mod_selfprofile.
 *
 * @package    mod_selfprofile
 * @copyright  2026 Johan Venter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the restore structure for a SelfProfile activity.
 */
class restore_selfprofile_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define restore structure.
     *
     * @return array Restore paths.
     */
    protected function define_structure(): array {
        $paths = [];

        $paths[] = new restore_path_element('selfprofile', '/activity/selfprofile');
        $paths[] = new restore_path_element('selfprofile_category', '/activity/selfprofile/categories/category');
        $paths[] = new restore_path_element('selfprofile_statement', '/activity/selfprofile/categories/category/statements/statement');

        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('selfprofile_attempt', '/activity/selfprofile/attempts/attempt');
            $paths[] = new restore_path_element('selfprofile_response', '/activity/selfprofile/attempts/attempt/responses/response');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore a SelfProfile activity instance.
     *
     * @param array|stdClass $data The data.
     */
    protected function process_selfprofile($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('selfprofile', $data);
        $this->apply_activity_instance($newitemid);
        $this->set_mapping('selfprofile', $oldid, $newitemid);
    }

    /**
     * Restore a category.
     *
     * @param array|stdClass $data The data.
     */
    protected function process_selfprofile_category($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->selfprofileid = $this->get_new_parentid('selfprofile');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('selfprofile_categories', $data);
        $this->set_mapping('selfprofile_category', $oldid, $newitemid);
    }

    /**
     * Restore a statement.
     *
     * @param array|stdClass $data The data.
     */
    protected function process_selfprofile_statement($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->selfprofileid = $this->get_new_parentid('selfprofile');
        $data->categoryid = $this->get_new_parentid('selfprofile_category');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('selfprofile_statements', $data);
        $this->set_mapping('selfprofile_statement', $oldid, $newitemid);
    }

    /**
     * Restore a user attempt.
     *
     * @param array|stdClass $data The data.
     */
    protected function process_selfprofile_attempt($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->selfprofileid = $this->get_new_parentid('selfprofile');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        if (!empty($data->timesubmitted)) {
            $data->timesubmitted = $this->apply_date_offset($data->timesubmitted);
        }

        if (empty($data->userid)) {
            return;
        }

        $newitemid = $DB->insert_record('selfprofile_attempts', $data);
        $this->set_mapping('selfprofile_attempt', $oldid, $newitemid);
    }

    /**
     * Restore a response.
     *
     * @param array|stdClass $data The data.
     */
    protected function process_selfprofile_response($data): void {
        global $DB;

        $data = (object) $data;

        $data->attemptid = $this->get_new_parentid('selfprofile_attempt');
        $data->statementid = $this->get_mappingid('selfprofile_statement', $data->statementid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        if (empty($data->attemptid) || empty($data->statementid)) {
            return;
        }

        $DB->insert_record('selfprofile_responses', $data);
    }

    /**
     * Execute after restore.
     */
    protected function after_execute(): void {
        $this->add_related_files('mod_selfprofile', 'intro', null);
    }
}
