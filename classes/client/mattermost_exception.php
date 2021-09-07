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
 * mattermost exception class
 *
 * @package   mod_mattermost
 * @copyright 2021 Brightscout <hello@brightscout.com>
 * @author    Manoj <manoj@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mattermost\client;
use Exception;

/**
 * A custom made exception class which converts the response into json and makes the appropriate message for the error.
 */
class mattermost_exception extends Exception
{
    /**
     * Constructor for the mattermost_exception class
     *
     * @param mixed $response
     * @param int $code
     * @param Exception $previous
     */
    public function __construct($response, $code = 0, Exception $previous = null) {
        $message = '';
        $jsonresponse = json_decode($response, true);
        if ($jsonresponse) {
            $message = isset($response->error) ? $response->error : $response->message;
            $code = $code || $response->code;
        } else {
            $message = strip_tags($response);
        }
        parent::__construct($message, $code, $previous);
    }
}
