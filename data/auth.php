<?php

/**
 * LOGIN FORM
 */

function auth_login_availability(Page $page, ?string $handle = null) {
	/**
	 * Check the status of being able to log in. The user handle should be passed
	 * when actually logging in, but can be used to show login disabled messages
	 * early if they are disabled sitewide.
	 */
	
	$verified = true;
	$admin = true;
	
	if ($handle) {
		$u = new User($handle);
		
		$verified = $u->is_verified();
		$admin = $u->is_admin();
	}
	
	switch (get_config("enable_login", "users")) {
		case "closed":
			$page->info("Sorry!", "Logging in has been disabled for all users. Please join our Discord server for updates.");
			break;
		case "admins":
			if (!$admin) {
				$page->info("Sorry!", "We have disabled logging in for most users at the moment. Please join our Discord for any updates.");
			}
			break;
		case "verified":
			if (!$admin && !$verified) {
				$page->info("Sorry!", "We have disabled logging in for most users at the moment. Please join our Discord for any updates.");
			}
			break;
		case "users":
			break;
		default:
			$page->info("This is strange!", "The site operator has not configured the site correctly. To be safe, no one is allowed to log in. Please have the hosting party delete the invalid file at \"data/db/site/settings\", then logins will be enabled again.");
			break;
	}
}

function auth_login_form(Page $page) {
	// Check if logins are enabled
	auth_login_availability($page);
	
	$page->add("<div class=\"card auth-form-box\" style=\"max-width: 30em; margin: auto;\"><div class=\"card-header\"><b>Log in or sign up</b></div><ul class=\"list-group list-group-flush\"><li class=\"list-group-item\">");
	
	if (!$page->has("nodiscord")) {
		$page->add("<p class=\"card-text text-body-secondary\">By logging in or signing up, you agree to our <a href=\"./!tos\">Terms of Service</a> and <a href=\"./!privacy\">Privacy Policy</a>. You agree that you are at least 16 years or older and are able to legally access the service.</p>");
		$page->add("</li><li class=\"list-group-item\">");
		
		// Discord
		$page->para("The recommended way to log in or sign up is using Discord!");
		$page->para("<a href=\"./?a=auth-discord\"><button type=\"button\" class=\"btn btn-primary w-100\" style=\"background: #5065F6;\">Log in or sign up using Discord</button></a>");
		$page->add("</li><li class=\"list-group-item\">");
		
		// No discord
		$page->para("If you made an account without using Discord, you can also sign in with a password:");
		$page->para("<a href=\"./?a=auth-login&nodiscord=1\"><button type=\"button\" class=\"btn btn-outline-secondary w-100\">Log in using password</button></a>");
		
		// Other options
		$page->add("</li><li class=\"list-group-item\">");
		$page->para("You can find some other sign up options here:");
		$page->para("<a href=\"./?a=auth-register\"><button type=\"button\" class=\"btn btn-outline-secondary w-100\">Other sign up options</button></a>");
	}
	else {
		$form = new Form("./?a=auth-login&submit=1");
		$form->set_container_type(FORM_CONTAINER_BLANK);
		$form->textbox("handle", "Handle", "The handle is the account name that you signed up for.");
		$form->password("password", "Password", "Your password was sent to your email when your account was created.");
		$form->submit("Login");
		
		$page->para("Enter your handle and password to log in to the Smash Hit Lab.");
		$page->add($form);
	}
	
	$page->add("</li></ul></div>");
}

