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
 * Restore task for mod_selfprofile.
 *
 * @package    mod_selfprofile
 * @copyright  2026 Johan Venter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the restore task for a SelfProfile activity.
 */
class restore_selfprofile_activity_task extends restore_activity_task {

    /**
     * Define task-specific settings.
     */
    protected function define_my_settings(): void {
        // No task-specific settings are required.
    }

    /**
     * Define restore steps.
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_selfprofile_activity_structure_step('selfprofile_structure', 'selfprofile.xml'));
    }

    /**
     * Decode links to this activity.
     *
     * @return array Link decoding rules.
     */
    public static function define_decode_contents(): array {
        $contents = [];

        $contents[] = new restore_decode_content('selfprofile', ['intro', 'instructions', 'resultpreamble'], 'selfprofile');

        return $contents;
    }

    /**
     * Decode links to this activity.
     *
     * @return array Link decoding rules.
     */
    public static function define_decode_rules(): array {
        $rules = [];

        $rules[] = new restore_decode_rule('SELFPROFILEVIEWBYID', '/mod/selfprofile/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('SELFPROFILEINDEX', '/mod/selfprofile/index.php?id=$1', 'course');

        return $rules;
    }
}
