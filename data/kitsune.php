<?php

/**
 * kitsune is basically some basic file storage service
 */

function kitsune_make_name(string $cat, string $item) {
	return "kitsune_" . sha256($cat) . "_" . sha256($item);
}

$gEndMan->add("kitsune-set", function (Page $page) {
	/**
	 * Setting property on kitsune is limited those with the role
	 */
	
	global $gStorage;
	
	$page->set_mode(PAGE_MODE_API);
	$user = user_get_current();
	
	if ($user && $user->is_admin() && $user->has_role("kitsune")) {
		$page->csrf($user);
		
		$name = kitsune_make_name($page->get("cat"), $page->get("item"));
		
		$gStorage->save($name, $page->get("content"));
		
		$page->set("status", "done");
		$page->set("message", "Set item in kitsune storage successfully!");
		$page->set("internal_name", $name);
	}
	else {
		$page->info("not_authed", "Endpoint requires login and kitsune role");
	}
});

$gEndMan->add("kitsune-get", function (Page $page) {
	/**
	 * Accessing storage on a kitsune does not need auth.
	 */
	
	global $gStorage;
	
	$page->set_mode(PAGE_MODE_RAW);
	$page->type("application/octet-stream");
	
	$name = kitsune_make_name($page->get("cat"), $page->get("item"));
	
	if ($gStorage->has($name)) {
		$page->add($gStorage->load($name));
	}
	else {
		$page->add("Not found");
	}
});
