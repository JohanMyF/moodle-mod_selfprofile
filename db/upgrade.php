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
 * Upgrade steps for the SelfProfile activity module.
 *
 * @package    mod_selfprofile
 * @copyright  2026 Johan Venter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Performs SelfProfile database upgrades.
 *
 * @param int $oldversion The previously installed plugin version.
 * @return bool
 */
function xmldb_selfprofile_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026060100) {
        $table = new xmldb_table('selfprofile');

        $field = new xmldb_field(
            'resultpreamble',
            XMLDB_TYPE_TEXT,
            null,
            null,
            null,
            null,
            null,
            'instructionsformat'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field(
            'resultpreambleformat',
            XMLDB_TYPE_INTEGER,
            '4',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'resultpreamble'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026060100, 'selfprofile');
    }

    if ($oldversion < 2026060101) {
        $table = new xmldb_table('selfprofile_statements');

        $field = new xmldb_field(
            'importid',
            XMLDB_TYPE_CHAR,
            '100',
            null,
            null,
            null,
            null,
            'categoryid'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field(
            'scoringdirection',
            XMLDB_TYPE_CHAR,
            '20',
            null,
            XMLDB_NOTNULL,
            null,
            'normal',
            'statement'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index(
            'selfprofile_importid_idx',
            XMLDB_INDEX_NOTUNIQUE,
            ['selfprofileid', 'importid']
        );

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_mod_savepoint(true, 2026060101, 'selfprofile');
    }

    return true;
}
