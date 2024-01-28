<?php

if (!defined("APP_LOADED")) {
	die();
}

/**
 * User and account related things
 */

class Token {
	public $name; // Name of the token
	public $user; // Name of the user
	public $created; // Time the user logged in
	public $expire; // Expiration date of the token
	public $key; // IP the token was created under
	
	function __construct(string $id = null) {
		$db = new Database("token");
		
		// Generate new token name
		// We just reroll until we get an unused one
		if (!$id) {
			do {
				$id = random_hex();
			} while ($db->has($id));
		}
		
		// Load an existing token
		if ($db->has($id)) {
			$token = $db->load($id);
			
			$this->id = $token->id;
			$this->user = $token->user;
			$this->created = $token->created;
			$this->expire = $token->expire;
			$this->key = property_exists($token, "key") ? $token->key : "";
		}
		// Create a new token
		else {
			$this->id = $id;
			$this->user = null;
			$this->created = time();
			$this->expire = time() + 60 * 60 * 24 * 7 * 2; // Expire in 2 weeks
			$this->key = "";
		}
	}
	
	function save() {
		$db = new Database("token");
		$db->save($this->id, $this);
	}
	
	function delete() {
		/**
		 * Delete the token so it can't be used anymore.
		 */
		
		$db = new Database("token");
		
		if ($db->has($this->id)) {
			$db->delete($this->id);
		}
	}
	
	function set_user(string $user) {
		/**
		 * Set who the token is for if not already set. We don't allow changing
		 * the name once it is set for safety reasons.
		 * 
		 * This returns the name of the issued token if it works.
		 */
		
		if ($this->user == null) {
			$this->user = $user;
			
			$db = new Database("token");
			
			$db->save($this->id, $this);
			
			return $this->id;
		}
		
		return null;
	}
	
	function get_user(?string $key = null, bool $require_key = false) {
		/**
		 * Get the username with a token, or null if the token can't be used.
		 * This will also verify the key if given.
		 * 
		 * Lockboxes are not nessicarially enforced here; if you pass in NULL
		 * then the LB isn't checked unless $require_key == true. This is
		 * kind of legacy code.
		 * 
		 * TODO Make it not work this way
		 */
		
		// Not initialised
		if ($this->user == null) {
			return null;
		}
		
		// Expired
		if (time() >= $this->expire) {
			return null;
		}
		
		// Too early
		if (time() < $this->created) {
			return null;
		}
		
		// Check the key
		$lbok = $this->verify_key($key);
		
		if (($key || $require_key) && !$lbok) {
			return null;
		}
		
		// probably okay to use
		return $this->user;
	}
	
	function get_id() : string {
		return $this->id;
	}
	
	function make_key() : string {
		/**
		 * Create a key value and store its hash
		 */
		
		$key = random_hex();
		$this->key = hash("sha256", $key);
		$this->save();
		
		return $key;
	}
	
	function verify_key(?string $key) : bool {
		/**
		 * Verify that a key matches
		 */
		
		return ($key) && (hash("sha256", $key) === $this->key);
	}
}

function get_yt_image(string $handle) : string {
	/**
	 * Get the URL of the user's YouTube profile picture.
	 */
	
	try {
		$ytpage = @file_get_contents("https://youtube.com/@$handle/featured");
		
		if (!$ytpage) {
			return "";
		}
		
		$before = "<meta property=\"og:image\" content=\"";
		
		if ($before < 0) {
			return "";
		}
		
		// Carve out anything before this url
		$i = strpos($ytpage, $before);
		$ytpage = substr($ytpage, $i + strlen($before));
		
		// Carve out anything after this url
		$i = strpos($ytpage, "\"");
		$ytpage = substr($ytpage, 0, $i);
		
		// We have the string!!!
		return $ytpage;
	}
	catch (Exception $e) {
		return "";
	}
}

function get_gravatar_image(string $email, string $default = "identicon") : string {
	/**
	 * Get a gravatar image URL.
	 */
	
	return "https://www.gravatar.com/avatar/" . md5(strtolower(trim($email))) . "?s=300&d=$default";
}

function has_gravatar_image(string $email) {
	/**
	 * Check if an email has a gravatar image
	 */
	
	return !!(@file_get_contents(get_gravatar_image($email, "404")));
}

function find_pfp($user) : string | null {
	/**
	 * One time find a user's pfp url
	 */
	
	switch ($user->image_type) {
		case "gravatar": {
			return get_gravatar_image($user->email);
		}
		case "youtube": {
			return get_yt_image($user->youtube);
		}
		default: {
			return "./avatar_default.png";
		}
	}
}

