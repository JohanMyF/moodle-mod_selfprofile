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
 * PDF results download page for the SelfProfile activity module.
 *
 * This page supports two routes:
 * - Students download their own PDF: pdf.php?id=cmid
 * - Teachers download a selected student's PDF by attempt id: pdf.php?id=cmid&attemptid=attemptid
 *
 * @package    mod_selfprofile
 * @copyright  2026 Johan Venter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/selfprofile/lib.php');
require_once($CFG->libdir . '/pdflib.php');

$id = required_param('id', PARAM_INT);
$attemptid = optional_param('attemptid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('selfprofile', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$selfprofile = $DB->get_record('selfprofile', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/selfprofile:view', $context);

$viewingotheruser = false;

if ($attemptid > 0) {
    require_capability('mod/selfprofile:viewreports', $context);

    $attempt = $DB->get_record('selfprofile_attempts', [
        'id' => $attemptid,
        'selfprofileid' => $selfprofile->id,
    ], '*', MUST_EXIST);

    $targetuserid = (int) $attempt->userid;
    $viewingotheruser = ($targetuserid !== (int) $USER->id);
} else {
    $targetuserid = (int) $USER->id;
    $attempt = $DB->get_record(
        'selfprofile_attempts',
        ['selfprofileid' => $selfprofile->id, 'userid' => $targetuserid],
        '*',
        MUST_EXIST
    );
}

$targetuser = $DB->get_record('user', ['id' => $targetuserid, 'deleted' => 0], '*', MUST_EXIST);

if ($viewingotheruser && !is_enrolled($context, $targetuser, '', true)) {
    throw new moodle_exception('invaliduser', 'error');
}

if (empty($selfprofile->showresults) && $attemptid === 0 && !has_capability('mod/selfprofile:viewreports', $context)) {
    throw new moodle_exception('resultsnotavailable', 'mod_selfprofile');
}

if ($attempt->status !== 'submitted') {
    throw new moodle_exception('resultsavailableaftersubmission', 'mod_selfprofile');
}

$enabledstatementcount = $DB->count_records(
    'selfprofile_statements',
    ['selfprofileid' => $selfprofile->id, 'enabled' => 1]
);

$responsecount = $DB->count_records(
    'selfprofile_responses',
    ['attemptid' => $attempt->id]
);

if ($responsecount === 0 || $responsecount < $enabledstatementcount) {
    throw new moodle_exception('resultsnotavailable', 'mod_selfprofile');
}

$scale = json_decode($selfprofile->scalejson ?? '');
$minscale = 0;
$maxscale = 5;
$scalerange = 5;

if (json_last_error() === JSON_ERROR_NONE && is_array($scale) && !empty($scale)) {
    $values = [];

    foreach ($scale as $scaleitem) {
        if (is_object($scaleitem) && isset($scaleitem->value) && is_numeric($scaleitem->value)) {
            $values[] = (int) $scaleitem->value;
        }
    }

    if (!empty($values)) {
        $minscale = min($values);
        $maxscale = max($values);
        $scalerange = max(1, $maxscale - $minscale);
    }
}

$sql = "SELECT c.id,
               c.name,
               c.description,
               AVG(CASE
                   WHEN s.scoringdirection = 'reverse'
                   THEN (:scalemax + :scalemin - r.scalevalue)
                   ELSE r.scalevalue
               END) AS averagescore,
               COUNT(r.id) AS responsecount
          FROM {selfprofile_categories} c
          JOIN {selfprofile_statements} s
            ON s.categoryid = c.id
           AND s.enabled = 1
          JOIN {selfprofile_responses} r
            ON r.statementid = s.id
           AND r.attemptid = :attemptid
         WHERE c.selfprofileid = :selfprofileid
      GROUP BY c.id, c.name, c.description, c.sortorder
      ORDER BY averagescore DESC, c.sortorder ASC";

$results = $DB->get_records_sql($sql, [
    'attemptid' => $attempt->id,
    'selfprofileid' => $selfprofile->id,
    'scalemax' => $maxscale,
    'scalemin' => $minscale,
]);

if (empty($results)) {
    throw new moodle_exception('resultsnotavailable', 'mod_selfprofile');
}

$submittedtext = '-';
if (!empty($attempt->timesubmitted)) {
    $submittedtext = userdate($attempt->timesubmitted);
}

$filenamebase = clean_param(format_string($selfprofile->name) . '-' . fullname($targetuser), PARAM_FILE);
if ($filenamebase === '') {
    $filenamebase = 'selfprofile-results';
}
$filename = $filenamebase . '.pdf';

