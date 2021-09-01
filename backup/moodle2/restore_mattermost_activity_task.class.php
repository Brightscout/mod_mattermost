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
 * The task that provides a complete restore of mod_mattermost is defined here.
 *
 * To Do: change these to Brightscout
 * @package   mod_mattermost
 * @copyright 2021 Abhishek Verma <abhishek.verma@brightscout.com>
 * @author    Abhishek Verma <abhishek.verma@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'//mod/mattermost/backup/moodle2/restore_mattermost_stepslib.php');

/**
 * Restore task for mod_mattermost.
 */
class restore_mattermost_activity_task extends restore_activity_task {

    /**
     * Defines particular settings that this activity can have.
     */
    protected function define_my_settings() {
        return;
    }

    /**
     * Defines particular steps that this activity can have.
     *
     * @return base_step.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_mattermost_activity_structure_step('mattermost_structure', 'mattermost.xml'));
    }

    /**
     * Defines the contents in the activity that must be processed by the link decoder.
     *
     * @return array.
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('mattermost', array('intro'), 'mattermost.xml');

        return $contents;
    }

    /**
     * Defines the decoding rules for links belonging to the activity to be executed by the link decoder.
     *
     * @return array.
     */
    static public function define_decode_rules() {
        $rules = array();
        $rules[] = new restore_decode_rule('MATTERMOSTVIEWBYID', '/mod/mattermost/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('MATTERMOSTINDEX', '/mod/mattermost/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Defines the restore log rules that will be applied by the
     * restore_logs_processor when restoring mod_mattermost logs. It
     * must return one array of restore_log_rule objects.
     *
     * @return array.
     */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('mattermost', 'add', 'view.php?id={course_module}', '{mattermost}');
        $rules[] = new restore_log_rule('mattermost', 'update', 'view.php?id={course_module}', '{mattermost}');
        $rules[] = new restore_log_rule('mattermost', 'view', 'view.php?id={course_module}', '{mattermost}');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the restore_logs_processor when restoring
     * course logs. It must return one array
     * of restore_log_rule objects
     */
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('mattermost', 'view all', 'index.php?id={course}', null);

        return $rules;
    }

    /**
     * Returns restore mode
     */
    public function get_plan_mode() {
        return $this->plan->get_mode();
    }
}
