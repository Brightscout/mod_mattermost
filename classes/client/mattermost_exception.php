<?php
namespace mod_mattermost\client;
use Exception;

class MattermostException extends Exception {
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