function generate_new_user_id() {
	/**
	 * Generate a new, original user ID.
	 */
	
	$id = null;
	$db = new Database("user");
	
	while ($id == null) {
		$id = random_base32(20);
		
		if ($db->has($id)) {
			$id = null;
		}
	}
	
	return $id;
}

#[AllowDynamicProperties]
class User {
	/**
	 * Represents a user and most of the state that comes with that. Unforunately,
	 * the decision to combine everything into one big table was made early on, and
	 * is not a mistake I would repeat, though switching to something better would
	 * require some effort.
	 */
	
	function __construct(string $id) {
		$db = new Database("user");
		
		if ($db->has($id)) {
			$info = $db->load($id);
			
			$this->id = $info->id;
			$this->handle = $info->handle;
			$this->display = $info->display;
			$this->pronouns = $info->pronouns;
			$this->password = $info->password;
			$this->tokens = $info->tokens;
			$this->email = $info->email;
			$this->created = $info->created;
			$this->login_wait = $info->login_wait;
			$this->verified = $info->verified;
			$this->image_type = $info->image_type;
			$this->image = $info->image;
			$this->roles = $info->roles;
			$this->sak = $info->sak;
		}
		else {
			$this->id = $id;
			$this->handle = $id;
			$this->display = "New User";
			$this->pronouns = "";
			$this->password = null;
			$this->tokens = [];
			$this->email = "";
			$this->created = time();
			$this->login_wait = 0;
			$this->verified = null;
			$this->image_type = "gravatar";
			$this->image = "";
			$this->roles = [];
			$this->sak = random_hex();
		}
	}
	
	function save() : void {
		$db = new Database("user");
		
		$db->save($this->id, $this);
	}
	
	function wipe_tokens() : array {
		/**
		 * Delete any active tokens this user has. Also returns ip's in the
		 * tokens.
		 */
		
		$tdb = new Database("token");
		$ips = [];
		
		for ($i = 0; $i < sizeof($this->tokens); $i++) {
			if ($tdb->has($this->tokens[$i])) {
				$token = new Token($this->tokens[$i]);
				$ips[] = $token->ip;
				$tdb->delete($this->tokens[$i]);
			}
		}
		
		$this->tokens = [];
		
		return $ips;
	}
	
	function delete() : void {
		/**
		 * Delete the user
		 */
		
		// Wipe tokens
		$this->wipe_tokens();
		
		// Delete the user
		$db = new Database("user");
		$db->delete($this->id);
	}
	
	function is_verified() : bool {
		return ($this->verified != null);
	}
	
	function verify_sak(string $key) : bool {
		/**
		 * Verify that the SAK is okay, and generate the next one.
		 */
		
		if ($this->sak == $key) {
			$this->sak = random_hex();
			$this->save();
			return true;
		}
		else {
			return false;
		}
	}
	
	function get_sak() : string {
		/**
		 * Get the current SAK.
		 */
		
		return $this->sak;
	}
	
	function clean_foreign_tokens() : void {
		/**
		 * Clean the any tokens this user claims to have but does not
		 * actually have.
		 */
		
		$db = new Database("token");
		$valid = array();
		
		// TODO Yes, I really shouldn't work with database primitives here, but
		// I can't find what I called the standard functions to do this stuff.
		for ($i = 0; $i < sizeof($this->tokens); $i++) {
			if ($db->has($this->tokens[$i])) {
				$token = new Token($this->tokens[$i]);
				
				if ($token->get_user() === $this->id) {
					// It should be a good token.
					$valid[] = $this->tokens[$i];
				}
				else {
					// It's a dirty one!
					$token->delete();
				}
			}
		}
		
		$this->tokens = $valid;
	}
	
	function set_password(string $password) : bool {
		/**
		 * Set the user's password.
		 * 
		 * @return False on failure, true on success
		 */
		
		$this->password = password_hash($password, PASSWORD_ARGON2I);
		
		return true;
	}
	
	function new_password() : string {
		/**
		 * Generate a new password for this user.
		 * 
		 * @return The plaintext password is returned and a hashed value is
		 * stored.
		 */
		
		$password = @random_password();
		
		$this->set_password($password);
		
		return $password;
	}
	
	function set_email(string $email) : void {
		/**
		 * Set the email for this user.
		 */
		
		$this->email = $email;
	}
	
