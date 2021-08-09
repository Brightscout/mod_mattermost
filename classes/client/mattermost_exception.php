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
 * @package     mod_mattermost
 * @copyright   2020 Manoj <manoj@brightscout.com>
 * @author      Manoj <manoj@brightscout.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mattermost\client;
use Exception;

class mattermost_exception extends Exception {
	public function __construct($response, $code = 0, Exception $previous = null) {
		$message = '';
		if (is_string($response)) {
			$message = strip_tags($response);
		} else {
            $response = json_decode($response);
			$message = isset($response->error) ? $response->error : $response->message;
			$code = $code || $response->code;
		}
		parent::__construct($message, $code, $previous);
	}
}
