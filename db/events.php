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
 * Plugin event observers are registered here.
 *
 * @package   mod_mattermost
 * @category  event
 * @copyright 2021 Brightscout <hello@brightscout.com>
 * @author    Manoj <manoj@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = array(

    array(
        'eventname' => '\core\event\role_assigned',
        'callback' => '\mod_mattermost\observers::role_assigned',
    ),
    array(
        'eventname' => '\core\event\role_unassigned',
        'callback' => '\mod_mattermost\observers::role_unassigned',
    ),
    array(
        'eventname' => '\core\event\user_updated',
        'callback' => '\mod_mattermost\observers::user_updated',
    ),
    array(
        'eventname' => '\core\event\group_created',
        'callback' => '\mod_mattermost\observers::group_created',
    ),
    array(
        'eventname' => '\core\event\group_deleted',
        'callback' => '\mod_mattermost\observers::group_deleted'
    ),
    array(
        'eventname' => '\core\event\group_member_added',
        'callback' => '\mod_mattermost\observers::group_member_added',
    ),
    array(
        'eventname' => '\core\event\group_member_removed',
        'callback' => '\mod_mattermost\observers::group_member_removed',
    ),
    array(
        'eventname' => 'tool_recyclebin\event\course_bin_item_restored',
        'callback' => '\mod_mattermost\observers::course_bin_item_restored',
    ),
    array(
        'eventname' => 'tool_recyclebin\event\course_bin_item_created',
        'callback' => '\mod_mattermost\observers::course_bin_item_created',
    ),
    array(
        'eventname' => 'tool_recyclebin\event\course_bin_item_deleted',
        'callback' => '\mod_mattermost\observers::course_bin_item_deleted',
    ),
    array(
        'eventname' => 'tool_recyclebin\event\category_bin_item_restored',
        'callback' => '\mod_mattermost\observers::category_bin_item_restored',
    ),
    array(
        'eventname' => 'tool_recyclebin\event\category_bin_item_created',
        'callback' => '\mod_mattermost\observers::category_bin_item_created',
    ),
    array(
        'eventname' => 'tool_recyclebin\event\category_bin_item_deleted',
        'callback' => '\mod_mattermost\observers::category_bin_item_deleted',
    ),
    array(
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\mod_mattermost\observers::course_module_updated',
    ),
    array(
        'eventname' => '\core\event\user_enrolment_updated',
        'callback' => '\mod_mattermost\observers::user_enrolment_updated',
    ),
);
