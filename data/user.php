<?php

function random_hex() : string {
	/**
	 * Cryptographically secure random hex values.
	 */
	
	return bin2hex(random_bytes(32));
}

class Token {
	public $name; // Name of the token
	public $user; // Name of the user
	public $created; // Time the user logged in
	public $expire; // Expiration date of the token
	public $ip; // IP the token was created under
	public $lockbox; // IP the token was created under10
	
	function __construct(string $name = null) {
		$db = new Database("token");
		
		// Generate new token name
		// We just reroll until we get an unused one
		if (!$name) {
			do {
				$name = random_hex();
			} while ($db->has($name));
		}
		
		// Load an existing token
		if ($db->has($name)) {
			$token = $db->load($name);
			
			$this->name = $token->name;
			$this->user = $token->user;
			$this->created = $token->created;
			$this->expire = $token->expire;
			$this->ip = property_exists($token, "ip") ? $token->ip : crush_ip();
			$this->lockbox = property_exists($token, "lockbox") ? $token->lockbox : "";
		}
		// Create a new token
		else {
			$this->name = $name;
			$this->user = null;
			$this->created = time();
			$this->expire = time() + 60 * 60 * 24 * 7 * 2; // Expire in 2 weeks
			$this->ip = crush_ip();
			$this->lockbox = "";
		}
	}
	
	function save() {
		$db = new Database("token");
		$db->save($this->name, $this);
	}
	
	function delete() {
		/**
		 * Delete the token so it can't be used anymore.
		 */
		
		$db = new Database("token");
		
		if ($db->has($this->name)) {
			$db->delete($this->name);
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
			
			$db->save($this->name, $this);
			
			return $this->name;
		}
		
		return null;
	}
	
	function get_user(?string $lockbox = null, bool $require_lockbox = false) {
		/**
		 * Get the username with a token, or null if the token can't be used.
		 * This will also verify the lockbox if given.
		 * 
		 * Lockboxes are not nessicarially enforced here; if you pass in NULL
		 * then the LB isn't checked unless $require_lockbox == true. This is
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
		
		// Check the lockbox
		$lbok = $this->verify_lockbox($lockbox);
		
		if (($lockbox || $require_lockbox) && !$lbok) {
			return null;
		}
		
		// Not the same IP (TODO: Needs some extra conditions so it's not annoying)
		//if ($this->ip !== crush_ip()) {
		//	$this->delete(); // We also destroy the token in case someone has been
		//	                 // tampering with it.
		//	return null;
		//}
		
		// probably okay to use
		return $this->user;
	}
	
	function get_id() : string {
		return $this->name;
	}
	
	function make_lockbox() : string {
		/**
		 * Create a lockbox value and store its hash
		 */
		
		$lockbox = random_hex();
		$this->lockbox = hash("sha256", $lockbox);
		$this->save();
		
		return $lockbox;
	}
	
