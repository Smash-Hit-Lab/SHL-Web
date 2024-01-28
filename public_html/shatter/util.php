<?php

if (!defined("APP_LOADED")) {
	die();
}

function post(string $url, string $body) {
	/**
	 * Do a POST request to the given URL with the given body.
	 */
	
	$options = [
		"http" => [
			"method" => "POST",
			"header" => "Content-Type: application/json\r\n",
			"content" => $body,
			"timeout" => 3,
		]
	];
	
	$context = stream_context_create($options);
	$result = file_get_contents($url, false, $context);
	
	return $result;
}

function send_discord_message(string $message) {
	$webhook_url = get_config("discord_webhook", "");
	
	if (!$webhook_url) {
		return;
	}
	
	$body = [
		"content" => $message,
	];
	
	post($webhook_url, json_encode($body));
}

function get_ip_address() : string {
	/**
	 * Get the current IP address.
	 */
	
	return $_SERVER["REMOTE_ADDR"];
}

function frand() : float {
	return mt_rand() / mt_getrandmax();
}

function get_formatted_datetime(?int $time = null) : string {
	return date("Y-m-d H:i:s", $time === null ? time() : $time);
}

function copy_object_vars(object &$to, object $from) {
	/**
	 * Load everything from object $from into object $to
	 */
	
	foreach (get_object_vars($from) as $key => $value) {
		$to->$key = $value;
	}
}
