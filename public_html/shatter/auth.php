<?php

if (!defined("APP_LOADED")) {
	die();
}

function validate_handle(string $handle) : bool {
	return strlen($handle) === strspn($handle, "abcdefghijklmnopqrstuvwxyz1234567890-");
}

/**
 * LOGIN
 */

$gEndMan->add("auth-login", function($page) {
	global $gEvents;
	
	$handle = $page->get("handle", true, 30, SANITISE_HTML, true);
	$password = $page->get("password", true, 100, SANITISE_NONE, true);
	
	// Validate the handle
	if (!validate_handle($handle)) {
		$page->info("failed", "The login information is not valid.");
	}
	
	// Check that the handle exists
	if (!user_lookup_id($handle)) {
		$page->info("failed", "The login information is not valid.");
	}
	
	// Now that we know we can, open the user's info!
	$user = new User(user_lookup_id($handle));
	
	// Now that we should be good, let's try to issue a token
	$token = $user->issue_token($password);
	
	if (!$token) {
		$page->info("failed", "The login information is not valid.");
	}
	
	// We should be able to log the user in
	$token_id = $token->get_id();
	$token_key = $token->make_key();
	
	$page->cookie("token", $token_id, 60 * 60 * 24 * 14);
	$page->cookie("key", $token_key, 60 * 60 * 24 * 14);
	
	// Send login result info
	$page->set("status", "success");
	$page->set("message", "You have been logged in successfully.");
	$page->set("user_id", "$user->id");
	$page->set("token", "$token_id");
	$page->set("token_key", "$token_key");
});

/**
 * REGISTER FORM
 */

function auth_register_first_user() {
	$db = new Database("user");
	
	return (sizeof($db->enumerate()) === 0);
}

$gEndMan->add("auth-register", function(Page $page) {
	global $gEvents;
	
	$email = $page->get("email", true, 300);
	$handle = $page->get("handle", true, 100);
	
	// Check if we can register
	if (get_config("register", true) != true) {
		$page->info("not_allowed", "Registering has been disabled at the moment.");
	}
	
	// Make sure the handle is valid
	if (!validate_handle($handle)) {
		$page->info("invalid_handle", "Your handle isn't valid. Please make sure it matches the requirements for handles.");
	}
	
	// Make sure the handle does not already exist
	if (user_lookup_id($handle)) {
		$page->info("already_exists", "Someone is already using that handle. Please try another one.");
	}
	
	// Anything bad that can happen should be taken care of by the database...
	$user_id = generate_new_user_id();
	$user = new User($user_id);
	
	// If we require emails, or one was given anyways, set it
	if ($email) {
		$user->set_email($email);
	}
	
	// Set the user's handle
	$user->handle = $handle;
	
	// Generate the new password
	$password = $user->new_password();
	
	// If this is the first user, grant them all roles
	if (auth_register_first_user()) {
		$user->set_roles(["headmaster", "admin", "mod"]);
	}
	
	// Save the user's data
	$user->save();
	
	// Finished event
	$gEvents->trigger("user.register.after", $page);
	
	// Print message
	$page->set("status", "success");
	$page->set("message", "Your user account has been created successfully!");
	$page->set("handle", "$handle");
	$page->set("password", "$password");
	$page->set("id", "$user_id");
});

$gEndMan->add("auth-logout", function(Page $page) {
	$token = $page->get_cookie("token");
	$lockbox = $page->get_cookie("key");
	
	// Delete the token on the server
	$db = new Database("token");
	$db->delete($token);
	
	// TODO Remove the token from the user
	
	// Unset cookie
	$page->cookie("token", "", 0);
	$page->cookie("key", "", 0);
	
	// Redirect to homepage
	$page->info("success", "You have been logged out.");
});
