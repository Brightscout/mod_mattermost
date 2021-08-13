<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The main mod_mattermost configuration form.
 *
 * @package     mod_mattermost
 * @copyright   2020 Manoj <manoj@brightscout.com>
 * @author      Manoj <manoj@brightscout.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
use \mod_mattermost\tools\mattermost_tools;

require_once($CFG->dirroot . '/course/moodleform_mod.php');


/**
 * Module instance settings form.
 *
 * @package    mod_mattermost
 * @author     Manoj <manoj@brightscout.com>
 * @copyright  2020 Manoj <manoj@brightscout.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_mattermost_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG, $DB;
        $mform = $this->_form;
        // General Section.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding a name field not the channel name but the name.
        $mform->addElement('text', 'name', get_string('name', 'mod_mattermost'), array('size' => '255'));

        // Strip name if necessary.
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }

        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement(
            'header',
            'displaysection',
            get_string('displaysection', 'mod_mattermost')
        );
        $mform->setExpanded('displaysection');
        $options = mattermost_tools::get_display_options();

        $mform->addElement(
            'select',
            'displaytype',
            get_string('displaytype', 'mod_mattermost'),
            $options
        );

        $mform->addElement('text', 'popupwidth', get_string('popupwidth', 'mod_mattermost'));
        $mform->setType('popupwidth', PARAM_INT);
        $mform->setDefault('popupwidth', 700);
        if (count($options) > 1) {
            $mform->disabledif(
                'popupwidth',
                'displaytype',
                'noteq',
                mattermost_tools::DISPLAY_POPUP
            );
        }

        $mform->addElement('text', 'popupheight', get_string('popupheight', 'mod_mattermost'));
        $mform->setType('popupheight', PARAM_INT);
        $mform->setDefault('popupheight', 700);
        if (count($options) > 1) {
            $mform->disabledif(
                'popupheight',
                'displaytype',
                'noteq',
                mattermost_tools::DISPLAY_POPUP
            );
        }

        $mform->addElement(
            'header',
            'rolessection',
            get_string('rolessection', 'mod_mattermost')
        );
        $mform->setExpanded('rolessection');
        $rolesoptions = role_fix_names(get_all_roles(), null, ROLENAME_ORIGINALANDSHORT, true);
        $defaultchanneladminroles = get_config('mod_mattermost', 'defaultchanneladminroles');
        $defaultuserroles = get_config('mod_mattermost', 'defaultuserroles');
        $rolesreadonly = !has_capability('mod/mattermost:candefineroles', $this->get_context());
        $channeladminroletext = '';
        $userroletext = '';
        if ($rolesreadonly) {
            if (!empty($this->_instance)) {
                $channeladminroletext = $this->format_roles($this->get_current()->channeladminroles, $rolesoptions);
                $userroletext = $this->format_roles($this->get_current()->userroles, $rolesoptions);
            } else {
                $channeladminroletext = $this->format_roles($defaultchanneladminroles, $rolesoptions);
                $userroletext = $this->format_roles($defaultuserroles, $rolesoptions);
            }
            $mform->addElement(
                'static',
                'channeladminrolesstatic',
                get_string('channeladminroles', 'mod_mattermost'),
                $channeladminroletext
            );
            $mform->addElement('hidden', 'channeladminroles');
            $mform->addElement(
                'static',
                'userrolesstatic',
                get_string('userroles', 'mod_mattermost'),
                $userroletext
            );
            $mform->addElement('hidden', 'userroles');
        } else {
            $channeladminroles = $mform->addElement(
                'select',
                'channeladminroles',
                get_string('channeladminroles', 'mod_mattermost'),
                $rolesoptions
            );
            $channeladminroles->setMultiple(true);

            $userroles = $mform->addElement(
                'select',
                'userroles',
                get_string('userroles', 'mod_mattermost'),
                $rolesoptions
            );
            $userroles->setMultiple(true);
        }
        $mform->setType('channeladminroles', PARAM_RAW);
        $mform->setType('userroles', PARAM_RAW);
        $mform->setDefault('channeladminroles', $defaultchanneladminroles);
        $mform->setDefault('userroles', $defaultuserroles);

        // Add standard elements.
        $this->standard_coursemodule_elements();

        // Add standard buttons.
        $this->add_action_buttons();
    }

    public function data_postprocessing($data) {
        $data->channeladminroles = is_array($data->channeladminroles) ?
            implode(',', $data->channeladminroles) :
            $data->channeladminroles;
        $data->userroles = is_array($data->userroles) ? implode(',', $data->userroles) : $data->userroles;
    }

    /**
     * @param string $formattedrole
     * @param array $rolesoptions
     */
    protected function format_roles($roleids, $rolesoptions) {
        $i = 1;
        $formattedrole = '';
        foreach (array_filter(explode(',', $roleids)) as $channeladminroleid) {
            if ($i > 1) {
                $formattedrole .= ',';
            }
            $formattedrole .= $rolesoptions[$channeladminroleid];
            $i++;
        }
        return $formattedrole;
    }

    protected function validation($data, $files) {
        global $COURSE, $DB, $CFG;
        $errors = parent::validation($data, $files);
    }
}
