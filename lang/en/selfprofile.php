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
 * English language strings for the SelfProfile activity module.
 *
 * @package    mod_selfprofile
 * @copyright  2026 Johan Venter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'SelfProfile';
$string['modulename'] = 'SelfProfile';
$string['modulenameplural'] = 'SelfProfiles';
$string['pluginadministration'] = 'SelfProfile administration';

$string['selfprofile:addinstance'] = 'Add a new SelfProfile activity';
$string['selfprofile:view'] = 'View SelfProfile activity';
$string['selfprofile:submit'] = 'Submit SelfProfile responses';
$string['selfprofile:manage'] = 'Manage SelfProfile content';
$string['selfprofile:export'] = 'Export SelfProfile structure';
$string['selfprofile:viewreports'] = 'View SelfProfile reports';

$string['name'] = 'SelfProfile name';
$string['name_help'] = 'Enter the name of this SelfProfile activity.';
$string['instructions'] = 'Instructions for students';
$string['instructions_help'] = 'These instructions will be shown to students before and during the activity.';
$string['jsonimport'] = 'SelfProfile JSON';
$string['jsonimport_help'] = 'Paste a valid SelfProfile JSON structure containing the scale, categories and items.';
$string['jsonimportrequired'] = 'You must provide a valid SelfProfile JSON structure.';
$string['invalidjson'] = 'The imported JSON is not valid.';
$string['invalidjsonschema'] = 'The imported JSON does not match the expected SelfProfile schema.';
$string['missingcategories'] = 'The imported JSON must contain at least one category.';
$string['missingstatements'] = 'Each category must contain at least one item.';
$string['missingscale'] = 'The imported JSON must contain a Likert-type scale.';
$string['toomanycategories'] = 'The imported JSON contains too many categories.';
$string['toomanystatements'] = 'The imported JSON contains too many items.';
$string['statementtoolong'] = 'One or more items are too long.';
$string['categorynametoolong'] = 'One or more category names are too long.';
$string['scalelabeltoolong'] = 'One or more scale labels are too long.';

$string['viewactivity'] = 'View SelfProfile';
$string['startactivity'] = 'Start SelfProfile';
$string['continueactivity'] = 'Continue SelfProfile';
$string['submitactivity'] = 'Submit SelfProfile';
$string['responsesaved'] = 'Your response has been saved.';
$string['responsenotsaved'] = 'Your response could not be saved.';
$string['allstatementsanswered'] = 'All statements have been answered.';
$string['notallstatementsanswered'] = 'Please answer all statements before submitting.';
$string['activitysubmitted'] = 'Your SelfProfile has been submitted.';
$string['alreadysubmitted'] = 'This SelfProfile has already been submitted.';
$string['changesallowed'] = 'You may change your responses before final submission.';
$string['changeslocked'] = 'Your responses are locked because this SelfProfile has been submitted.';

$string['statement'] = 'Statement';
$string['category'] = 'Category';
$string['scale'] = 'Scale';
$string['response'] = 'Response';
$string['progress'] = 'Progress';
$string['answeredcount'] = '{$a->answered} of {$a->total} statements answered';
$string['nextstatement'] = 'Next statement';
$string['previousstatement'] = 'Previous statement';
$string['noactivitycontent'] = 'This SelfProfile activity does not yet contain categories and statements.';
$string['nostatementsavailable'] = 'There are no statements available in this SelfProfile activity.';
$string['selectresponse'] = 'Select your response';
$string['saveandcontinue'] = 'Save and continue';
$string['savechanges'] = 'Save changes';

$string['results'] = 'Results';
$string['yourresults'] = 'Your SelfProfile results';
$string['categoryresults'] = 'Category results';
$string['categoryscore'] = 'Category score';
$string['averagescore'] = 'Average score';
$string['rank'] = 'Rank';
$string['highesttolowest'] = 'Categories from highest score to lowest score';
$string['resultsnotavailable'] = 'Results are not yet available.';
$string['resultsavailableaftersubmission'] = 'Results will be available after final submission.';

