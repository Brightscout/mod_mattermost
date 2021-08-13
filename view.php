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
 * Prints an instance of mod_mattermost.
 *
 * @package     mod_mattermost
 * @copyright   2020 Manoj <manoj@brightscout.com>
 * @author      Manoj <manoj@brightscout.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/classes/api/manager/mattermost_api_manager.php');
use \mod_mattermost\tools\mattermost_tools;

// Course_module ID.
$id = optional_param('id', 0, PARAM_INT);
// Module instance id.
$n  = optional_param('r', 0, PARAM_INT);

if ($id) {
    $cm             = get_coursemodule_from_id('mattermost', $id, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('mattermost', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $moduleinstance = $DB->get_record('mattermost', array('id' => $n), '*', MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm             = get_coursemodule_from_instance('mattermost', $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error('missingparam');
}

require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);

// TODO: add event trigger for course_module_viewed.

$PAGE->set_url('/mod/mattermost/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

$config = get_config('mod_mattermost');
echo $OUTPUT->header();
$mattermostapiconfig = new \mod_mattermost\api\manager\mattermost_api_config();
$link = mattermost_tools::get_channel_link($moduleinstance->mattermostid);

switch ($moduleinstance->displaytype) {
    case mattermost_tools::DISPLAY_POPUP:
        echo $OUTPUT->action_link($link, get_string('joinmattermost', 'mod_mattermost'),
            new popup_action(
                'click',
                $link,
                'joinmattermost',
                array('height' => $moduleinstance->popupheight, 'width' => $moduleinstance->popupwidth)
            )
        );
        break;
    case mattermost_tools::DISPLAY_CURRENT:
        echo $OUTPUT->action_link(
            $link,
            get_string('joinmattermost', 'mod_mattermost')
        );
        break;
    default:
        // DISPLAY_NEW and default case.
        echo html_writer::link(
            $link,
            get_string('joinmattermost', 'mod_mattermost'),
            array('onclick' => 'this.target="_blank";')
        );
        break;
}

echo $OUTPUT->footer();
