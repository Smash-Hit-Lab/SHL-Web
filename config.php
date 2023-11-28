<?php

require_once "database.php";

function set_config($key, $value, $valid = null) {
	/**
	 * Set a key in the site config. If $valid is an array then the value will only
	 * be set if $value is in $valid.
	 */
	
	if ($valid) {
		// Validate that we have a valid option
		if (array_search($value, $valid, true) === false) {
			return;
		}
	}
	
	$db = new Database("site");
	$config = new stdClass;
	
	if ($db->has("settings")) {
		$config = $db->load("settings");
	}
	
	$config->$key = $value;
	$db->save("settings", $config);
}

function get_config($key, $default = null) {
	/**
	 * Get a config key, with a default fallback if the key does not exist.
	 */
	
	$db = new Database("site");
	
	if ($db->has("settings")) {
		$config = $db->load("settings");
		
		if (property_exists($config, $key)) {
			return $config->$key;
		}
		else {
			return $default;
		}
	}
	else {
		return $default;
	}
}

