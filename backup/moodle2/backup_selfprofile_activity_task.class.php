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
 * Backup task for mod_selfprofile.
 *
 * @package    mod_selfprofile
 * @copyright  2026 Johan Venter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the backup task for a SelfProfile activity.
 */
class backup_selfprofile_activity_task extends backup_activity_task {

    /**
     * Define task-specific settings.
     */
    protected function define_my_settings(): void {
        // No task-specific settings are required.
    }

    /**
     * Define backup steps.
     */
    protected function define_my_steps(): void {
        $this->add_step(new backup_selfprofile_activity_structure_step('selfprofile_structure', 'selfprofile.xml'));
    }

    /**
     * Encode links to this activity.
     *
     * @param string $content Content to encode.
     * @return string Encoded content.
     */
    public static function encode_content_links($content): string {
        global $CFG;

        $base = preg_quote($CFG->wwwroot . '/mod/selfprofile', '#');

        $search = '#(' . $base . '/index\.php\?id=)([0-9]+)#';
        $content = preg_replace($search, '$@SELFPROFILEINDEX*$2@$', $content);

        $search = '#(' . $base . '/view\.php\?id=)([0-9]+)#';
        $content = preg_replace($search, '$@SELFPROFILEVIEWBYID*$2@$', $content);

        return $content;
    }
}