function auth_login_action(Page $page) {
	global $gEvents;
	
	$handle = $page->get("handle", true, 24, SANITISE_HTML, true);
	$password = $page->get("password", true, 100, SANITISE_NONE, true);
	$ip = crush_ip();
	$real_ip = $_SERVER['REMOTE_ADDR'];
	
	// Check if logins are enabled
	auth_login_availability($page, $handle);
	
	// Validate the handle
	if (!validate_username($handle)) {
		$page->info("Sorry!", "Your handle isn't valid. Handles can be at most 24 characters and must only use upper and lower case A - Z as well as underscores (<code>_</code>), dashes (<code>-</code>) and fullstops (<code>.</code>).");
	}
	
	// Check that the handle exists
	if (!user_exists($handle)) {
		$page->info("Login failed!", "There isn't any user with the name you typed. Make sure that there are no errors in the handle you typed and try again.");
	}
	
	// Now that we know we can, open the user's info!
	$user = new User($handle);
	
	// Check if this user or their IP is banned, if they are not admin
	if (!$user->is_admin()) {
		// User ban
		if ($user->is_banned()) {
			$until = $user->unban_date();
			
			if ($until == "forever") {
				$page->info("You are banned forever", "You have been banned from the Smash Hit Lab.");
			}
			
			$page->info("You are banned", "You have been banned from the Smash Hit Lab until " . date("Y-m-d h:i", $until) . ".");
		}
		
		// IP ban
		if (is_ip_blocked($ip)) {
			$page->info("Sorry!", "Something went wrong while logging in. Make sure your username and password are correct, then try again.");
		}
	}
	
	// Now that we should be good, let's try to issue a token
	$token = $user->issue_token($password);
	
	if (!$token) {
		$gEvents->trigger("user.login.failed.wrong_password", $page);
		
		// If this is an admin, warn about failed logins.
		if ($user->is_admin()) {
			// mail($user->email, "Failed login for " . $handle, "For site safety purposes, admins are informed any time a failed login occurs on their account. If this was you, there is no need to worry.\n\nUsername: " . $handle . "\nPassword: " . htmlspecialchars($password) . "\nIP Address: " . $real_ip);
			alert("Failed login for admin @$handle.");
		}
		
		// We send a notification to that user when they fail to log in
		// NOTE Since we don't really have any rate limits on logins I have
		// made this available to all users, instead of admins like on the old
		// login handling code.
		notify($user->name, "Login failed from $real_ip", "/");
		
		$page->info("Sorry!", "Something went wrong while logging in. Make sure your username and password are correct, then try again.");
	}
	
	// Make token id and lockbox
	$tk = $token->get_id();
	$lb = $token->make_lockbox();
	
	// We should be able to log the user in
	if (!$page->has("api")) {
		$page->cookie("tk", $tk, 60 * 60 * 24 * 14);
		$page->cookie("lb", $lb, 60 * 60 * 24 * 14);
		
		// Redirect to user page
		if ($page->has("redirect")) {
			$page->redirect($page->get("redirect"));
		}
		else {
			$page->redirect("./@$handle");
		}
	}
	else {
		$page->set_mode(PAGE_MODE_API);
		$page->set("status", "done");
		$page->set("message", "Logged in successfully.");
		$page->set("tk", $tk);
		$page->set("lb", $lb);
		$page->set("cl", hash_hmac("sha256", "$tk:$lb", get_config("cl_hmac_key")));
		$page->set("vt", time() + 60 * 60 * 24 * 14);
	}
}

$gEndMan->add("auth-login", function($page) {
	$submitting = $page->has("submit");
	
	if ($submitting) {
		auth_login_action($page);
	}
	else {
		auth_login_form($page);
	}
});

/**
 * REGISTER FORM
 */

function auth_register_availability(Page $page) {
	switch (get_config("register", "anyone")) {
		case "closed":
			$page->info("An error occured", "User account registration has been disabled for the moment. Please try again later and make sure to join the Discord for updates.");
			break;
		case "admins":
			if (!get_name_if_admin_authed()) {
				$page->info("An error occured", "We have disabled new account creation for most users at the moment. Please join our Discord and contact an admin to have them create an account for you.");
			}
			break;
		case "users":
			if (!get_name_if_authed()) {
				$page->info("An error occured", "Only existing users can create new accounts at the moment. If you have a friend who uses this site, have them enter your desired username and email for you. Otherwise, please ask staff to create an account for you.");
			}
			break;
		case "anyone":
			break;
		default:
			$page->info("An error occured", "The site operator has not configured the site corrently. To be safe, accounts will not be created. Please have the hosting party delete the invalid file at \"data/db/site/settings\", then user account creation will be enabled again.");
			break;
	}
}