$string['exportjson'] = 'Export JSON';
$string['importjson'] = 'Import JSON';
$string['downloadjson'] = 'Download SelfProfile JSON';
$string['jsonexportnotavailable'] = 'JSON export is not available for this activity.';
$string['jsonimportsuccess'] = 'The SelfProfile JSON structure was imported successfully.';
$string['jsonimportfailed'] = 'The SelfProfile JSON structure could not be imported.';

$string['privacy:metadata:selfprofile_attempts'] = 'Information about a user attempt in a SelfProfile activity.';
$string['privacy:metadata:selfprofile_attempts:selfprofileid'] = 'The ID of the SelfProfile activity.';
$string['privacy:metadata:selfprofile_attempts:userid'] = 'The ID of the user making the attempt.';
$string['privacy:metadata:selfprofile_attempts:status'] = 'The status of the attempt.';
$string['privacy:metadata:selfprofile_attempts:statementorderjson'] = 'The randomised order of items for the attempt.';
$string['privacy:metadata:selfprofile_attempts:timecreated'] = 'The time when the attempt was created.';
$string['privacy:metadata:selfprofile_attempts:timemodified'] = 'The time when the attempt was last modified.';
$string['privacy:metadata:selfprofile_attempts:timesubmitted'] = 'The time when the attempt was submitted.';

$string['privacy:metadata:selfprofile_responses'] = 'Individual responses given by a user in a SelfProfile activity.';
$string['privacy:metadata:selfprofile_responses:attemptid'] = 'The ID of the related SelfProfile attempt.';
$string['privacy:metadata:selfprofile_responses:statementid'] = 'The ID of the item being answered.';
$string['privacy:metadata:selfprofile_responses:scalevalue'] = 'The selected scale value.';
$string['privacy:metadata:selfprofile_responses:timecreated'] = 'The time when the response was created.';
$string['privacy:metadata:selfprofile_responses:timemodified'] = 'The time when the response was last modified.';

$string['eventcoursemoduleviewed'] = 'SelfProfile activity viewed';
$string['eventresponsecreated'] = 'SelfProfile response created';
$string['eventresponseupdated'] = 'SelfProfile response updated';
$string['eventattemptsubmitted'] = 'SelfProfile attempt submitted';
$string['eventjsonimported'] = 'SelfProfile JSON imported';
$string['eventjsonexported'] = 'SelfProfile JSON exported';

$string['reports'] = 'Reports';
$string['noreportdata'] = 'No report data is available yet.';
$string['student'] = 'Student';
$string['attemptstatus'] = 'Attempt status';
$string['timesubmitted'] = 'Submitted';
$string['actions'] = 'Actions';
$string['submitted'] = 'Submitted';
$string['inprogress'] = 'In progress';
$string['viewresults'] = 'View results';
$string['resultsforuser'] = 'Results for {$a}';

$string['resultpreamble'] = 'Results preamble';
$string['resultpreamble_help'] = 'Enter a paragraph explaining how students and teachers should read and interpret the SelfProfile results. This text is displayed above the results and will also be included in the PDF report.';
$string['howtoreadresults'] = 'How to read these results';

$string['selfprofileresultreport'] = 'SelfProfile result report';
$string['downloadpdf'] = 'Download PDF';
$string['importantnote'] = 'Important note';
$string['resultdisclaimer'] = 'These results are intended to support reflection and conversation. They are not a clinical, psychometric or high-stakes diagnostic assessment.';

$string['liveeditwarning'] = 'Editing or replacing JSON after students have started may affect existing attempts. For live use, create a new activity.';
$string['instrumentbuilder'] = 'Instrument builder';
$string['scalebuilder'] = 'Likert scale anchors';

$string['defaultscaletext'] = "0|Strongly disagree\n1|Disagree\n2|Agree\n3|Strongly agree";


