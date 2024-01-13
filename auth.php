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
	// Global header
	$page->global_header();
	
	// Check if logins are enabled
	auth_login_availability($page);
	
	// Create the login form
	$form = new Form("./?a=auth-login&submit=1");
	$form->set_container_type(FORM_CONTAINER_BLANK);
	$form->textbox("handle", "Handle", "The handle is the account name that you signed up for.");
	$form->password("password", "Password", "Your password was sent to your email when your account was created.");
	$form->submit("Login");
	
	$page->force_bs();
	$page->add("<div class=\"card auth-form-box\" style=\"max-width: 50em; margin: auto;\"><div class=\"card-body\">");
	
	// Heading and text
	$page->heading(1, "Log in", "20pt");
	$page->para("Enter your handle and password to log in to the Smash Hit Lab. Don't have an account? <a href=\"./?a=auth-register\">Create an account!</a>");
	$page->para("Forgot your password? <a href=\"./?n=password-resets\">Learn about password resets</a>");
	
	// Add form
	$page->add($form);
	
	$page->add("</div></div>");
	
	// Add the global footer
	$page->global_footer();
}

function auth_login_action(Page $page) {
	global $gEvents;
	
	$handle = $page->get("handle", true, 24, SANITISE_HTML, true);
	$password = $page->get("password", true, 100, SANITISE_NONE, true);
	$ip = crush_ip();
	$real_ip = $_SERVER['REMOTE_ADDR'];
	
	// Check if logins are enabled
	auth_login_availability($page, $handle);
	
	// Before login event
	$gEvents->trigger("user.login.before", $page);
	
	// Validate the handle
	if (!validate_username($handle)) {
		$page->info("Sorry!", "Your handle isn't valid. Handles can be at most 24 characters and must only use upper and lower case A - Z as well as underscores (<code>_</code>), dashes (<code>-</code>) and fullstops (<code>.</code>).");
	}
	
	// Check that the handle exists
	if (!user_exists($handle)) {
		$gEvents->trigger("user.login.failed.wrong_handle", $page);
		
		$page->info("Login failed!", "There isn't any user with the name you typed. Make sure that there are no errors in the handle you typed and try again.");
	}
	
	// Now that we know we can, open the user's info!
	$user = new User($handle);
	
	// Check if this user or their IP is banned, if they are not admin
	if (!$user->is_admin()) {
		// User ban
		if ($user->is_banned()) {
			$gEvents->trigger("user.login.failed.banned", $page);
			
			$until = $user->unban_date();
			
			if ($until == "forever") {
				$page->info("You are banned forever", "You have been banned from the Smash Hit Lab.");
			}
			
			$page->info("You are banned", "You have been banned from the Smash Hit Lab until " . date("Y-m-d h:i", $until) . ".");
		}
		
		// IP ban
		if (is_ip_blocked($ip)) {
			$gEvents->trigger("user.login.failed.ip_block", $page);
			
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
	
	// We should be able to log the user in
	$page->cookie("tk", $token->get_id(), 60 * 60 * 24 * 14);
	$page->cookie("lb", $token->make_lockbox(), 60 * 60 * 24 * 14);
	
	// Final event for login
	$gEvents->trigger("user.login.after", $page);
	
	// Redirect to user page
	if ($page->has("redirect")) {
		$page->redirect($page->get("redirect"));
	}
	else {
		$page->redirect("./@$handle");
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
	// Global header
	$page->global_header();
	
	// Check if logins are enabled
	auth_register_availability($page);
	
	// Create the login form
	$form = new Form("./?a=auth-register&submit=1");
	$form->textbox("handle", "Handle", "Pick a handle name that you would like. Please note that you can't change it later.");
	$form->password("password", "Password", "Your password should be at least 12 characters long and be different from any other password you've used. If you leave this blank, a secure password will be generated for you.", "", true, true);
	$form->day("birth", "Birthday", "Please enter your birthday so we can verify that you are old enough to join the Smash Hit Lab.");
	$form->container("Terms", "Terms help protect us from each other and set standards on how we should behave.", "
			<p>When you sign up for an account, you agree to the following documents:</p>
			<ul>
				<li><a href=\"./?p=tos\">Terms of Service</a></li>
				<li><a href=\"./?p=privacy\">Privacy Policy</a></li>
				<li><a href=\"./?p=disclaimer\">General Disclaimers</a></li>
			</ul>
			<p>You need to be 16 or older in order to use the Smash Hit Lab. We will remove your account if we find that you are under 16 years old.</p>");
	$form->submit("Create account");
	
	$page->force_bs();
	$page->add("<div class=\"auth-form-box\">");
	
	// Heading and text
	$page->heading(1, "Create an account", "20pt");
	$page->para("To create an account at the Smash Hit Lab, decide what your handle will be, enter your email address and brithdate, then create your account. Already have an account? <a href=\"./?a=auth-login\">Log in!</a>");
	
	// Add form
	$page->add($form);
	
	$page->add("</div>");
	
	// Add the global footer
	$page->global_footer();
}

function auth_register_action(Page $page) {
	global $gEvents;
	
	$email_required = get_config("email_required", false);
	
	$handle = $page->get("handle", true, 24);
	$email = $page->get("email", $email_required, 300);
	$ip = crush_ip();
	$birthdate = datetounix($page->get("birth-day"), $page->get("birth-month"), $page->get("birth-year"));
	
	// Check if we can register
	auth_register_availability($page);
	
	$gEvents->trigger("user.register.before", $page);
	
	// Blocked IP address check
	if (is_ip_blocked($ip)) {
		$gEvents->trigger("user.register.failed.ip_block", $page);
		
		$page->info("Blocked location", "This location has been denylisted and cannot be used for logins or account registers.");
	}
	
	// Make sure the handle is valid
	if (!validate_username($handle)) {
		$page->info("Bad handle", "Your handle isn't valid. Handles can be at most 24 characters and must only use upper and lower case A - Z as well as underscores (<code>_</code>), dashes (<code>-</code>) and fullstops (<code>.</code>).");
	}
	
	// See if the user already exists
	if (user_exists($handle)) {
		$gEvents->trigger("user.register.failed.user_exists", $page);
		
		$page->info("User already exists", "There is already a user with the handle that you chose. Please try another handle.");
	}
	
	// Check if the user is of age
	if ($birthdate > (time() - 60 * 60 * 24 * 365 * 16)) {
		$gEvents->trigger("user.register.failed.underage", $page);
		
		$page->info("Too young", "Unforunately, you are too young to use our website. If you entered your birthday incorrectly, please try again.");
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
	
	// Password email
	// Yes there is a more readable version of this available as the original
	// HTML file. :)
	$body = "<!DOCTYPE html>\n<html>\n\t<head>\n\t\t<title>Smash Hit Lab Account Details</title>\n\t\t<style>\n\t\t\t@import url('https://fonts.googleapis.com/css2?family=Titillium+Web:wght@400;700&display=swap');\n\t\t\t\n\t\t\t.body {\n\t\t\t\tfont-family: \"Titillium Web\", monospace, sans-serif;\n\t\t\t}\n\t\t\t\n\t\t\t.main {\n\t\t\t\tmargin: 1em auto;\n\t\t\t\tpadding: 0.5em;\n\t\t\t\tborder-radius: 0.5em;\n\t\t\t\tmax-width: min(75%, 50em);\n\t\t\t}\n\t\t\t\n\t\t\tp {\n\t\t\t\tfont-size: 14pt;\n\t\t\t}\n\t\t\t\n\t\t\t.box {\n\t\t\t\tdisplay: grid;\n\t\t\t\tgrid-template-columns: 150px auto;\n\t\t\t}\n\t\t\t\n\t\t\t.box-key {\n\t\t\t\tgrid-column: 1;\n\t\t\t\tgrid-row: 1;\n\t\t\t}\n\t\t\t\n\t\t\t.box-value {\n\t\t\t\tgrid-column: 2;\n\t\t\t\tgrid-row: 1;\n\t\t\t}\n\t\t</style>\n\t</head>\n\t<body class=\"body\">\n\t\t<div class=\"main\">\n\t\t\t<p>Hello $handle!</p>\n\t\t\t<p>It seems like you registered an account at the <a href=\"https://smashhitlab.000webhostapp.com/?p=home\">Smash Hit Lab</a> from the IP address <a href=\"https://www.shodan.io/host/$ip\">$ip</a>. If it wasn't you, please report this email to <a href=\"mailto:contactcdde@protonmail.ch\">contactcdde@protonmail.ch</a> and do not mark it as spam.</p>\n\t\t\t<p>Assuming this was you, the username and password for your account is:</p>\n\t\t\t<div class=\"box\">\n\t\t\t\t<div class=\"box-key\"><p><b>Username</b></p></div>\n\t\t\t\t<div class=\"box-value\"><p>$handle</p></div>\n\t\t\t</div>\n\t\t\t<div class=\"box\">\n\t\t\t\t<div class=\"box-key\"><p><b>Password</b></p></div>\n\t\t\t\t<div class=\"box-value\"><p>$password</p></div>\n\t\t\t</div>\n\t\t\t<p>You can <a href=\"https://smashhitlab.000webhostapp.com/?p=login\">log in here</a>.</p>\n\t\t\t<p>Thank you!</p>\n\t\t</div>\n\t</body>\n</html>\n";
	
	// If we are configured to send passwords by email, then do so
	if ($email_required) {
		mail($email, "Smash Hit Lab Registration", $body, array("MIME-Version" => "1.0", "Content-Type" => "text/html; charset=utf-8"));
	}
	
	// Alert the admins of the new account
	alert("New user account @$handle was registered", "./@$handle");
	
	// If this is the first user, grant them all roles
	if (auth_register_first_user()) {
		$user->set_roles(["headmaster", "admin", "mod"]);
	}
	
	// Save the user's data
	$user->save();
	
	// Finished event
	$gEvents->trigger("user.register.after", $page);
	
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

$gEndMan->add("auth-reset-password", function(Page $page) {
	/**
	 * Note: The site should not respond differently when a user does or doesn't
	 * exist.
	 */
	if (!$page->has("submit")) {
		$page->heading(1, "Reset password");
		
		$form = new Form("./?a=auth-reset-password&submit=1");
		$form->textbox("handle", "Handle", "What was your username that you signed up for?");
		$form->textbox("code", "Code", "What was the reset code that was sent to your email?");
		$form->submit("Reset password");
		
		$page->add($form);
	}
	else {
		$handle = $page->get("handle");
		$code = $page->get("code");
		
		if (!user_exists($handle)) {
			$page->info("Reset password", "Unforunately, your password reset did not work. It might be becuase your account does not exist or you typed the code wrong.");
		}
		
		$user = new User($handle);
		
		$new_pw = $user->do_reset($code);
		
		if ($new_pw) {
			$page->info("Yay!", "Your password was reset! Your new password is <code>$new_pw</code>.");
		}
		else {
			$page->info("Reset password", "Unforunately, your password reset did not work. It might be becuase your account does not exist or you typed the code wrong.");
		}
	}
});

/**
 * Redirects for legacy pages which are still linked sometimes
 */
$gEndMan->add("login", function (Page $page) {
	$page->redirect("./?a=auth-login");
});

$gEndMan->add("register", function (Page $page) {
	$page->redirect("./?a=auth-register");
});
