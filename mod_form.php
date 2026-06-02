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
 * Activity settings form for the SelfProfile activity module.
 *
 * @package    mod_selfprofile
 * @copyright  2026 Johan Venter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Activity settings form class for mod_selfprofile.
 */
class mod_selfprofile_mod_form extends moodleform_mod {

    /**
     * Defines the activity settings form.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'mod_selfprofile'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'name', 'mod_selfprofile');

        $this->standard_intro_elements();

        $mform->addElement('header', 'selfprofiletextsettings', get_string('selfprofiletextsettings', 'mod_selfprofile'));

        $mform->addElement(
            'editor',
            'instructions_editor',
            get_string('instructions', 'mod_selfprofile'),
            null,
            selfprofile_get_editor_options()
        );
        $mform->setType('instructions_editor', PARAM_RAW);
        $mform->addHelpButton('instructions_editor', 'instructions', 'mod_selfprofile');

        $mform->addElement(
            'editor',
            'resultpreamble_editor',
            get_string('resultpreamble', 'mod_selfprofile'),
            null,
            selfprofile_get_editor_options()
        );
        $mform->setType('resultpreamble_editor', PARAM_RAW);
        $mform->addHelpButton('resultpreamble_editor', 'resultpreamble', 'mod_selfprofile');

        $mform->addElement(
            'advcheckbox',
            'showresults',
            get_string('results', 'mod_selfprofile'),
            get_string('resultsavailableaftersubmission', 'mod_selfprofile')
        );
        $mform->setDefault('showresults', 1);

        $mform->addElement('header', 'selfprofilebuildersection', get_string('instrumentbuilder', 'mod_selfprofile'));

        $mform->addElement(
            'static',
            'builderexplanation',
            '',
            get_string('builderexplanation', 'mod_selfprofile')
        );

        $mform->addElement(
            'static',
            'liveeditwarning',
            get_string('warning', 'mod_selfprofile'),
            get_string('liveeditwarning', 'mod_selfprofile')
        );

        if (!empty($this->current) && !empty($this->current->coursemodule)) {
            $builderurl = new moodle_url('/mod/selfprofile/builder.php', ['id' => $this->current->coursemodule]);

            $mform->addElement(
                'static',
                'builderlink',
                '',
                html_writer::link(
                    $builderurl,
                    get_string('openbuilder', 'mod_selfprofile'),
                    ['class' => 'btn btn-primary']
                )
            );
        } else {
            $mform->addElement(
                'static',
                'buildernotavailable',
                '',
                get_string('builderavailableaftersave', 'mod_selfprofile')
            );
        }

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Prepares current instance data for the settings form.
     *
     * @param array $defaultvalues Default form values.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues): void {
        if ($this->current && isset($this->current->instructions)) {
            $defaultvalues['instructions_editor'] = [
                'text' => $this->current->instructions,
                'format' => $this->current->instructionsformat ?? FORMAT_HTML,
            ];
        }

        if ($this->current && isset($this->current->resultpreamble)) {
            $defaultvalues['resultpreamble_editor'] = [
                'text' => $this->current->resultpreamble,
                'format' => $this->current->resultpreambleformat ?? FORMAT_HTML,
            ];
        }
    }

    /**
     * Allows completion rules to be added later without breaking the form class.
     *
     * @return array
     */
    public function add_completion_rules(): array {
        return [];
    }

    /**
     * Validates custom completion rules.
     *
     * @param array $data Submitted form data.
     * @return array
     */
    public function completion_rule_enabled($data): array {
        return [];
    }
}

/**
 * Returns editor options for text editor fields.
 *
 * @return array
 */
function selfprofile_get_editor_options(): array {
    return [
        'maxfiles' => 0,
        'maxbytes' => 0,
        'trusttext' => false,
        'noclean' => false,
        'context' => context_system::instance(),
    ];
}
