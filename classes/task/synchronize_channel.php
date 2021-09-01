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
 *
 * @package   mod_mattermost
 * @category  task
 * @copyright 2021 Brightscout <hello@brightscout.com>
 * @author    Manoj <manoj@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mattermost\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Adhoc task for synchronizing a channel
 */
class synchronize_channel extends \core\task\adhoc_task
{
    /**
     * Execute the task
     */
    public function execute() {
        $data = $this->get_custom_data();
        $context = \context::instance_by_id($data->coursecontextid);
        \mod_mattermost\tools\mattermost_tools::synchronize_channel(
            $data->mattermostid,
            (array)$data->moodlemembers,
            (array)$data->channeladminroleids,
            (array)$data->userroleids,
            $context
        );
    }
}