	function authinticate(string $password) : bool {
		/**
		 * Check the stored password against the given password.
		 */
		
		return password_verify($password, $this->password);
	}
	
	function make_token() {
		/**
		 * Make a token assigned to this user
		 */
		
		$token = new Token();
		$name = $token->set_user($this->id);
		$this->tokens[] = $name;
		$this->save();
		
		return $token;
	}
	
	function login_rate_limited() {
		/**
		 * Check if the user's login should be denied because they are trying
		 * to log in too soon after trying a first time.
		 */
		
		// The login isn't allowed if they have logged in too recently.
		if ($this->login_wait >= time()) {
			return true;
		}
		
		// It has been long enough to allow, also reset the counter.
		$this->login_wait = time() + 15;
		$this->save();
		
		return false;
	}
	
	function issue_token(string $password, string $mfa = null) {
		/**
		 * Given the password and MFA string, add a new token for this user
		 * and return its name.
		 */
		
		// Deny requests coming too soon
		if ($this->login_rate_limited()) {
			return null;
		}
		
		// First, run maintanance
		$this->clean_foreign_tokens();
		
		// Check the password
		if (!$this->authinticate($password)) {
			return null;
		}
		
		// Create a new token
		$token = $this->make_token();
		
		return $token;
	}
	
	function verify(?string $verifier) : void {
		$this->verified = $verifier;
		$this->save();
	}
	
	function is_admin() : bool {
		/**
		 * Check if the user can preform administrative tasks.
		 */
		
		return $this->has_role("admin");
	}
	
	function is_mod() : bool {
		/**
		 * Check if the user can preform moderation tasks.
		 */
		
		return $this->has_role("mod") || $this->is_admin();
	}
	
	function get_display() : string {
		return $this->display ? $this->display : $this->id;
	}
	
	function set_roles(array $roles) : void {
		/**
		 * Set the user's roles
		 */
		
		$this->roles = $roles;
		$this->save();
	}
	
	function add_role(string $role) : void {
		if (array_search($role, $this->roles) === false) {
			$this->roles[] = $role;
		}
		
		$this->save();
	}
	
	function remove_role(string $role) : void {
		$index = array_search($role, $this->roles);
		
		if ($index !== false) {
			array_splice($this->roles, $index, 1);
		}
		
		$this->save();
	}
	
	function has_role(string $role) : bool {
		/**
		 * Check if the user has a certian role
		 */
		
		return (array_search($role, $this->roles) !== false);
	}
	
	function count_roles() : int {
		/**
		 * Get the number of roles this user has
		 */
		
		return sizeof($this->roles);
	}
	
	function get_role_score() : int {
		/**
		 * Get a number assocaited with a user's highest role.
		 */
		
		$n = 0;
		
		for ($i = 0; $i < sizeof($this->roles); $i++) {
			switch ($this->roles[$i]) {
				case "mod": max($n, 1); break;
				case "admin": max($n, 2); break;
				case "headmaster": max($n, 3); break;
				default: break;
			}
		}
		
		return $n;
	}
}

function user_exists(string $id) : bool {
	/**
	 * Check if a user exists in the database given their user ID.
	 */
	
	$db = new Database("user");
	return $db->has($id);
}

function check_token__(string $name, string $key) {
	/**
	 * Given the name of the token, get the user's assocaited name and the token
	 * key, or null if the token is not valid.
	 */
	
	$token = new Token($name);
	
	return $token->get_user($key, true);
}

function user_get_current() {
	/**
	 * Get the current user
	 */
	
	if (!array_key_exists("token", $_COOKIE) || !array_key_exists("key", $_COOKIE)) {
		return null;
	}
	
	$name = check_token__($_COOKIE["token"], $_COOKIE["key"]);
	
	return ($name) ? (new User($name)) : null;
}

function user_lookup_handle(?string $user_id) : ?string {
	/**
	 * Find a user's handle given their user ID.
	 */
	
	if ($user_id === null) {
		return null;
	}
	
	if (!user_exists($user_id)) {
		return null;
	}
	
	$user = new User($user_id);
	return $user->handle;
}

function user_lookup_id(?string $handle) : ?string {
	/**
	 * Give an user's handle, find their user ID
	 */
	
	$db = new Database("user");
	return $db->where_one(["handle" => $handle]);
}

function user_get_from_handle(?string $handle) : ?User {
	if (!$handle) {
		return null;
	}
	
	$user_id = user_lookup_id($handle);
	
	if (!$user_id) {
		return null;
	}
	
	$user = new User($user_id);
	
	return $user;
}