	function verify_lockbox(?string $lockbox) : bool {
		/**
		 * Verify that a lockbox matches
		 */
		
		return ($lockbox) && (hash("sha256", $lockbox) === $this->lockbox);
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
	
	$fb = "./?a=generate-logo-coloured&seed=$user->name";
	
	switch ($user->image_type) {
		case "gravatar": {
			return get_gravatar_image($user->email);
		}
		case "youtube": {
			return get_yt_image($user->youtube);
		}
		case "url": {
			return (strpos($user->image, "cdn.discordapp.com") < 0) ? $user->image : $fb;
		}
		default: {
			return $fb;
		}
	}
}

function colour_add(float $scalar, $colour) {
	$colour["red"] += $scalar;
	$colour["green"] += $scalar;
	$colour["blue"] += $scalar;
	
	return $colour;
}

function colour_mul(float $scalar, $colour) {
	$colour["red"] *= $scalar;
	$colour["green"] *= $scalar;
	$colour["blue"] *= $scalar;
	
	return $colour;
}

function colour_hex($colour) {
	return "#" . dechexa(min(floor($colour["red"] * 255), 255)) . dechexa(min(floor($colour["green"] * 255), 255)) . dechexa(min(floor($colour["blue"] * 255), 255));
}

function colour_brightness($colour) {
	$R = $colour["red"] / 255;
	$G = $colour["green"] / 255;
	$B = $colour["blue"] / 255;
	
	return max($R, $G, $B);
}

function colour_saturation($colour) {
	$R = $colour["red"] / 255;
	$G = $colour["green"] / 255;
	$B = $colour["blue"] / 255;
	$M = max($R, $G, $B); $M = $M ? $M : 1;
	
	return 1.0 - (min($R, $G, $B) / $M);
}

function special_function($n) {
	return max(1.0 - 4.0 * pow($n - 0.65, 2), -0.1);
}

function get_image_accent_colour(string $url) {
	/**
	 * Get the accent colour of the image at the given URL.
	 */
	
	if (!$url || !function_exists("imagecreatefromjpeg")) {
		return null;
	}
	
	$img = @imagecreatefromjpeg($url);
	
	// Try PNG
	if (!$img) {
		$img = @imagecreatefrompng($url);
	}
	
	if (!$img) {
		return null;
	}
	
	$colours = array();
	
	//floor(imagesx($img) / 2), floor(imagesy($img) / 2)
	
	// Get the accent colour
	// We pick the colour that is most unique. This means we need a function that
	// weighs heavy with large differences but barely does anything with small
	// ones.
	$colour = array("red" => 255, "green" => 255, "blue" => 255);
	$points_to_beat = 0;
	
	for ($i = 0; $i < 135; $i++) {
		// Pick a random point radialy (more likely to hit near the centre)
		$theta = frand() * 6.28;
		$radius = frand();
		
		$x = floor(($radius * cos($theta) + 1.0) * 0.5 * (imagesx($img) - 1));
		$y = floor(($radius * sin($theta) + 1.0) * 0.5 * (imagesx($img) - 1));
		
		$candidate = imagecolorat($img, $x, $y);
		
		// Get the proper colour names
		$candidate = imagecolorsforindex($img, $candidate);
		
		// Calculate score
		$points = colour_saturation($candidate) + 0.5 * special_function(colour_brightness($candidate));
		
		// If we've got a better score then we win!
		if ($points > $points_to_beat) {
			$colour = $candidate;
		}
	}
	
	// Dividing by 255
	$colour = colour_mul(1 / 255, $colour);
	
	// Making it the right brightness
	$colour = colour_mul(1 / colour_brightness($colour), $colour);
	
	// Normalise colour
	$n = sqrt(($colour["red"] * $colour["red"]) + ($colour["green"] * $colour["green"]) + ($colour["blue"] * $colour["blue"]));
	
	if ($n < 0.3) {
		$colour = colour_add(0.1 + $n, $colour);
		$n += 0.1;
	}
	
	$base = colour_mul(1 / $n, $colour);
	
	return derive_pallete_from_colour($base);
}

function derive_pallete_from_colour(array $base) : array {
	// Create variants
	$colours[] = colour_hex(colour_mul(0.1, $base)); // Darkest
	$colours[] = colour_hex(colour_mul(0.15, $base)); // Dark (BG)
	$colours[] = colour_hex(colour_mul(0.245, $base)); // Dark lighter
	$colours[] = colour_hex(colour_add(0.5, colour_mul(0.6, $base))); // Text
	
	return $colours;
}

function colour_from_hex($hex) : array {
	list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");
	
	return array("red" => $r / 255, "green" => $g / 255, "blue" => $b / 255);
}

function validate_username(string $name) : bool {
	$chars = str_split("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890_-.");
	
	// Charset limit
	for ($i = 0; $i < strlen($name); $i++) {
		if (array_search($name[$i], $chars, true) === false) {
			return false;
		}
	}
	
	// Size limit
	if (strlen($name) > 24) {
		return false;
	}
	
	return true;
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
	
	function __construct(string $name) {
		$db = new Database("user");
		
		if ($db->has($name)) {
			$info = $db->load($name);
			
			$this->schema = (property_exists($info, "schema") ? $info->schema : 0);
			$this->name = $info->name;
			$this->display = (property_exists($info, "display") ? $info->display : $info->name);
			$this->pronouns = (property_exists($info, "pronouns") ? $info->pronouns : "");
			$this->password = $info->password;
			$this->discord_uid = (property_exists($info, "discord_uid") ? $info->discord_uid : null);
			$this->pw_reset = (property_exists($info, "pw_reset") ? $info->pw_reset : "");
			$this->tokens = $info->tokens;
			$this->email = $info->email ? $info->email : "";
			$this->created = (property_exists($info, "created") ? $info->created : time());
			$this->login_wait = (property_exists($info, "login_wait") ? $info->login_wait : 0);
			$this->login_time = (property_exists($info, "login_time") ? $info->login_time : -1);
			$this->verified = property_exists($info, "verified") ? $info->verified : null;
			$this->ban = property_exists($info, "ban") ? $info->ban : null;
			$this->wall = property_exists($info, "wall") ? $info->wall : random_discussion_name();
			$this->youtube = property_exists($info, "youtube") ? $info->youtube : "";
			$this->image_type = property_exists($info, "image_type") ? $info->image_type : "gravatar";
			$this->image = property_exists($info, "image") ? $info->image : "";
			$this->accent = property_exists($info, "accent") ? $info->accent : null;
			$this->about = property_exists($info, "about") ? $info->about : "";
			$this->sak = property_exists($info, "sak") ? $info->sak : random_hex();
			$this->manual_colour = property_exists($info, "manual_colour") ? $info->manual_colour : "";
			$this->roles = property_exists($info, "roles") ? $info->roles : array();
			$this->mods = property_exists($info, "mods") ? $info->mods : array();
			
			// If there weren't discussions before, save them now.
			if (!property_exists($info, "wall")) {
				$this->save();
			}
			
			// If we didn't have a pfp before, find and save it now!
			if ((!$this->image) || (!$this->accent)) {
				$this->update_image();
				$this->save();
			}
			
			// Schema R1: Update discussions URL
			if ($this->schema < 2) {
				$disc = new Discussion($this->wall);
				$disc->set_url("./@$this->name");
				
				$this->schema = 2;
				$this->save();
			}
		}
		else {
			$this->name = $name;
			$this->display = $name;
			$this->pronouns = "";
			$this->password = null;
			$this->discord_uid = null;
			$this->pw_reset = "";
			$this->tokens = array();
			$this->email = "";
			$this->created = time();
			$this->login_wait = 0;
			$this->login_time = time();
			$this->verified = null;
			$this->ban = null;
			$this->wall = random_discussion_name();
			$this->youtube = "";
			$this->image_type = "generated";
			$this->image = "";
			$this->accent = null;
			$this->about = "";
			$this->sak = random_hex();
			$this->manual_colour = "";
			$this->roles = array();
			$this->mods = array();
			
			// Make sure the new user is following their wall by default.
			$d = new Discussion($this->wall);
			$d->toggle_follow($this->name);
		}
	}
	
	function save() : void {
		$db = new Database("user");
		
		$db->save($this->name, $this);
	}
	
	function wipe_tokens(bool $ipban = false, ?int $duration = null) : void {
		/**
		 * Delete any active tokens this user has. If $ipban is true, any ip's
		 * assocaited with the tokens are also banned. You must provide $duration
		 * if $ipban == true
		 */
		
		$tdb = new Database("token");
		
		for ($i = 0; $i < sizeof($this->tokens); $i++) {
			if ($tdb->has($this->tokens[$i])) {
				if ($ipban) {
					$token = new Token($this->tokens[$i]);
					block_ip($token->ip, $duration);
				}
				
				$tdb->delete($this->tokens[$i]);
			}
		}
		
		$this->tokens = array();
	}
	
	function delete() : void {
		/**
		 * Delete the user
		 */
		
		event_trigger("user.delete", $this);
		
		// We do these manually, we need to make sure they happen last.
		
		// Wipe tokens
		$this->wipe_tokens();
		
		// Delete the user
		$db = new Database("user");
		$db->delete($this->name);
	}
	
	function set_ban(?int $until) : void {
		$this->ban = ($until === -1) ? (-1) : (time() + $until);
		$this->wipe_tokens(true, $until);
		$this->save();
	}
	
	function unset_ban() : void {
		$this->ban = null;
		$this->save();
	}
	
	function ban_expired() : bool {
		return ($this->ban !== -1) && (time() > $this->ban);
	}
	
	function is_banned() : bool {
		/**
		 * Update banned status and check if the user is banned.
		 */
		
		if ($this->ban_expired()) {
			$this->unset_ban();
		}
		
		return ($this->ban !== null);
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
	
	function unban_date() : string {
		/**
		 * The user must be banned for this to return a value.
		 */
		
		if ($this->ban > 0) {
			return date("Y-m-d H:i:s", $this->ban);
		}
		else {
			return "forever";
		}
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
				
				if ($token->get_user() === $this->name) {
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
		
		$this->password = password_hash($password, PASSWORD_ARGON2ID);
		
		return true;
	}
	
	function new_password() : string {
		/**
		 * Generate a new password for this user.
		 * 
		 * @return The plaintext password is returned and a hashed value is
		 * stored.
		 */
		
		//$password = @random_password();
		
		$password = random_base64(26);
		
		$this->set_password($password);
		
		return $password;
	}
	
	function authorise_reset(string $actor) : void {
		/**
		 * Authorise a password reset for this user
		 */
		
		die();
		
		$this->pw_reset = random_base64(100);
		
		mail($this->email, "Password reset for the Smash Hit Lab", "<html><body><p>Hello $this->name,</p><p>A password reset was initialised by $actor, a member of the staff team.</p><p>Please go to this link to reset your password: <a href=\"https://smashhitlab.000webhostapp.com/?a=auth-reset-password\">https://smashhitlab.000webhostapp.com/?a=auth-reset-password</a></p><p>You will also need this code: $this->pw_reset</p><p>If you did not ask for a password reset, report this to staff immediately.</p></body></html>", array("MIME-Version" => "1.0", "Content-Type" => "text/html; charset=utf-8"));
		
		$this->pw_reset = hash("sha256", $this->pw_reset);
		
		$this->save();
	}
	
	function do_reset(string $code) : ?string {
		$reset_ok = ($this->pw_reset && ($this->pw_reset === hash("sha256", $code)));
		$pw = null;
		
		if ($reset_ok) {
			$pw = $this->new_password();
		}
		
		// we always clear the reset even if it failed
		$this->pw_reset = "";
		$this->save();
		
		return $pw;
	}
	
	function set_email(string $email) : void {
		/**
		 * Set the email for this user.
		 */
		
		$this->email = $email;
	}
	
	function authenticate(string $password) : bool {
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
		$name = $token->set_user($this->name);
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
		$this->login_wait = time() + 10;
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
		if (!$this->authenticate($password)) {
			return null;
		}
		
		// Record login time (dont need to save, its done in make_token)
		$this->login_time = time();
		
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
		
		return $this->has_role("admin") || $this->has_role("devel");
	}
	
	function is_mod() : bool {
		/**
		 * Check if the user can preform moderation tasks.
		 */
		
		return $this->has_role("mod") || $this->is_admin();
	}
	
	function update_image() : void {
		/**
		 * Update the profile image
		 */
		
		$this->image = find_pfp($this);
		
		if ($this->image) {
			$this->accent = get_image_accent_colour($this->image);
		}
		
		if ($this->manual_colour) {
			$this->accent = derive_pallete_from_colour(colour_from_hex($this->manual_colour));
		}
	}
	
	function get_image() : string {
		return $this->image;
	}
	
	function get_display() : string {
		return $this->display ? $this->display : $this->name;
	}
	
	function set_roles(array $roles) : void {
		/**
		 * Set the user's roles
		 */
		
		$this->roles = $roles;
		$this->save();
	}
	
	function toggle_role(string $role) : bool {
		/**
		 * Toggle a given role
		 */
		
		$roles = $this->roles;
		
		$key = array_search($role, $roles);
		
		$set = false;
		
		// Add role
		if ($key === false) {
			$roles[] = $role;
			$set = true;
		}
		// Remove role
		else {
			$roles = array_diff($roles, array($role));
		}
		
		$this->roles = $roles;
		$this->save();
		
		return $set;
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
	
	function add_mod(string $mod) : void {
		if (array_search($mod, $this->mods) === false) {
			$this->mods[] = $mod;
		}
		
		$this->save();
	}
	
	function remove_mod(string $mod) : void {
		$index = array_search($mod, $this->mods);
		
		if ($index !== false) {
			array_splice($this->mods, $index, 1);
		}
		
		$this->save();
	}
	
	function has_mod(string $mod) : bool {
		/**
		 * Check if the user has a certian mod
		 */
		
		return (array_search($mod, $this->mods) !== false);
	}
	
	function set_discord_uid(string $uid) {
		$this->discord_uid = $uid;
	}
}

function user_exists(string $username) : bool {
	/**
	 * Check if a user exists in the database.
	 */
	
	$db = new Database("user");
	return $db->has($username);
}

function user_with_discord_uid(string $uid) : ?string {
	/**
	 * Get the name of the user with the given user ID, or null if it doesn't
	 * exist.
	 */
	
	$db = new Database("user");
	return $db->where_one(["discord_uid" => $uid]);
}

function user_new_handle_from_name(string $base) : string {
	/**
	 * Generate a new random user handle similar to $base. This is unique.
	 */
	
	$db = new Database("user");
	
	if (!validate_username($base)) {
		while (true) {
			$handle = random_base32(12);
			
			if (!$db->has($handle)) {
				return $handle;
			}
		}
	}
	
	if (!$db->has($base)) {
		return $base;
	}
	
	while (true) {
		$handle = "$base-" . random_base32(5);
		
		if (!$db->has($handle)) {
			return $handle;
		}
	}
}

function check_token__(string $name, string $lockbox) {
	/**
	 * Given the name of the token, get the user's assocaited name, or NULL if
	 * the token is not valid.
	 */
	
	$token = new Token($name);
	
	return $token->get_user($lockbox, true);
}

function get_name_if_authed() {
	/**
	 * Get the user's name if they are authed properly, otherwise do nothing.
	 */
	
	if (!array_key_exists("tk", $_COOKIE)) {
		return null;
	}
	
	if (!array_key_exists("lb", $_COOKIE)) {
		return null;
	}
	
	return check_token__($_COOKIE["tk"], $_COOKIE["lb"]);
}

function user_get_current() {
	/**
	 * Get the current user
	 */
	
	$name = get_name_if_authed();
	
	return ($name) ? (new User($name)) : null;
}

function get_display_name_if_authed() {
	/**
	 * Get the user's preferred disply name if authed.
	 */
	
	$user = get_name_if_authed();
	
	if (!$user) {
		return null;
	}
	
	$user = new User($user);
	return $user->display ? $user->display : $user->name;
}

function get_name_if_admin_authed() {
	/**
	 * Get the user's name if they are authed and they are an admin.
	 */
	
	$user = get_name_if_authed();
	
	// Check if authed
	if (!$user) {
		return null;
	}
	
	$user = new User($user);
	
	// Check if admin
	if (!$user->is_admin()) {
		return null;
	}
	
	return $user->name;
}

function get_name_if_mod_authed() {
	/**
	 * Get the user's name if they are authed and they are a moderator.
	 * 
	 * Note: Technically this was made after moving away from get_name_if*
	 * functions but there is some code in discussions that uses this and it's
	 * just easier if we create this function.
	 */
	
	$user = get_name_if_authed();
	
	// Check if authed
	if (!$user) {
		return null;
	}
	
	$user = new User($user);
	
	// Check if admin
	if (!$user->is_mod()) {
		return null;
	}
	
	return $user->name;
}

function user_get_sak() : string {
	/**
	 * Get the SAK of the current user.
	 */
	
	$user = get_name_if_authed();
	
	if (!$user) {
		return "";
	}
	
	return (new User($user))->get_sak();
}

function user_verify_sak(string $key) : bool {
	/**
	 * Verify that the SAK of the current user matches the given one.
	 */
	
	$user = get_name_if_authed();
	
	if (!$user) {
		return false;
	}
	
	return (new User($user))->verify_sak($key);
}

function get_nice_display_name(string $user, bool $badge = true) {
	/**
	 * Get a nicely formatted display name for any user.
	 */
	
	if (!user_exists($user)) {
		return "deleted user";
	}
	
	$user = new User($user);
	
	$string = "";
	
	if ($user->name == $user->display) {
		$string = "<a href=\"./@$user->name\">$user->name</a> (@$user->name)";
	}
	else {
		$string = "<a href=\"./@$user->name\">$user->display</a> (@$user->name)";
	}
	
	return $string;
}

function get_user_badge(User $user) {
	/**
	 * Get a user's badge.
	 */
	
	$devel = $user->has_role("devel");
	
	$badge = "";
	
	if ($user->is_admin() && !$devel) {
		$badge .= "<span class=\"small-text staff-badge\">Owner</span>";
	}
	else if ($user->is_mod() && !$devel) {
		$badge .= "<span class=\"small-text moderator-badge\">Moderator</span>";
	}
	else if ($user->is_verified()) {
		$badge .= "<span class=\"small-text verified-badge\">Verified</span>";
	}
	
	if ($user->is_banned()) {
		$badge .= "<span class=\"small-text banned-badge\">Banned</span>";
	}
	
	return $badge;
}

function get_profile_image(string $user) {
	/**
	 * Get the URL to a user's profile image.
	 */
	
	$user = new User($user);
	
	$pfpi = (ord($user->name[0]) % 6) + 1;;
	
	return $user->image;
}

$gEndMan->add("account-edit", function (Page $page) {
	$user = user_get_current();
	
	$page->force_bs();
	
	if ($user) {
		if (!$page->has("submit")) {
			$page->title("Edit your account");
			$page->heading(1, "Edit account info");
			
			$form = new Form("./?a=account-edit&submit=1");
			
			$form->textbox("display", "Display name", "This is the name that will be displayed instead of your handle. It can be any name you prefer to be called.", $user->display);
			$form->textbox("pronouns", "Pronouns", "These are your perferred pronouns; for example, he/him, she/her or they/them. They will be displayed by your name in some contexts.", $user->pronouns);
			$form->textaera("about", "About", "You can write a piece of text detailing whatever you like on your userpage. Please don't include personal information!", $user->about);
			
			$available_types = [
				"gravatar" => "Gravatar",
				"youtube" => "YouTube",
				"generated" => "Lab Logo",
			];
			
			$form->select("image_type", "Profile image source", "Please chose what service your profile image should be derived from.", $available_types, $user->image_type);
			$form->textbox("youtube", "YouTube", "The handle for your YouTube account, if you have one.", $user->youtube);
			$form->textbox("email", "Email", "The email address that you prefer to be contacted about for account related issues.", $user->email, !$user->is_admin());
			$form->textbox("colour", "Page colour", "The base colour that the colour of your userpage is derived from. Represented as hex #RRGGBB.", $user->manual_colour);
			
			$form->submit("Save account info");
			
			$page->add($form);
			
			$page->heading(2, "Bind your account to Discord");
			$page->para("If you want to be able to log in using Discord, you can use this to set your current Discord account as being assocaited with your Smash Hit Lab account. You can also use it to change which Discord account your SHL account is bound with.");
			if (isset($user->discord_uid) && $user->discord_uid !== null) {
				$page->para("<b>Your account is currently bound to a Discord account. Discord user ID: $user->discord_uid</b>");
			}
			$page->para("<a href=\"./?a=auth-discord\"><button type=\"button\" class=\"btn btn-primary\" style=\"background: #5065F6;\">Bind to Discord</button></a>");
			
			$page->heading(2, "Change your password");
			$page->add("<p>If you would like to change your password, this can happen here. If you think your account has been hacked please contact staff.</p><p><a href=\"./?a=account-change-password\"><button class=\"btn btn-outline-primary\">Change password</button></a></p>");
			
			$page->heading(2, "Download your data");
			$page->para("At the moment we must manually collect the data we store about you. Firstly, make sure you set an email above. Then please send an email to <a href=\"mailto:cddepppp256@gmail.com\">cddepppp256@gmail.com</a>.");
			
			$page->heading(2, "Delete your account");
			$page->add("<p>If you would like to delete your account and associated data, you can start by clicking the button. <b>This action cannot be undone!</b></p><p><a href=\"./?a=account-delete\"><button class=\"btn btn-danger\">Delete account</button></a></p>");
		}
		else {
			$user = user_get_current();
			
			$page->csrf($user);
			
			$user->display = $page->get("display");
			$user->pronouns = $page->get("pronouns");
			
			if (($user->display != $user->name) && user_exists($user->display)) {
				$page->info("Whoops!", "You cannot set your display name to that of another user's handle.");
			}
			
			$new_mail = $page->get("email");
			
			if (!$user->is_admin()) {
				$user->email = $new_mail;
			}
			else if ($user->email != $new_mail) {
				$page->info("Whoops!", "Your rank prevents you from updating your email address.");
			}
			
			$user->youtube = $page->get("youtube");
			$user->manual_colour = $page->get("colour");
			
			// If the user started it with an @ then we will try to make it okay for
			// them.
			if (str_starts_with($user->youtube, "@")) {
				$user->youtube = substr($user->youtube, 1);
			}
			
			$user->image_type = $page->get("image_type");
			
			// HACK This is a quick hack for custom image urls.
			if ($user->image_type == "url" && $user->is_verified()) {
				$user->image = $page->get("imageurl");
			}
			
			$user->update_image();
			
			// Finally the about section
			// This is sanitised at display time
			$user->about = $page->get("about");
			
			$user->save();
			
			redirect("./@" . $user->name);
		}
	}
	else {
		$page->info("Oops", "You need to log in to edit your account information!");
	}
});

$gEndMan->add("user-view", function (Page $page) {
	$page->force_bs();
	
	$stalker = user_get_current();
	$handle = $page->get("handle");
	
	if (!user_exists($handle)) {
		$page->info("User not found", "We could not find any user with that name.");
	}
	
	$user = new User($handle);
	
	if ($stalker && user_block_has($stalker->name, $user->name, true, false)) {
		$page->info("Blocked user", "This user has blocked you from viewing their profile page.");
	}
	
	$page->title("$user->display (@$user->name)");
	
	$page_colour = $user->accent ? $user->accent[3] : "#ffffff";
	
	$page->add("
	<div style=\"margin-bottom: 15px; background: ".$page_colour."3f;\" class=\"card\"><div class=\"card-body\">
	<div style=\"display: grid; grid-template-columns: 128px auto;\">
		<div style=\"grid-column: 1;\">
			<img style=\"width: 128px; border-radius: 64px;\" src=\"$user->image\"/>
		</div>
		<div style=\"grid-column: 2; margin-left: 24px;\">
			<h1>$user->display</h1>
			<h3>@$handle</h3>
			<p>$user->pronouns</p>
		</div>
	</div>
	</div></div>");
	
	// $page->add("<nav style=\"margin-bottom: 20px;\">
	// 		<div class=\"nav nav-tabs\">
	// 			<button class=\"nav-link active\" id=\"nav-home-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#nav-home\" type=\"button\" role=\"tab\" aria-controls=\"nav-home\" aria-selected=\"true\">Home</button>
	// 			<button class=\"nav-link\" id=\"nav-contact-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#nav-contact\" type=\"button\" role=\"tab\" aria-controls=\"nav-contact\" aria-selected=\"false\">Actions</button>
	// 			<button class=\"nav-link\" id=\"nav-profile-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#nav-profile\" type=\"button\" role=\"tab\" aria-controls=\"nav-profile\" aria-selected=\"false\">Comments</button>
	// 		</div>
	// 	</nav>");
	
// 	$page->add("<div class=\"tab-content\" id=\"nav-tabContent\">");
// 	
// 	$page->add("<div class=\"tab-pane fade show active\" id=\"nav-home\" role=\"tabpanel\" aria-labelledby=\"nav-home-tab\" tabindex=\"0\">");
	
	if ($user->is_banned()) {
		$page->add("<div class=\"card border-danger mb-3\"><div class=\"card-body text-danger\">This user is banned until " . $user->unban_date() . ".</div></div>");
	}
	
	$page->add("<div class=\"user-body-container\">");
	
	// Left side
	$page->add("<div style=\"grid-column: 1;\">");
	
	// About
	if ($user->about) {
		$page->add("<div class=\"card mb-3\"><div class=\"card-header\"><b>Description</b></div><div class=\"card-body\">");
		$pd = new Parsedown();
		$pd->setSafeMode(true);
		$page->add(str_replace("<p>", "<p class=\"card-text\">", $pd->text($user->about)));
		$page->add("</div></div>");
	}
	
	// Stats
	$page->add("<div class=\"card mb-3\"><div class=\"card-header\"><b>Statistics</b></div><div class=\"card-body\">");
	user_show_stat($page, "Joined", Date("Y-m-d", $user->created));
	user_show_stat($page, "Last login", Date("Y-m-d", $user->login_time));
	if ($user->count_roles()) {
		user_show_stat($page, "Roles", join(", ", $user->roles));
	}
	if ($user->youtube) {
		user_show_stat($page, "YouTube", "<a href=\"https://youtube.com/@$user->youtube\">@$user->youtube</a>");
	}
	if ($stalker && $stalker->is_mod() && $user->discord_uid) {
		user_show_stat($page, "Discord (ID)", "$user->discord_uid");
	}
	$page->add("</div></div>");
	
	// Actions
	if ($stalker) {
		$page->add("<div class=\"card mb-3\"><div class=\"card-header\"><b>Actions</b></div><div class=\"card-body\">");
		
		if ($stalker->name == $user->name) {
			$page->link_button("", "Edit account", "./?a=account-edit", true, "primary", "w-100 mb-2");
		}
		
		if ($stalker->is_mod()) {
			$page->link_button("", $user->verified ? "Unmark as verified" : "Mark as verified", "./?a=user-verify&handle=$user->name&key=" . $stalker->get_sak(), false, "success", "w-100 mb-2");
		}
		
		if ($stalker->name != $user->name) {
			$page->link_button("", "Block user", "./?a=account-toggle-block&handle=$user->name&key=" . $stalker->get_sak(), false, "danger", "w-100 mb-2");
		}
		
		if ($stalker->name != $user->name && $stalker->is_mod()) {
			$page->link_button("", $user->is_banned() ? "Unban user" : "Ban user", "./?a=user_ban&handle=$user->name", false, "danger", "w-100 mb-2");
		}
		
		$page->add("</div></div>");
	}
	
	$page->add("</div>");
	
	// Right side
	$page->add("<div class=\"user-comment-container\" style=\"grid-column: 2;\">");
	$disc = new Discussion($user->wall);
	$page->add($disc->render_reverse("Comments", "./@" . $user->name));
	$page->add("</div>");
	
	$page->add("</div>");
	
	// $page->add("</div><div class=\"tab-pane fade\" id=\"nav-profile\" role=\"tabpanel\" aria-labelledby=\"nav-profile-tab\" tabindex=\"0\">");
	
	// $disc = new Discussion($user->wall);
	// $page->add($disc->render_reverse("Comments", "./@" . $user->name));
	
	// $page->add("</div>");
	// $page->add("<div class=\"tab-pane fade\" id=\"nav-contact\" role=\"tabpanel\" aria-labelledby=\"nav-contact-tab\" tabindex=\"0\">");
	
	// $page->add("</div>");
	
	// $page->add("</div>");
});

function user_show_stat(Page $page, string $title, string $value) {
	$page->add("<p class=\"card-text\"><b>$title</b><br/>$value</p>");
}

function display_user(string $user) {
	/**
	 * Display user account info
	 */
	
	$stalker = get_name_if_authed();
	
	// We need this so admins can have some extra options like banning users
	$stalker = $stalker ? (new User($stalker)) : null;
	
	if (!user_exists($user)) {
		sorry("We could not find that user in our database.");
	}
	
	$user = new User($user);
	
	// Handle user blocks at this point
	if ($stalker && user_block_has($stalker->name, $user->name, true, false)) {
		inform("Blocked user", "This user has blocked you from viewing their profile page.");
	}
	
	// HACK Page title
	global $gTitle; $gTitle = ($user->display ? $user->display : $user->name) . " (@$user->name)";
	
	include_header();
	
	// 
	// If these contains have passed, we can view the user page
	// 
	
	display_user_banner($user);
	
	// Include the tabs script
	readfile("../../data/_user_tabs.html");
	
	// If the user has an about section, then we should show it.
	echo "<div class=\"user-tab-data about\">";
	echo "<h3>About</h3>";
	if ($user->about) {
		$pd = new Parsedown();
		$pd->setSafeMode(true);
		echo $pd->text($user->about);
		//echo "<p style=\"white-space: pre-line;\">".htmlspecialchars($user->about)."</p>";
	}
	else {
		echo "<p><i>This user has not added any information to their about page.</i></p>";
	}
	echo "</div>";
	
	echo "<div class=\"user-tab-data details\">";
	echo "<h3>Details</h3>";
	mod_property("Join date", "The date that the user joined the Smash Hit Lab.", Date("Y-m-d", $user->created));
	mod_property("Last login", "The date of the last login of the user.", Date("Y-m-d", $user->login_time));
	
	// Maybe show pronouns?
	if ($user->pronouns) {
		mod_property("Pronouns", "When referring to this person using pronouns, please use their preferred pronouns.", "$user->pronouns");
	}
	
	// Show roles
	if ($user->count_roles()) {
		mod_property("Roles", "Users that are members of certian roles can preform extra administrative actions.", join(", ", $user->roles));
	}
	
	// Maybe show youtube?
	if ($user->youtube) {
		mod_property("YouTube", "This user's YouTube account.", "<a href=\"https://youtube.com/@$user->youtube\">@$user->youtube</a>");
	}
	
	// Show if the user is verified
	if ($user->is_verified()) {
		mod_property("Verified", "Verified members are checked by staff to be who they claim they are.", "Verified by $user->verified");
	}
	
	echo "</div>";
	echo "<div class=\"user-tab-data actions\">";
	
	echo "<h3>Actions</h3>";
	
	// Show the send message action
	if ($stalker) {
		mod_property("Send message", "You can send this user a message publicly via their message wall.", "<button class=\"button secondary\" onclick=\"user_tabs_select('wall')\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">forum</span> Message wall</button>");
	}
	
	if ($stalker && $stalker->is_mod()) {
		if ($user->is_banned()) {
			mod_property("Unban time", "The time at which this user will be allowed to log in again.", $user->unban_date());
		}
		
		// If the wanted user isn't admin, we can ban them
		if (!$user->is_admin() && $stalker->name != $user->name) {
			mod_property("Ban user", "Banning this user will revoke access and prevent them from logging in until a set amount of time has passed.", "<a href=\"./?a=user_ban&handle=$user->name\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">gavel</span> Ban @$user->name</button></a>");
		}
		
		// Only admins can change ranks
		if ($stalker->is_admin()) {
			mod_property("Change roles", "Roles set permissions for what users are allowed to do. They are often used for giving someone moderator or manager privleges.", "<a href=\"./?a=user_roles&handle=$user->name\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">security</span> Edit roles</button></a>");
		}
		
		mod_property("Verified", "Verified members are checked by staff to be who they claim they are.", "<a href=\"./?a=user-verify&handle=$user->name\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">verified</span> Toggle verified status</button></a>");
	}
	
	// Block user
	if ($stalker && $stalker->name != $user->name) {
		mod_property("Block user", "Blocking this user will prevent you from seeing some of the things this user does and prevent this user from seeing things you do. You might still see some things as blocking is still in a testing state.", "<a href=\"./?a=account-toggle-block&handle=$user->name&key=" . $stalker->get_sak() . "\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">block</span> Block user</button></a>");
	}
	
	echo "</div>";
	
	// Finally the message wall for this user
	// Display comments
	echo "<div class=\"user-tab-data wall\">";
	$disc = new Discussion($user->wall);
	$disc->display_reverse("Message wall", "./@" . $user->name);
	echo "</div>";
	
	// Edit profile button
	if ($stalker && $stalker->name === $user->name) {
		echo "<a href=\"./?a=edit_account\"><button style=\"position: fixed; bottom: 2em; right: 2em;\" class=\"button\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">edit</span> Edit profile</button></a>";
	}
	
	// User tab script
	echo "<script>user_tabs_init();</script>";
	
	// Colourful user profile, if we can show it
	display_user_accent_script($user);
	
	// Footer
	include_footer();
}

function display_user_banner(User $user) {
	$display_name = $user->display ? $user->display : $user->name;
	
	echo "<div class=\"profile-header\">";
		echo "<div class=\"profile-header-image-section\">";
			if ($user->image) {
				echo "<div class=\"profile-header-image-wrapper\"><img class=\"profile-header-image\" src=\"$user->image\"/></div>";
			}
		echo "</div>";
		echo "<div class=\"profile-header-userinfo\">";
			echo "<h1 class=\"left-align\">$display_name";
			if ($user->pronouns) { echo "<span class=\"pronouns-span\"> ($user->pronouns)</span>"; }
			echo "</h1>";
			echo "<h2 class=\"left-align\">@$user->name</h2>";
		echo "</div>";
	echo "</div>";
}

function duas_setvar(string $var, string $val) {
	echo "qs.style.setProperty('$var', '$val');";
}

function display_user_accent_script(User $user) {
	if ($user->accent) {
		$darkest = $user->accent[0];
		$dark = $user->accent[1];
		$darkish = $user->accent[2];
		$bright = $user->accent[3];
		
		echo "<script>var qs = document.querySelector(':root');";
		
		duas_setvar("--colour-primary", $bright);
		duas_setvar("--colour-primary-darker", "#ffffff");
		duas_setvar("--colour-primary-hover", "#ffffff");
		duas_setvar("--colour-primary-a", $bright . "40");
		duas_setvar("--colour-primary-b", $bright . "80");
		duas_setvar("--colour-primary-c", $bright . "c0");
		duas_setvar("--colour-primary-text", "#000000");
		
		duas_setvar("--colour-background-light", $darkish);
		duas_setvar("--colour-background-light-a", $darkish . "40");
		duas_setvar("--colour-background-light-b", $darkish . "80");
		duas_setvar("--colour-background-light-c", $darkish . "c0");
		duas_setvar("--colour-background-light-text", $bright);
		
		duas_setvar("--colour-background", $dark);
		duas_setvar("--colour-background-a", $dark . "40");
		duas_setvar("--colour-background-b", $dark . "80");
		duas_setvar("--colour-background-c", $dark . "c0");
		duas_setvar("--colour-background-text", $bright);
		
		duas_setvar("--colour-background-dark", $darkest);
		duas_setvar("--colour-background-dark-a", $darkest . "40");
		duas_setvar("--colour-background-dark-b", $darkest . "80");
		duas_setvar("--colour-background-dark-c", $darkest . "c0");
		duas_setvar("--colour-background-dark-text", $bright);
		duas_setvar("--colour-background-dark-text-hover", $bright);
		
		echo "</script>";
	}
}

$gEndMan->add("user-verify", function (Page $page) {
	$verifier = user_get_current();
	
	if ($verifier && $verifier->is_mod()) {
		$page->csrf($verifier);
		
		$handle = $page->get("handle");
		
		$user = new User($handle);
		
		if ($user->is_verified()) {
			$user->verify(null);
		}
		else {
			$user->verify($verifier->name);
		}
		
		alert("@$verifier->name has toggled verified status for user @$user->name", "./@$user->name");
		
		$page->redirect("./@$user->name");
	}
	else {
		$page->info("The action you have requested is not currently implemented.");
	}
});

$gEndMan->add("account-delete", function (Page $page) {
	$user = user_get_current();
	
	if ($user) {
		if (!$page->has("submit")) {
			$page->heading(1, "Delete your account");
			
			$form = new Form("./?a=account-delete&submit=1");
			
			$form->textbox("reason", "Reason", "You can optionally provide us a short reason for deleteing your account.", "");
			$form->select(
				"understand",
				"Acknowledgement",
				"<b>By deleting your account, you agree that your data will be deleted forever, and that there is no possible way we can recover it.</b>",
				[
					"0" => "No, I don't understand",
					"1" => "Yes, I understand"
				],
				"0"
			);
			
			$form->submit("Delete my account");
			$page->add($form);
		}
		else {
			$page->csrf($user);
			
			// Must accept agreement
			if (!$page->get("understand")) {
				sorry("You must agree to the acknowledgement in order to delete your account.");
			}
			
			// Must not be admin
			if ($user->is_mod()) {
				sorry("Staff accounts cannot be deleted without first being demoted.");
			}
			
			// Get the reason
			$reason = $page->get("reason", false, 500);
			
			// Send alert
			alert("User account @$user->name was deleted: $reason");
			
			// Delete the account
			$user->delete();
			
			// Show the final page ;(
			$page->add("<h1>Account deleted</h1>");
			$page->add("<p>Your account has been deleted. Goodbye!</p>");
		}
	}
	else {
		$page->info("Error!", "You need to log in to delete your account!");
	}
});

$gEndMan->add("account-change-password", function (Page $page) {
	$user = user_get_current();
	
	$page->force_bs();
	
	if ($user) {
		if (!$page->has("submit")) {
			$page->title("Change your password");
			$page->heading(1, "Change your password");
			
			$form = new Form("./?a=account-change-password&submit=1");
			$form->password("oldpassword", "Old password", "Type your old password so we can verify it is you preforming this action.");
			$form->password("newpassword", "New password", "Your new password should be at least 12 characters long and be different from any other password you've used. If you leave this blank, a secure password will be generated for you.", "", true, true);
			$form->submit("Change password");
			
			$page->add($form);
		}
		else {
			$page->csrf($user);
			
			if (!$user->authenticate($page->get("oldpassword"))) {
				$page->info("Sorry", "The old password is not correct. Please try again.");
			}
			
			$newpassword = $page->get("newpassword", false, 72, SANITISE_NONE);
			$newpwvalid = strlen($newpassword) >= 12 || strlen($newpassword) == 0;
			
			if ($newpassword === $page->get("newpassword2", false, 72, SANITISE_NONE) && $newpwvalid) {
				// New according to what the user wants
				if ($newpassword) {
					$user->set_password($newpassword);
					$page->add("<h1>Success</h1><p>Your password has been changed!</p>");
				}
				// New random password
				else {
					$password = $user->new_password();
					$page->add("<h1>Success</h1><p>Your password has been changed!</p><p>New password: <code style=\"background: #000; color: #000;\">" . htmlspecialchars($password) . "</code> (select to reveal)</p>");
				}
				
				$user->save();
				
				alert("User @$user->name changed their password.");
			}
			else {
				$page->info("Sorry", "The new passwords do not match or is too short.");
			}
		}
	}
	else {
		$page->info("Sorry", "You need to be logged in to reset your password.");
	}
});

$gEndMan->add("account-next-sak", function (Page $page) {
	$page->set_mode(PAGE_MODE_API);
	$user = user_get_current();
	
	if ($user) {
		$page->set("status", "done");
		$page->set("message", "Retrived new CSRF key successfully.");
		$page->set("key", $user->get_sak());
	}
	else {
		$page->info("not_authed", "The authentication token is not valid anymore or does not exist.");
	}
});

/**
 * Welcome message after user has registered.
 */
$gEvents->add("user.register.after", function(Page $page) {
	$handle = $page->get("handle");
	
	$user = new User($handle);
	
	$wall = new Discussion($user->wall);
	
	$wall->add_comment("smashhitlab", "Welcome to the Smash Hit Lab!\n\nIf you ever have any issues, please let one of our staff know — they will have a badge that says \"moderator\" or \"manager\" next to their name.\n\nIf you find any bugs or glitches, please report them to [knot126](./@knot126).\n\nDon't be afraid to say hello, and we hope you enjoy your stay!");
});

$gEndMan->add("user-authorise-reset", function(Page $page) {
	
});
