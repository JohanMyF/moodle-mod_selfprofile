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
 * Privacy provider for the SelfProfile activity module.
 *
 * @package    mod_selfprofile
 * @copyright  2026 Johan Venter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_selfprofile\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_selfprofile.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe all user data stored by this plugin.
     *
     * @param collection $collection The metadata collection.
     * @return collection The updated metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'selfprofile_attempts',
            [
                'selfprofileid' => 'privacy:metadata:selfprofile_attempts:selfprofileid',
                'userid' => 'privacy:metadata:selfprofile_attempts:userid',
                'status' => 'privacy:metadata:selfprofile_attempts:status',
                'statementorderjson' => 'privacy:metadata:selfprofile_attempts:statementorderjson',
                'timecreated' => 'privacy:metadata:selfprofile_attempts:timecreated',
                'timemodified' => 'privacy:metadata:selfprofile_attempts:timemodified',
                'timesubmitted' => 'privacy:metadata:selfprofile_attempts:timesubmitted',
            ],
            'privacy:metadata:selfprofile_attempts'
        );

        $collection->add_database_table(
            'selfprofile_responses',
            [
                'attemptid' => 'privacy:metadata:selfprofile_responses:attemptid',
                'statementid' => 'privacy:metadata:selfprofile_responses:statementid',
                'scalevalue' => 'privacy:metadata:selfprofile_responses:scalevalue',
                'timecreated' => 'privacy:metadata:selfprofile_responses:timecreated',
                'timemodified' => 'privacy:metadata:selfprofile_responses:timemodified',
            ],
            'privacy:metadata:selfprofile_responses'
        );

        return $collection;
    }

    /**
     * Get contexts containing data for the supplied user.
     *
     * @param int $userid The user id.
     * @return contextlist The context list.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm
                    ON cm.id = ctx.instanceid
                   AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m
                    ON m.id = cm.module
                   AND m.name = :modname
                  JOIN {selfprofile_attempts} spa
                    ON spa.selfprofileid = cm.instance
                 WHERE spa.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'selfprofile',
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Export user data for the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved context list.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $contexts = $contextlist->get_contexts();

        if (empty($contexts)) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $contextids = [];

        foreach ($contexts as $context) {
            if ($context->contextlevel === CONTEXT_MODULE) {
                $contextids[] = $context->id;
            }
        }

        if (empty($contextids)) {
            return;
        }

        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');

        $attemptsql = "SELECT spa.id,
                              spa.selfprofileid,
                              spa.userid,
                              spa.status,
                              spa.statementorderjson,
                              spa.timecreated,
                              spa.timemodified,
                              spa.timesubmitted,
                              ctx.id AS contextid
                         FROM {context} ctx
                         JOIN {course_modules} cm
                           ON cm.id = ctx.instanceid
                          AND ctx.contextlevel = :contextlevel
                         JOIN {modules} m
                           ON m.id = cm.module
                          AND m.name = :modname
                         JOIN {selfprofile_attempts} spa
                           ON spa.selfprofileid = cm.instance
                        WHERE ctx.id {$contextsql}
                          AND spa.userid = :userid";

        $attemptparams = array_merge($contextparams, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'selfprofile',
            'userid' => $userid,
        ]);

        $attempts = $DB->get_records_sql($attemptsql, $attemptparams);

        if (empty($attempts)) {
            return;
        }

        $attemptids = array_keys($attempts);
        [$attemptidsql, $attemptidparams] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED, 'attempt');

        $responsesql = "SELECT spr.id,
                               spr.attemptid,
                               spr.scalevalue,
                               spr.timecreated,
                               spr.timemodified,
                               sps.statement,
                               spc.name AS categoryname,
                               spc.sortorder AS categorysortorder,
                               sps.sortorder AS statementsortorder
                          FROM {selfprofile_responses} spr
                          JOIN {selfprofile_statements} sps
                            ON sps.id = spr.statementid
                     LEFT JOIN {selfprofile_categories} spc
                            ON spc.id = sps.categoryid
                         WHERE spr.attemptid {$attemptidsql}
                      ORDER BY spr.attemptid, spc.sortorder, sps.sortorder, spr.id";

        $responses = $DB->get_records_sql($responsesql, $attemptidparams);
        $responsesbyattempt = [];

        foreach ($responses as $response) {
            $responsesbyattempt[$response->attemptid][] = $response;
        }

        $contextsbyid = [];
        foreach ($contexts as $context) {
            $contextsbyid[$context->id] = $context;
        }

        foreach ($attempts as $attempt) {
            if (empty($contextsbyid[$attempt->contextid])) {
                continue;
            }

            $exportresponses = [];

            foreach ($responsesbyattempt[$attempt->id] ?? [] as $response) {
                $exportresponses[] = [
                    'category' => $response->categoryname,
                    'statement' => $response->statement,
                    'scalevalue' => $response->scalevalue,
                    'timecreated' => transform::datetime($response->timecreated),
                    'timemodified' => transform::datetime($response->timemodified),
                ];
            }

            $data = (object) [
                'status' => $attempt->status,
                'statementorderjson' => $attempt->statementorderjson,
                'timecreated' => transform::datetime($attempt->timecreated),
                'timemodified' => transform::datetime($attempt->timemodified),
                'timesubmitted' => $attempt->timesubmitted ? transform::datetime($attempt->timesubmitted) : null,
                'responses' => $exportresponses,
            ];

            writer::with_context($contextsbyid[$attempt->contextid])->export_data(
                [get_string('pluginname', 'selfprofile')],
                $data
            );
        }
    }

    /**
     * Delete all user data in the supplied context.
     *
     * @param \context $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        $selfprofileid = self::get_selfprofile_instanceid_from_context($context);

        if (!$selfprofileid) {
            return;
        }

        $attemptids = $DB->get_fieldset_select(
            'selfprofile_attempts',
            'id',
            'selfprofileid = :selfprofileid',
            ['selfprofileid' => $selfprofileid]
        );

        self::delete_attempts_and_responses($attemptids);
    }

    /**
     * Delete data for the approved user and contexts.
     *
     * @param approved_contextlist $contextlist The approved context list.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            $selfprofileid = self::get_selfprofile_instanceid_from_context($context);

            if (!$selfprofileid) {
                continue;
            }

            $attemptids = $DB->get_fieldset_select(
                'selfprofile_attempts',
                'id',
                'selfprofileid = :selfprofileid AND userid = :userid',
                [
                    'selfprofileid' => $selfprofileid,
                    'userid' => $userid,
                ]
            );

            self::delete_attempts_and_responses($attemptids);
        }
    }

    /**
     * Add users who have data in the supplied context.
     *
     * @param userlist $userlist The user list.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $sql = "SELECT spa.userid
                  FROM {course_modules} cm
                  JOIN {modules} m
                    ON m.id = cm.module
                   AND m.name = :modname
                  JOIN {selfprofile_attempts} spa
                    ON spa.selfprofileid = cm.instance
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, [
            'modname' => 'selfprofile',
            'cmid' => $context->instanceid,
        ]);
    }

    /**
     * Delete data for approved users in the supplied context.
     *
     * @param approved_userlist $userlist The approved user list.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $selfprofileid = self::get_selfprofile_instanceid_from_context($userlist->get_context());

        if (!$selfprofileid) {
            return;
        }

        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'userid');

        $params = array_merge(['selfprofileid' => $selfprofileid], $userparams);

        $attemptids = $DB->get_fieldset_select(
            'selfprofile_attempts',
            'id',
            "selfprofileid = :selfprofileid AND userid {$usersql}",
            $params
        );

        self::delete_attempts_and_responses($attemptids);
    }

    /**
     * Get the SelfProfile instance id from a module context.
     *
     * @param \context $context The module context.
     * @return int The SelfProfile instance id, or 0 if unavailable.
     */
    protected static function get_selfprofile_instanceid_from_context(\context $context): int {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return 0;
        }

        $sql = "SELECT cm.instance
                  FROM {course_modules} cm
                  JOIN {modules} m
                    ON m.id = cm.module
                   AND m.name = :modname
                 WHERE cm.id = :cmid";

        return (int) $DB->get_field_sql($sql, [
            'modname' => 'selfprofile',
            'cmid' => $context->instanceid,
        ]);
    }

    /**
     * Delete attempts and their related responses.
     *
     * @param array $attemptids The attempt ids.
     */
    protected static function delete_attempts_and_responses(array $attemptids): void {
        global $DB;

        if (empty($attemptids)) {
            return;
        }

        [$attemptsql, $attemptparams] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED, 'attemptid');

        $DB->delete_records_select(
            'selfprofile_responses',
            "attemptid {$attemptsql}",
            $attemptparams
        );

        [$idsql, $idparams] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED, 'id');

        $DB->delete_records_select(
            'selfprofile_attempts',
            "id {$idsql}",
            $idparams
        );
    }
}