function auth_register_first_user() {
	$db = new Database("user");
	
	return (sizeof($db->enumerate()) === 0);
}

function auth_register_form(Page $page) {
	// Check if logins are enabled
	auth_register_availability($page);
	
	if (!$page->has("nodiscord")) {
		$page->add("<div class=\"card auth-form-box\" style=\"max-width: 30em; margin: auto;\"><div class=\"card-header\"><b>Create an account</b></div><ul class=\"list-group list-group-flush\"><li class=\"list-group-item\">");
		
		// Heading and text
		$page->para("The recommended way to sign up is using Discord!");
		$page->para("<a href=\"./?a=auth-discord\"><button type=\"button\" class=\"btn btn-primary w-100\" style=\"background: #5065F6;\">Sign up using Discord</button></a>");
		$page->add("</li><li class=\"list-group-item\">");
		$page->para("If you are making a bot or cannot use Discord, you can also sign up with a password.");
		$page->para("<a href=\"./?a=auth-register&nodiscord=1\"><button type=\"button\" class=\"btn btn-outline-secondary w-100\">Sign up with a password</button></a>");
		
		$page->add("</li></ul></div>");
	}
	else {
		// Heading and text
		$page->heading(1, "Create an account", "20pt");
		$page->add("<div class=\"card border-danger\"><div class=\"card-body text-danger\">Starting soon, you will need to be logged in to a verified account to create a new password authenticated account. Any regular users should now use Discord to log in as it provides more security options.</div></div>");
		
		// Create the login form
		$form = new Form("./?a=auth-register&submit=1");
		$form->textbox("handle", "Handle", "A unique identifying name for the new account.");
		$form->password("password", "Password", "Account passwords should be at least 12 characters long. A secure password will be generated if this is left blank.", "", true, true);
		$form->container("Terms", "When you sign up for an account, you agree to the listed terms.", "
				<ul>
					<li><a href=\"./?p=tos\">Terms of Service</a></li>
					<li><a href=\"./?p=privacy\">Privacy Policy</a></li>
					<li><a href=\"./?p=disclaimer\">General Disclaimers</a></li>
				</ul>");
		$form->submit("Create new bot account");
		
		$page->add("<div class=\"auth-form-box\">");
		
		// Add form
		$page->add($form);
		
		$page->add("</div>");
	}
}

function auth_register_action(Page $page) {
	global $gEvents;
	
	$email_required = get_config("email_required", false);
	
	$handle = $page->get("handle", true, 24);
	$email = $page->get("email", $email_required, 300);
	$ip = crush_ip();
	
	// Check if we can register
	auth_register_availability($page);
	
	// Blocked IP address check
	if (is_ip_blocked($ip)) {
		$page->info("Blocked location", "This location has been denylisted and cannot be used for logins or account registers.");
	}
	
	// Make sure the handle is valid
	if (!validate_username($handle)) {
		$page->info("Bad handle", "Your handle isn't valid. Handles can be at most 24 characters and must only use upper and lower case A - Z as well as underscores (<code>_</code>), dashes (<code>-</code>) and fullstops (<code>.</code>).");
	}
	
	// See if the user already exists
	if (user_exists($handle)) {
		$page->info("User already exists", "There is already a user with the handle that you chose. Please try another handle.");
	}
	
	// Anything bad that can happen should be taken care of by the database...
	$user = new User($handle);
	
	// If we require emails, or one was given anyways, set it
	if ($email) {
		$user->set_email($email);
	}
	
	// Generate the new password
	$password = $page->get("password", false, 72, SANITISE_NONE);
	
	if ($password) {
		if ($password !== $page->get("password2", false, 72, SANITISE_NONE)) {
			$page->info("Invalid password", "Your passwords do not match. Please go back and try typing your passwords again!");
		}
		
		if (strlen($password) < 12) {
			$page->info("Invalid password", "Your password is too short! Your password should be at least 12 characters long.");
		}
		
		$user->set_password($password);
	}
	else {
		$password = $user->new_password();
	}
	
	// Alert the admins of the new account
	alert("New user account @$handle was registered", "./@$handle");
	
	// If this is the first user, grant them all roles
	if (auth_register_first_user()) {
		$user->set_roles(["headmaster", "admin", "mod"]);
	}
	
	// Save the user's data
	$user->save();
	
	// Print message
	if ($email_required) {
		$page->info("Account created!", "We sent an email to $email that contains your username and password.</p><p>");
	}
	else {
		$page->info("Account created!", "Your account was created successfully!</p>
		<p>Your handle is: <code>$user->name</code></p>
		<p>Your password is: <code style=\"background: #000; color: #000;\">" . htmlspecialchars($password) . "</code> (select to reveal)</p>
		<p>If you think you may forget these, please consider using a password manager like <a target=\"_blank\" rel=\"noopener noreferrer\" href=\"https://keepassxc.org/\">KeePassXC</a>!</p>");
	}
}

$gEndMan->add("auth-register", function(Page $page) {
	$submitting = $page->has("submit");
	
	if ($submitting) {
		auth_register_action($page);
	}
	else {
		auth_register_form($page);
	}
});

$gEndMan->add("auth-logout", function(Page $page) {
	$token = $page->get_cookie("tk");
	$sak = $page->get("key");
	
	// User SAK verification
	$page->csrf(user_get_current());
	
	// Delete the token on the server
	$db = new Database("token");
	$db->delete($token);
	
	// TODO Remove the token from the user
	
	// Unset cookie
	$page->cookie("tk", "", 0);
	$page->cookie("lb", "", 0);
	
	// Redirect to homepage
	$page->info("Logged out", "You have been logged out of the Smash Hit Lab.");
});

function auth_do_block_check(Page $page, ?User $user) {
	$ip = crush_ip();
	
	if (!$user || !$user->is_admin()) {
		// User ban
		if ($user && $user->is_banned()) {
			$until = $user->unban_date();
			
			if ($until == "forever") {
				$page->info("You are banned", "You have been banned from the Smash Hit Lab.");
			}
			
			$page->info("You are banned", "You have been banned from the Smash Hit Lab until " . date("Y-m-d h:i", $until) . ".");
		}
		
		// IP ban
		if (is_ip_blocked($ip)) {
			$page->info("Sorry!", "Something went wrong while logging in. Make sure your username and password are correct, then try again.");
		}
	}
}

function discord_bind_user(Page $page, string $discord_uid) {
	$user = user_get_current();
	
	if (!$user) {
		$page->info("Huh?", "It seems like you were logged out! This shouldn't have happened.");
	}
	
	$current_binding = user_with_discord_uid($discord_uid);
	
	if ($current_binding && $current_binding !== $user->name) {
		$page->info("Whoops!", "It seems like your Discord account is already bound to another user!");
	}
	
	$user->set_discord_uid($discord_uid);
	$user->save();
	
	alert("User account @$user->name was bound to <@$discord_uid>", "./@$user->name");
	
	$page->info("Success", "Your Discord account and Smash Hit Lab account have been bound! You can now use Discord to log in.");
}

function discord_user_login(Page $page, string $handle) {
	auth_login_availability($page, $handle);
	
	$user = new User($handle);
	
	auth_do_block_check($page, $user);
	
	// Everything should be okay at this point
	$token = $user->make_token();
	$tk = $token->get_id();
	$lb = $token->make_lockbox();
	
	// Set the cookies
	$page->cookie("tk", $tk, 60 * 60 * 24 * 14);
	$page->cookie("lb", $lb, 60 * 60 * 24 * 14);
	
	// Redirect to user page
	$page->redirect("./@$handle");
}

function discord_user_create(Page $page, string $discord_uid, string $base_name) {
	auth_register_availability($page);
	auth_do_block_check($page, null);
	
	$handle = user_new_handle_from_name($base_name);
	
	$user = new User($handle);
	$user->set_discord_uid($discord_uid);
	$user->save();
	
	alert("User @$handle was registered using Discord auth", "./@$handle");
	
	discord_user_login($page, $handle);
}

$gEndMan->add("auth-discord", function (Page $page) {
	$redirect_uri = "https://smashhitlab.000webhostapp.com/lab/?a=auth-discord";
	$client_id = get_config("discord_client_id");
	$client_secret = get_config("discord_client_secret");
	$discord_api = "https://discord.com/api/v10";
	
	if (!$page->has("state")) {
		$state = random_base32(32);
		$state_hash = sha256($state);
		$page->cookie("os", $state_hash);
		$page->redirect("https://discord.com/api/oauth2/authorize?client_id=$client_id&response_type=code&redirect_uri=" . urlencode($redirect_uri) . "&scope=identify&state=$state");
	}
	else if (sha256($page->get("state")) === $page->get_cookie("os")) {
		$code = $page->get("code");
		$shl_auth = "Authorization: Basic " . base64_encode("$client_id:$client_secret") .  "\r\n";
		
		// Get the access token
		$body = http_build_query([
			"grant_type" => "authorization_code",
			"code" => $code,
			"redirect_uri" => $redirect_uri,
		]);
		$token_info = post("$discord_api/oauth2/token", $body, "application/x-www-form-urlencoded", $shl_auth);
		
		if (!$token_info) {
			$page->info("Could not contact discord.");
		}
		
		// Decode the result
		$token_info = json_decode($token_info, true);
		$user_auth = "Authorization: " . $token_info["token_type"] . " " . $token_info["access_token"] .  "\r\n";
		
		// Get the user ID
		// (Note that we trust discord to get the UID correct and if not we're
		// screwed)
		$discord_user_info = http_get("$discord_api/users/@me", $user_auth);
		
		if (!$discord_user_info) {
			$page->info("Could not get user info.");
		}
		
		$discord_user_info = json_decode($discord_user_info, true);
		
		// Revoke our access token since we don't need it anymore
		$result = post("$discord_api/oauth2/token/revoke", http_build_query([
			"token" => $token_info["access_token"],
			"token_type_hint" => "access_token",
		]), "application/x-www-form-urlencoded", $shl_auth);
		
		if (!$result) {
			$page->info("Could not revoke token.");
		}
		
		// Actually preform the action
		$discord_uid = $discord_user_info["id"];
		$discord_name = $discord_user_info["username"];
		
		$user = user_get_current();
		$handle = user_with_discord_uid($discord_uid);
		
		if ($user) {
			discord_bind_user($page, $discord_uid);
		}
		else if ($handle) {
			discord_user_login($page, $handle);
		}
		else {
			discord_user_create($page, $discord_uid, $discord_name);
		}
	}
	else {
		$page->info("Error", "Authentication error. Most likely, OAuth2 state is not consistent.");
	}
});

// $gEndMan->add("auth-reset-password", function(Page $page) {
// 	/**
// 	 * Note: The site should not respond differently when a user does or doesn't
// 	 * exist.
// 	 */
// 	if (!$page->has("submit")) {
// 		$page->heading(1, "Reset password");
// 		
// 		$form = new Form("./?a=auth-reset-password&submit=1");
// 		$form->textbox("handle", "Handle", "What was your username that you signed up for?");
// 		$form->textbox("code", "Code", "What was the reset code that was sent to your email?");
// 		$form->submit("Reset password");
// 		
// 		$page->add($form);
// 	}
// 	else {
// 		$handle = $page->get("handle");
// 		$code = $page->get("code");
// 		
// 		if (!user_exists($handle)) {
// 			$page->info("Reset password", "Unforunately, your password reset did not work. It might be becuase your account does not exist or you typed the code wrong.");
// 		}
// 		
// 		$user = new User($handle);
// 		
// 		$new_pw = $user->do_reset($code);
// 		
// 		if ($new_pw) {
// 			$page->info("Yay!", "Your password was reset! Your new password is <code>$new_pw</code>.");
// 		}
// 		else {
// 			$page->info("Reset password", "Unforunately, your password reset did not work. It might be becuase your account does not exist or you typed the code wrong.");
// 		}
// 	}
// });

/**
 * Redirects for legacy pages which are still linked sometimes
 */
$gEndMan->add("login", function (Page $page) {
	$page->redirect("./?a=auth-login");
});

$gEndMan->add("register", function (Page $page) {
	$page->redirect("./?a=auth-register");
});