$string['scalebuilder_help'] = 'Enter one scale anchor per line using value|label. Example: 0|Strongly disagree. Use between 2 and 10 evenly spaced integer values.';
$string['instrumentbuildertext'] = 'Categories and Items';
$string['instrumentbuildertext_help'] = 'Enter categories and items line by line. Category format: category|categoryid|Category name|Description. Item format: item|categoryid|normal|item text or item|categoryid|statementid|reverse|Statement text.';
$string['jsoninstrument'] = 'Import prepared instrument';
$string['jsonfile'] = 'Upload schemaVersion 2 JSON file';
$string['jsonfile_help'] = 'Upload a previously exported or prepared SelfProfile schemaVersion 2 JSON file.';
$string['instrumentrequired'] = 'Create an instrument using the builder, paste schemaVersion 2 JSON, or upload a schemaVersion 2 JSON file.';
$string['invalidscalebuilder'] = 'The Likert scale anchors are invalid. Use one value|label pair per line, with 2 to 10 unique evenly spaced integer values.';
$string['invalidinstrumentbuilder'] = 'The instrument builder text is invalid. Check category and item line formats.';

$string['selfprofiletextsettings'] = 'SelfProfile text settings';
$string['builderexplanation'] = 'Use the SelfProfile Builder to define the Likert scale, categories, items, and normal or reverse scoring. The builder saves the instrument as schemaVersion 2 JSON behind the scenes.';
$string['openbuilder'] = 'Open SelfProfile Builder';
$string['builderavailableaftersave'] = 'Save this activity first. After saving, return to the activity settings page to open the SelfProfile Builder.';

$string['warning'] = 'Warning';


$string['builderitem'] = 'Item';
$string['builderstatus'] = 'Current status';
$string['activityname'] = 'Activity name';
$string['likertscaleanchors'] = 'Likert scale anchors';
$string['categoryplural'] = 'Categories';
$string['statementplural'] = 'Items';
$string['buildernextsteps'] = 'Builder functions still to add';
$string['buildernextstep_scale'] = 'Define or edit the Likert scale anchors.';
$string['buildernextstep_categories'] = 'Add, edit, or remove categories.';
$string['buildernextstep_statements'] = 'Add items inside each category.';
$string['buildernextstep_scoring'] = 'Choose normal or reverse scoring for each item.';
$string['editsettings'] = 'Edit settings';


$string['editscale'] = 'Edit Likert scale';
$string['editscaleinstructions'] = 'Enter one scale anchor per line using value|label. Use between 2 and 10 evenly spaced integer values.';
$string['scalebuilderexample'] = 'Example: 0|Strongly disagree, 1|Disagree, 2|Agree, 3|Strongly agree. A 1-based scale such as 1|Strongly disagree to 5|Strongly agree is also allowed.';
$string['savescale'] = 'Save Likert scale';
$string['scalesaved'] = 'The Likert scale has been saved.';


$string['categories'] = 'Categories';
$string['nocategoriesyet'] = 'No categories have been added yet.';
$string['addcategory'] = 'Add category';
$string['categoryname'] = 'Category name';
$string['categoryid'] = 'Category identifier';
$string['categoryid_helptext'] = 'Optional. Use a short lowercase identifier such as planning or people_support. If left blank, one will be created from the category name.';
$string['categorydescription'] = 'Category description';
$string['invalidcategorybuilder'] = 'The category could not be saved. Enter at least a category name.';
$string['duplicatecategoryid'] = 'A category with this identifier already exists in this SelfProfile.';
$string['categorysaved'] = 'The category has been saved.';

$string['builderstage4notice'] = 'The SelfProfile Builder can now save the Likert scale, add categories, and add items inside categories.';
$string['nostatementsincategory'] = 'No items have been added to this category yet.';
$string['statementid'] = 'Item identifier';
$string['scoringdirection'] = 'Scoring direction';
$string['scoringnormal'] = 'Normal scoring';
$string['scoringreverse'] = 'Reverse scoring';
$string['addstatementtocategory'] = 'Add item to {$a}';
$string['reversescoringwarning'] = 'Use reverse scoring only when the wording genuinely requires it. If possible, rewrite the item positively to avoid confusing respondents with negative or double-negative wording.';
$string['statementtext'] = 'Item text';
$string['statementid_helptext'] = 'Optional. Use a short lowercase identifier such as enjoys_planning. If left blank, one will be created automatically.';
$string['addstatement'] = 'Add item';
$string['invalidstatementbuilder'] = 'The item could not be saved. Check the item text and category.';
$string['duplicatestatementid'] = 'An item with this identifier already exists in this SelfProfile.';
$string['statementsaved'] = 'The item has been saved.';
$string['buildernextstep_review'] = 'Review the completed SelfProfile as a teacher before allowing students to start.';
$string['buildernextstep_editdelete'] = 'Add editing and deleting controls for categories and items.';