$pdf = new pdf(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8');

$pdf->SetCreator('Moodle');
$pdf->SetAuthor(fullname($targetuser));
$pdf->SetTitle(format_string($selfprofile->name) . ' - ' . get_string('yourresults', 'mod_selfprofile'));
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

$html = '';

$html .= html_writer::tag('h1', s(format_string($selfprofile->name)));
$html .= html_writer::tag('h2', s(get_string('selfprofileresultreport', 'mod_selfprofile')));

$html .= '<table cellpadding="4" cellspacing="0" border="0">';
$html .= '<tr><td width="30%"><strong>' . s(get_string('student', 'mod_selfprofile')) . '</strong></td><td width="70%">' .
    s(fullname($targetuser)) . '</td></tr>';
$html .= '<tr><td width="30%"><strong>' . s(get_string('course')) . '</strong></td><td width="70%">' .
    s(format_string($course->fullname)) . '</td></tr>';
$html .= '<tr><td width="30%"><strong>' . s(get_string('timesubmitted', 'mod_selfprofile')) . '</strong></td><td width="70%">' .
    s($submittedtext) . '</td></tr>';
$html .= '</table>';

if (!empty($selfprofile->resultpreamble)) {
    $html .= html_writer::tag('h3', s(get_string('howtoreadresults', 'mod_selfprofile')));
    $html .= format_text($selfprofile->resultpreamble, $selfprofile->resultpreambleformat, ['context' => $context]);
}

$html .= html_writer::tag('h3', s(get_string('categoryresults', 'mod_selfprofile')));
$html .= html_writer::tag('p', s(get_string('highesttolowest', 'mod_selfprofile')));

$html .= '<table cellpadding="5" cellspacing="0" border="1" width="100%">';
$html .= '<thead>';
$html .= '<tr style="font-weight:bold;background-color:#eeeeee;">';
$html .= '<th width="10%">' . s(get_string('rank', 'mod_selfprofile')) . '</th>';
$html .= '<th width="50%">' . s(get_string('category', 'mod_selfprofile')) . '</th>';
$html .= '<th width="20%">' . s(get_string('averagescore', 'mod_selfprofile')) . '</th>';
$html .= '<th width="20%">' . s(get_string('categoryscore', 'mod_selfprofile')) . '</th>';
$html .= '</tr>';
$html .= '</thead>';
$html .= '<tbody>';

$rank = 1;

foreach ($results as $result) {
    if ((int) $result->responsecount === 0 || $result->averagescore === null) {
        continue;
    }

    $average = round((float) $result->averagescore, 2);
    $normalised = $scalerange > 0 ? (($average - $minscale) / $scalerange) : 0;
    $normalised = min(1, max(0, $normalised));

    $bar = selfprofile_pdf_result_bar($normalised);

    $categoryhtml = '<strong>' . s(format_string($result->name)) . '</strong>';

    if (!empty($result->description)) {
        $categoryhtml .= '<br><span style="font-size:9pt;">' . s($result->description) . '</span>';
    }

    $html .= '<tr>';
    $html .= '<td width="10%">' . $rank . '</td>';
    $html .= '<td width="50%">' . $categoryhtml . '</td>';
    $html .= '<td width="20%">' . s(format_float($average, 2)) . '</td>';
    $html .= '<td width="20%">' . $bar . '</td>';
    $html .= '</tr>';

    $rank++;
}

$html .= '</tbody>';
$html .= '</table>';

$html .= html_writer::tag('h3', s(get_string('importantnote', 'mod_selfprofile')));
$html .= html_writer::tag('p', s(get_string('resultdisclaimer', 'mod_selfprofile')));

$pdf->writeHTML($html);
$pdf->Output($filename, 'D');
exit;

/**
 * Builds a stable segmented bar for TCPDF.
 *
 * This avoids unreliable 0%-width and 100%-width table cells in TCPDF.
 *
 * @param float $normalised A value from 0 to 1.
 * @return string HTML table representing the score bar.
 */
function selfprofile_pdf_result_bar(float $normalised): string {
    $normalised = min(1, max(0, $normalised));
    $segments = 10;
    $filledsegments = (int) round($normalised * $segments);

    $html = '<table cellpadding="0" cellspacing="1" border="0" width="100%"><tr>';

    for ($i = 1; $i <= $segments; $i++) {
        $background = $i <= $filledsegments ? '#666666' : '#dddddd';
        $html .= '<td width="10%" style="background-color:' . $background . ';font-size:5pt;">&nbsp;</td>';
    }

    $html .= '</tr></table>';

    return $html;
}
