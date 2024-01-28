<?php

/**
 * Note: The MD5 magics are just for a bit of extra stuff, they don't actaully do
 * anything security wise since they are easily cracked (e.g. time attacks) and
 * most are public.
 */

if (!defined("APP_LOADED")) {
    die();
}

class WeakUser {
	public $id;
	public $token;
	public $creator;
	public $created;
	public $updated;
	public $props;
	
	function __construct(string $id) {
		$db = new Database("weakuser");
		
		if ($db->has($id)) {
			copy_object_vars($this, $db->load($id));
		}
		else {
			$this->id = $id;
			$this->token = null;
			$this->creator = "";
			$this->created = time();
			$this->updated = time();
			$this->props = new stdClass();
		}
	}
	
	function save() {
		$this->updated = time();
		
		$db = new Database("weakuser");
		$db->save($this->id, $this);
	}
	
	function pseudodelete() : void {
		/**
		 * Kind of deletes the account by clearing the creator name and setting
		 * the token to null.
		 */
		
		$this->creator = "";
		$this->token = null;
	}
	
	function is_deleted() : bool {
		return $this->token === null;
	}
	
	function set_token(string $token) : void {
		/**
		 * Set the login token/password/thing
		 */
		
		$this->token = password_hash($token, PASSWORD_ARGON2ID);
	}
	
	private function check_token(string $token) : bool {
		/**
		 * Check the user token against the saved one
		 */
		
		return $this->token ? password_verify($token, $this->token) : false;
	}
	
	function get_if_token_okay(string $token) : ?WeakUser {
		/**
		 * Return $this if the token's okay
		 */
		
		return (($this->check_token($token)) ? $this : null);
	}
}

function weak_user_current(?string $uid, ?string $token) : ?WeakUser {
	/**
	 * Get the current weak user, given the uid and token
	 */
	
	if (!weak_user_exists($uid) || !$uid || !$token) {
		return null;
	}
	
	$w = new WeakUser($uid);
	return $w->get_if_token_okay($token);
}

function weak_user_exists(string $uid) : bool {
	/**
	 * Check if a weak user exists
	 */
	
	$db = new Database("weakuser");
	return $db->has($uid);
}

$gEndMan->add("weak-user-check", function (Page $page) {
	/**
	 * Check for weak user, create if it does not exist.
	 */
	
	// API key because idk
	if ($page->get("magic") != md5("theGameInIts")) {
		$page->info("error", "There was an error doing that.");
	}
	
	$uid = $page->get("uid");
	$token = $page->get("token");
	
	// validate weak user info
	if (strlen($uid) != 35 || strspn($uid, "abcdef1234567890-") != strlen($uid) || strlen($token) < 64) {
		$page->info("error", "There was an error doing that.");
	}
	
	// create it if it does not exist
	if (!weak_user_exists($uid)) {
		$w = new WeakUser($uid);
		$w->set_token($token);
		$w->save();
	}
	
	// Get the weak user
	$weak = weak_user_current($uid, $token);
	
	// Set final status
	$page->set("status", ($weak !== null) ? "done" : "error");
	$page->set("message", ($weak !== null) ? "The login information is correct." : "There was an error doing that.");
});

$gEndMan->add("weak-user-set-name", function (Page $page) {
	/**
	 * Set the name if the weak user does not exist.
	 */
	
	// API key because idk
	if ($page->get("magic") != md5("thisApiSucks")) {
		$page->info("error", "There was an error doing that.");
	}
	
	$uid = $page->get("uid");
	$token = $page->get("token");
	
	// Get the weak user
	$weak = weak_user_current($uid, $token);
	
	if ($weak) {
		$weak->creator = $page->get("name", true, 512);
		$weak->save();
		$page->info("done", "The creator name has been set.");
	}
	else {
		$page->info("not_found", "Your account information could not be loaded.");
	}
});

$gEndMan->add("weak-user-lookup", function (Page $page) {
	if ($page->get("magic") != md5("justALittleFoxxo")) {
		$page->info("error", "There was an error doing that.");
	}
	
	$uid = $page->get("uid");
	
	if (!weak_user_exists($uid)) {
		$page->info("not_found", "There is no weak user by that name.");
	}
	
	$weak = new WeakUser($uid);
	
	$page->set("id", $weak->id);
	$page->set("deleted", $weak->is_deleted());
	$page->set("creator", $weak->creator);
	$page->set("created", $weak->created);
	$page->set("updated", $weak->updated);
});


function weak_user_from_page_cookies(Page $page) : ?WeakUser {
	return weak_user_current($page->get_cookie("shatter_uid"), $page->get_cookie("shatter_token"));
}

$gEndMan->add("weak-user-login-ui", function (Page $page) {
	/**
	 * Weak user login ui
	 */
	
	$page->set_mode(PAGE_MODE_HTML);
	KSHeader($page);
	
	if (!$page->has("submit")) {
		$form = new Form("./api.php?action=weak-user-login-ui&submit=1");
		$form->textbox("uid", "User ID", "");
		$form->password("token", "Token", "");
		$form->submit("Log in");
		
		$page->add($form);
	}
	else {
		$page->cookie("shatter_uid", $page->get("uid"));
		$page->cookie("shatter_token", $page->get("token"));
		$page->redirect("./api.php?action=weak-user-delete-ui");
	}
});

$gEndMan->add("weak-user-delete-ui", function (Page $page) {
	$page->set_mode(PAGE_MODE_HTML);
	
	$user = weak_user_from_page_cookies($page);
	
	if ($user) {
		KSHeader($page);
		
		if (!$page->has("submit")) {
			$page->add("<h1>Confirm account deletion</h1><p>You are deleting the account with the creator name \"$user->creator\" and the user ID $user->id. <b>This is permanent and cannot be undone.</b></p><div class=\"warning\"><p>Note: Even after you delete your account, data about your segments will still be available, and this user ID can never be used again.</p></div><p><a href=\"./api.php?action=weak-user-delete-ui&submit=1\"><button class=\"red-button\">Delete account</button></p>");
		}
		else {
			$user->pseudodelete();
			$user->save();
			$page->add("Account deleted");
		}
		
		KSFooter($page);
	}
	else {
		$page->add("<h1>Wrong or bad uid or token</h1><p>Please log in to your weak account before doing this. This can also happen if you entered the wrong UID or token.</p>");
	}
});

$gEndMan->add("weak-user-delete-admin-ui", function (Page $page) {
	$page->set_mode(PAGE_MODE_HTML);
	KSHeader($page);
	
	$user = user_get_current();
	
	if ($user && $user->is_admin()) {
		if (!$page->has("submit")) {
			$page->heading(1, "Delete weak user");
			$form = new Form("./api.php?action=weak-user-delete-admin-ui&submit=1");
			$form->textbox("uid", "User ID", "");
			$form->submit("Delete weak user");
			$page->add($form);
		}
		else {
			$weak = new WeakUser($page->get("uid"));
			$weak->pseudodelete();
			$weak->save();
			$page->para("Account deleted");
		}
	}
	else {
		$page->info("Not authed", "You are not authed, you need to be authed.");
	}
	
	KSFooter($page);
});
