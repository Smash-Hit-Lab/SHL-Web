<?php

if (!defined("APP_LOADED")) {
	die();
}

// Main endpoint manager
require_once "endpoint.php";

// Event manager
require_once "event.php";

// Everying else
require_once "auth.php";
require_once "config.php";
require_once "crypto.php";
require_once "database.php";
require_once "form.php";
require_once "page.php";
require_once "storage.php";
require_once "user.php";
require_once "util.php";

function handle_action(string $action, Page $page) {
	/**
	 * Handle the given action
	 */
	
	global $gEndMan;
	
	$okay = $gEndMan->run($action, $page);
	
	if (!$okay) {
		$page->info("Sorry", "The action you have requested is not currently implemented.");
	}
	else {
		// We do send in case the endpoint doesn't do it.
		$page->send();
	}
}

function kwl_main() {
	/**
	 * Called in the index.php script
	 */
	
	$page = new Page();
	
	$page->info("service_down", "This service has been sunset and is no longer available as of 24 November 2023.");
	$page->send();
	
	if (array_key_exists("action", $_GET)) {
		handle_action($_GET["action"], $page);
	}
	else {
		$page->info("missing_action", "An action to preform is required.");
		$page->send();
	}
}
