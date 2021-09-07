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
 * adhoc task file file
 * @package     mod_mattermost
 * @category    task
 * @copyright   2020 Manoj <manoj@brightscout.com>
 * @author      Manoj <manoj@brightscout.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mattermost\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Adhoc task for unenrollingg a user from a mattermost channel
 */
class unenrol_user_from_mattermost_channel extends \core\task\adhoc_task {
    /**
     * Execute the task
     */
    public function execute() {
        $data = $this->get_custom_data();
        \mod_mattermost\tools\mattermost_tools::unenrol_user_from_mattermost_channel(
            $data->channelid,
            $data->userid,
            $data->mattermostinstanceid
        );
    }
}