$string['editcategory'] = 'Edit category';
$string['savecategorychanges'] = 'Save category changes';
$string['categoryupdated'] = 'The category has been updated.';
$string['editstatement'] = 'Edit item';
$string['savestatementchanges'] = 'Save item changes';
$string['statementupdated'] = 'The item has been updated.';
$string['movestatementto'] = 'Move item to category';
$string['movestatementhelp'] = 'Use this form to edit the item text, change normal or reverse scoring, or move the item to a different category.';
$string['buildernextstep_delete'] = 'Add delete controls with confirmation for categories and items.';



$string['statementeditinstruction'] = 'Open an item below to edit its full text, scoring direction, identifier, or category.';

$string['privacy:metadata:selfprofile_attempts'] = 'Information about a user attempt in a SelfProfile activity.';
$string['privacy:metadata:selfprofile_attempts:selfprofileid'] = 'The SelfProfile activity instance.';
$string['privacy:metadata:selfprofile_attempts:userid'] = 'The user who made the attempt.';
$string['privacy:metadata:selfprofile_attempts:status'] = 'The status of the user attempt.';
$string['privacy:metadata:selfprofile_attempts:statementorderjson'] = 'The order in which items were presented to the user.';
$string['privacy:metadata:selfprofile_attempts:timecreated'] = 'The time the attempt was created.';
$string['privacy:metadata:selfprofile_attempts:timemodified'] = 'The time the attempt was last modified.';
$string['privacy:metadata:selfprofile_attempts:timesubmitted'] = 'The time the attempt was submitted.';

$string['privacy:metadata:selfprofile_responses'] = 'Individual responses given by a user in a SelfProfile attempt.';
$string['privacy:metadata:selfprofile_responses:attemptid'] = 'The attempt associated with the response.';
$string['privacy:metadata:selfprofile_responses:statementid'] = 'The item that was answered.';
$string['privacy:metadata:selfprofile_responses:scalevalue'] = 'The scale value selected by the user.';
$string['privacy:metadata:selfprofile_responses:timecreated'] = 'The time the response was created.';
$string['privacy:metadata:selfprofile_responses:timemodified'] = 'The time the response was last modified.';

$string['builderitems'] = 'Items';
$string['deletecategory'] = 'Delete category';
$string['deleteitem'] = 'Delete item';
$string['deletecategoryconfirm'] = 'Delete the category "{$a->name}" and its {$a->count} item(s)? Items linked to this category will also be deleted. If you want to keep them, cancel and move them to another category first.';
$string['deleteitemconfirm'] = 'Delete this item?';
$string['categorydeleted'] = 'The category has been deleted.';
$string['itemdeleted'] = 'The item has been deleted.';

$string['jsonimported'] = 'The JSON file has been imported.';
$string['jsonimportempty'] = 'Paste JSON or choose a JSON file to import.';
$string['jsonfiletoolarge'] = 'The JSON file is too large. Please upload a file smaller than 1 MB.';
$string['jsonimportinstructions'] = 'Importing JSON will replace the current categories, items and scale for this SelfProfile activity. Only import JSON that was exported from SelfProfile or created according to the SelfProfile schema.';
$string['jsonfile'] = 'JSON file';
$string['jsonpaste'] = 'Paste JSON';
$string['jsonimportconfirm'] = 'Import this JSON? Existing categories and items in this SelfProfile activity will be replaced.';

