<?php
/**
 * Discssions for comments and reviews
 */

function random_discussion_name() : string {
	/**
	 * Cryptographically secure random values modified for discussion names.
	 */
	
	return random_base32(24);
}

#[AllowDynamicProperties]
class Comment {
	/**
	 * This is the comment class, which represents a signle comment as part of
	 * a larger discussion.
	 */
	
	public $author;
	public $body;
	public $created;
	public $updated;
	public $hidden;
	
	function __construct() {
		$this->author = "";
		$this->body = "";
		$this->created = 0;
		$this->updated = 0;
		$this->hidden = false;
	}
	
	function load(object $base) {
		$this->author = $base->author;
		$this->body = $base->body;
		$this->created = $base->created;
		$this->updated = $base->updated;
		$this->hidden = $base->hidden;
		
		return $this;
	}
	
	function create(string $author, string $message) {
		$this->author = $author;
		$this->body = $message;
		$this->created = time();
		$this->updated = time();
		
		return $this;
	}
	
	function update(string $body) {
		$this->body = $body;
		$this->updated = time();
	}
	
	function hide() {
		$this->hidden = !$this->hidden;
	}
	
	function is_hidden() {
		return $this->hidden;
	}
	
	function render_body() {
		$pd = new Parsedown();
		$pd->setSafeMode(true);
		$pd->setBreaksEnabled(true);
		return $pd->text($this->body);
		// return "<p style=\"white-space: pre-line;\">".htmlspecialchars($this->body)."</p>";
	}
}

class Discussion {
	/**
	 * This is the main discussion class, which represents one discussion.
	 */
	
	public $id;
	public $followers;
	public $comments;
	public $url;
	public $locked;
	public $access;
	
	function __construct(string $id) {
		$db = new Database("discussion");
		
		if ($db->has($id)) {
			$info = $db->load($id);
			
			$this->id = $info->id;
			$this->followers = property_exists($info, "followers") ? $info->followers : array();
			$this->comments = $info->comments;
			$this->url = property_exists($info, "url") ? $info->url : null;
			$this->locked = property_exists($info, "locked") ? $info->locked : false;
			$this->access = property_exists($info, "access") ? $info->access : null;
			
			// Make sure that comments are Comment type objects
			for ($i = 0; $i < sizeof($this->comments); $i++) {
				$this->comments[$i] = (new Comment())->load($this->comments[$i]);
			}
		}
		else {
			$this->id = $id;
			$this->followers = array();
			$this->comments = array();
			$this->url = null;
			$this->locked = false;
			$this->access = null;
		}
	}
	
	function save() {
		$db = new Database("discussion");
		$db->save($this->id, $this);
	}
	
	function delete() {
		$db = new Database("discussion");
		$db->delete($this->id);
	}
	
	function get_id() {
		return (sizeof($this->comments) > 0) ? $this->id : null;
	}
	
	function is_following(string $user) {
		/**
		 * Check if a user is following a discussion.
		 */
		
		return array_search($user, $this->followers, true) !== false;
	}
	
	function toggle_follow(string $user) {
		/**
		 * Toggle the given user's follow status for this discussion.
		 */
		
		// Remove the follower status
		if (($index = array_search($user, $this->followers, true)) !== false) {
			array_splice($this->followers, $index, 1);
		}
		// Add the follower status
		else {
			$this->followers[] = $user;
		}
		
		$this->save();
	}
	
	function set_access(string $handle) : void {
		$this->access[] = $handle;
		$this->save();
	}
	
	function has_access(string $handle) : bool {
		return ($this->access === null) || in_array($handle, $this->access);
	}
	
	function is_locked() {
		return $this->locked;
	}
	
	function toggle_locked() {
		/**
		 * Lock or unlock a thread.
		 */
		
		//            vv It's the toggle operator :P
		$this->locked =! $this->locked;
		$this->save();
	}
	
	function get_url() : ?string {
		/**
		 * Get the URL where this discussion appears
		 */
		
		return ($this->url) ? $this->url : "";
	}
	
	function set_url(string $url) : bool {
		/**
		 * Set the URL assocaited with the discussion, if not already set.
		 */
		
		if ($this->url === null) {
			$this->url = $url;
			$this->save();
			return true;
		}
		else {
			return false;
		}
	}
	
	function add_comment(string $author, string $body) : bool {
		if (!$this->has_access($author)) {
			return false;
		}
		
		$this->comments[] = (new Comment())->create($author, $body);
		$this->save();
		
		// Notify users
		// We start by grabbing the assocaited URL
		$url = $this->get_url();
		
		// Notify post followers
		notify_many(array_diff($this->followers, [$author]), "New message from @$author", $url . "#discussion-$this->id-" . (sizeof($this->comments) - 1));
		
		// Notify any mentioned users
		notify_scan($body, $url);
		
		// Admin alert!
		alert("Discussion $this->id has a new comment by @$author\nContent: " . substr($body, 0, 300) . ((strlen($body) > 300) ? "..." : ""), $url);
		
		return true;
	}
	
	function update_comment(int $index, string $author, string $body) {
		if ($this->comments[$index]->author === $author && !$this->locked) {
			$this->comments[$index]->update($body);
			$this->save();
			
			return true;
		}
		else {
			return false;
		}
	}
	
	function hide_comment(int $index) {
		if (isset($this->comments[$index])) {
			$this->comments[$index]->hide();
			$this->save();
		}
	}
	
	function delete_comment(int $index) {
		if (isset($this->comments[$index])) {
			array_splice($this->comments, $index, 1);
			$this->save();
		}
	}
	
	function get_author(int $index) {
		if (isset($this->comments[$index])) {
			return $this->comments[$index]->author;
		}
		else {
			return null;
		}
	}
	
	function delete_comments_by(string $author) {
		/**
		 * Delete comments by a given author.
		 */
		
		for ($i = 0; $i < sizeof($this->comments); $i++) {
			if ($this->get_author($i) == $author) {
				$this->delete_comment($i);
			}
		}
	}
	
	function count_all() {
		/**
		 * Count all shown and hidden comments.
		 */
		
		return sizeof($this->comments);
	}
	
	function enumerate_hidden() {
		/**
		 * Return the number of hidden comments.
		 */
		
		$hidden = 0;
		
		for ($i = 0; $i < sizeof($this->comments); $i++) {
			if ($this->comments[$i]->is_hidden()) {
				$hidden++;
			}
		}
		
		return $hidden;
	}
	
	function enumerate_shown() {
		/**
		 * Return the number of shown comments.
		 */
		
		return sizeof($this->comments) - $this->enumerate_hidden();
	}
	
	function list_since(int $index, bool $hidden = false) {
		/**
		 * Return a list of comments since (and including) a given comment, also
		 * including some extra data
		 */
		
		$size = sizeof($this->comments);
		
		if ($index > ($size - 1)) {
			return array();
		}
		
		$comments = array_slice($this->comments, $index);
		
		// Put indexes on comments
		for ($i = 0; $i < sizeof($comments); $i++) {
			$comments[$i]->index = $i;
		}
		
		// Remove hidden comments
		if (!$hidden) {
			for ($i = 0; $i < sizeof($comments);) {
				if ($comments[$i]->is_hidden()) {
					array_splice($comments, $i, 1);
				}
				// We can only increment if it doesn't exist since everything
				// will shift down when things are removed!
				else {
					$i++;
				}
			}
		}
		
		$stalker = get_name_if_authed();
		$stalker_user = $stalker ? new User($stalker) : null;
		
		// Add extra metadata and format them
		for ($i = 0; $i < sizeof($comments); $i++) {
			$user = new User($comments[$i]->author);
			
			// If not blocked, display the comment normally.
			if (!($stalker && user_block_has($stalker, $user->name))) {
				$comments[$i]->display = $user->get_display();
				$comments[$i]->image = $user->get_image();
				$comments[$i]->body = $comments[$i]->render_body();
				$comments[$i]->actions = ["reply"];
				$comments[$i]->badge = get_user_badge($user);
				$comments[$i]->pronouns = $user->pronouns;
			}
			// If blocked, give a fake comment.
			else {
				$comments[$i]->author = "???";
				$comments[$i]->display = "Blocked user";
				$comments[$i]->image = "./?a=generate-logo-coloured&seed=$i";
				$comments[$i]->body = "<i>[You blocked the user who wrote this comment or the user who wrote this comment blocked you, so it can't be displayed.]</i>";
				$comments[$i]->actions = [];
				$comments[$i]->badge = "";
				$comments[$i]->pronouns = "";
			}
			
			if ($stalker && $stalker_user->is_mod() || (get_name_if_authed() == $user->name)) {
				$comments[$i]->actions[] = "hide";
			}
		}
		
		return $comments;
	}
	
	function render_edit(int $index, string $url = "") {
		/**
		 * Display the comment edit box.
		 */
		
		$base = "";
		
		$enabled = get_config("enable_discussions", "enabled");
		
		if ($enabled == "enabled" && $this->is_locked()) {
			$enabled = "closed";
		}
		
		switch ($enabled) {
			case "enabled": {
				if (!get_name_if_authed()) {
					$base .= "<div class=\"comment-card comment-edit\">";
					$base .= "<p style=\"text-align: center\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px; font-size: 128px;\">forum</span></p>";
					$base .= "<p style=\"text-align: center\">Want to leave a comment? <a href=\"./?a=login\">Log in</a> or <a href=\"./?a=register\">create an account</a> to share your thoughts!</p>";
					$base .= "</div>";
					return;
				}
				
				$comment = new Comment();
				
				if ($index >= 0) {
					$comment = $this->comments[$index];
				}
				
				$name = get_name_if_authed();
				$url = htmlspecialchars($_SERVER['REQUEST_URI']); // Yes this should be sanitised for mod pages
				$body = htmlspecialchars($comment->body);
				$img = get_profile_image(get_name_if_authed());
				$sak = user_get_sak();
				
				if (!$img) {
					$img = "./icon.png";
				}
				
				$base .= "<li class=\"list-group-item\"><div id=\"discussion-$this->id-box\" class=\"comment-edit\"><div class=\"comment-card-inner\"><div class=\"comment-card-inner-left\"><img src=\"$img\"/></div><div class=\"comment-card-inner-right\"><div><p>@$name</p><p><textarea id=\"discussions-$this->id-entry\" class=\"form-control\" style=\"height: 150px; font-family: monospace;\" name=\"body\" placeholder=\"What would you like to say? (supports markdown)\">$body</textarea></p><p><input type=\"hidden\" name=\"key\" value=\"$sak\">";
				
				$base .= "<p class=\"small-text\">Please make sure to follow our <a href=\"./?n=community-guidelines\">Community Guidelines</a>.</p><p><button class=\"btn btn-primary button\" onclick=\"ds_update();\">Post comment</button></p>";
				
				$base .= "<span id=\"discussions-$this->id-error\"></span></p></div></div></div></div></li>";
				break;
			}
			case "closed": {
				$base .= "<div class=\"comment-card comment-edit\">";
				$base .= "<p style=\"text-align: center\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px; font-size: 128px;\">nights_stay</span></p>";
				$base .= "<p style=\"text-align: center\">This discussion has been closed. You can still chat on our Discord server!</p>";
				$base .= "</div>";
				break;
			}
			// If they are fully disabled there should be a message about it.
			default: {
				break;
			}
		}
		
		return $base;
	}
	
	function render_reload() {
		$base = "";
		
		if (get_name_if_mod_authed()) {
			$base .= " <button class=\"btn btn-outline-primary\" onclick=\"ds_toggle_hidden();\">Hidden</button>";
		}
		
		return $base;
	}
	
	function render_follow() {
		$name = get_name_if_authed();
		
		if ($name) {
			$following = $this->is_following($name);
			
			$follow = ($following) ? "Unfollow" : "Follow";
			$secondary = ($following) ? "btn-outline-primary" : "btn-primary";
			$url = $_SERVER['REQUEST_URI'];
			
			return "<a href=\"./?a=discussion_follow&id=$this->id&after=$url\"><button class=\"btn $secondary\">$follow</button></a>";
		}
		
		return "";
	}
	
	function render_lock() {
		$name = get_name_if_mod_authed();
		
		if ($name) {
			$locked = $this->is_locked();
			
			$text = ($locked) ? "Unlock" : "Lock";
			$url = $_SERVER['REQUEST_URI'];
			
			return "<a href=\"./?a=discussion_lock&id=$this->id&after=$url\"><button class=\"btn btn-outline-primary\">$text</button></a>";
		}
		
		return "";
	}
	
	function render_comments(bool $reverse = false) {
		return "<div id=\"discussion-$this->id\"></div><script>ds_clear(); ds_load();</script>";
	}
	
	function is_disabled() : bool {
		return (get_config("enable_discussions", "enabled") === "disabled");
	}
	
	function render_disabled() : bool {
		$disabled = $this->is_disabled();
		
		if ($disabled) {
			return "<div class=\"comment-card comment-edit\"><p>Discussions have been disabled sitewide. Existing comments are not shown, but will return when discussions are enabled again.</p></div>";
		}
		
		return "";
	}
	
	function comments_load_script(bool $backwards = false) {
		$sak = user_get_sak();
		return "<script>var DiscussionID = \"$this->id\"; var UserSAK = \"$sak\"; var DiscussionBackwards = " . ($backwards ? "true" : "false") . "; var ShowHidden = false;</script>" . file_get_contents("../../data/_discussionload.html");
	}
	
	function render_start(string $title) {
		$base = "<div class=\"card\"><div class=\"card-header d-flex justify-content-between align-items-center\"><span><b>$title</b> " . $this->enumerate_shown() . "</span>";
		
		$base .= "<span>";
		$base .= $this->render_reload();
		$base .= " ";
		$base .= $this->render_lock();
		$base .= " ";
		$base .= $this->render_follow();
		$base .= "</span>";
		
		$base .= "</div><ul id=\"shl-discussion-$this->id-item-list\" class=\"list-group list-group-flush\">";
		
		return $base;
	}
	
	function render_end() {
		return "</ul></div>";
	}
	
	function render(string $title = "Discussion", string $url = "") : string {
		$base = "";
		
		$base .= $this->comments_load_script();
		$base .= $this->render_start($title);
		if ($this->is_disabled()) {
			$base .= $this->render_disabled();
			return $base;
		}
		$base .= $this->render_comments();
		$base .= $this->render_edit(-1, $url);
		$base .= $this->render_end();
		
		return $base;
	}
	
	function render_reverse(string $title = "Discussion", string $url = "") : string {
		$base = "";
		
		$base .= $this->comments_load_script(true);
		$base .= $this->render_start($title);
		if ($this->is_disabled()) {
			$base .= $this->render_disabled();
			return $base;
		}
		$base .= $this->render_edit(-1, $url);
		$base .= $this->render_comments(true);
		$base .= $this->render_end();
		
		return $base;
	}
	
	function display(string $title = "Discussion", string $url = "") : void {
		echo $this->render($title, $url);
	}
	
	function display_reverse(string $title = "Discussion", string $url = "") : void {
		echo $this->render_reverse($title, $url);
	}
}

function discussion_exists(string $name) {
	$db = new Database("discussion");
	return $db->has($name);
}

function discussion_delete_given_id(string $id) {
	$d = new Discussion($id);
	$d->delete();
}

function discussion_nuke_comments_by(string $author) {
	/**
	 * Nuke every comment by a user in every discussion.
	 */
	
	$db = new Database("discussion");
	$entries = $db->enumerate();
	
	for ($i = 0; $i < sizeof($entries); $i++) {
		$d = new Discussion($entries[$i]);
		$d->delete_comments_by($author);
	}
}

function discussion_update() {
	if (array_key_exists("api", $_GET)) {
		return discussion_update_new();
	}
	else {
		sorry("Using this endpoint in a non-api mode has been removed.");
	}
}

function discussion_update_new() {
	$user = get_name_if_authed();
	
	// Send mimetype (we need to anyways)
	header('Content-type: application/json');
	
	if (!$user || !array_key_exists("key", $_GET) || !user_verify_sak($_GET["key"])) {
		echo "{\"error\": \"not_authed\", \"message\": \"You need to log in first.\"}"; return;
	}
	
	if (get_config("enable_discussions", "enabled") !== "enabled") {
		echo "{\"error\": \"discussions_disabled\", \"message\": \"Discussions are currently inactive sitewide.\"}"; return;
	}
	
	$user = new User($user);
	
	if (!array_key_exists("id", $_GET)) {
		echo "{\"error\": \"api\", \"message\": \"API: Missing 'id' feild.\"}"; return;
	}
	
	$discussion = $_GET["id"];
	
	if (!array_key_exists("index", $_GET)) {
		echo "{\"error\": \"api\", \"message\": \"API: Missing 'index' feild.\"}"; return;
	}
	
	$index = $_GET["index"]; // If it's -1 then it's a new comment
	
	$body = file_get_contents("php://input");
	
	if (strlen($body) < 1) {
		echo "{\"error\": \"no_content\", \"message\": \"This comment does not have any content.\"}"; return;
	}
	
	if (strlen($body) > 3500) {
		echo "{\"error\": \"too_long\", \"message\": \"Your comment is too long! Please make sure your comment is less than 3500 characters.\"}"; return;
	}
	
	$discussion = new Discussion($discussion);
	
	// Comment limit
	if ($discussion->count_all() >= 800) {
		echo "{\"error\": \"comment_limit_reached\", \"message\": \"This discussion has too many comments. You cannot post another one.\"}"; return;
	}
	
	if ($index == "-1") {
		$status = $discussion->add_comment($user->name, $body);
		
		if ($status) {
			echo "{\"error\": \"done\", \"message\": \"Your comment was posted successfully!\"}";
			
			// If we reach the comment limit, automatically lock the discussion.
			if ($discussion->count_all() >= 800 && !$discussion->is_locked()) {
				$discussion->toggle_locked();
			}
		}
		else {
			echo "{\"error\": \"not_posted\", \"message\": \"This comment could not be posted. This might happen if you don't have access to the discussion.\"}";
		}
	}
	else {
		echo "{\"error\": \"not_supported\", \"message\": \"Updating existing comments is not supported.\"}";
	}
}

function discussion_hide() {
	$user = get_name_if_authed();
	
	if (!$user) {
		sorry("You need to log in to hide a comment.");
	}
	
	if (get_config("enable_discussions", "enabled") === "disabled") {
		sorry("Updating discussions has been disabled.");
	}
	
	$user = new User($user);
	
	if (!array_key_exists("id", $_GET)) {
		sorry("Need an id to update.");
	}
	
	$discussion = $_GET["id"];
	
	if (!array_key_exists("index", $_GET)) {
		sorry("Need an index to update.");
	}
	
	$index = $_GET["index"];
	
	if (!array_key_exists("key", $_GET)) {
		sorry("Need an index to update.");
	}
	
	$sak = $_GET["key"];
	
	$discussion = new Discussion($discussion);
	
	// If the user requesting is not the author and is not mod, we deny the
	// request.
	if (($discussion->get_author($index) !== $user->name && !$user->is_mod()) || (!$user->verify_sak($sak))) {
		sorry("You cannot hide a comment which you have not written.");
	}
	
	$discussion->hide_comment($index);
	
	if (array_key_exists("after", $_GET)) {
		redirect($_GET["after"]);
	}
	else {
		sorry("It's done but no clue what page you were on...");
	}
}

function discussion_follow() {
	$user = get_name_if_authed();
	
	if (!$user) {
		sorry("You need to be logged in to follow discussions.");
	}
	
	if (get_config("enable_discussions", "enabled") !== "enabled") {
		sorry("There is no reason to follow a discussion which has been closed.");
	}
	
	$user = new User($user);
	
	if (!array_key_exists("id", $_GET)) {
		sorry("Need an id to follow.");
	}
	
	$discussion = $_GET["id"];
	$discussion = new Discussion($discussion);
	$discussion->toggle_follow($user->name);
	
	if (array_key_exists("after", $_GET)) {
		redirect($_GET["after"]);
	}
	else {
		sorry("It's done but no clue what page you were on...");
	}
}

function discussion_poll() {
	if (!array_key_exists("id", $_GET) || !array_key_exists("index", $_GET)) {
		sorry("Problem doing that.");
	}
	
	// Send mimetype
	header('Content-type: application/json');
	
	$user = get_name_if_authed();
	
	// List the comments
	$disc = new Discussion($_GET["id"]);
	$comments = $disc->list_since($_GET["index"], get_name_if_mod_authed() && array_key_exists("hidden", $_GET));
	
	// Create the result data
	$result = new stdClass;
	$result->status = "done";
	$result->message = "Loaded discussions successfully!";
	$result->anything = (sizeof($comments) !== 0);
	$result->comments = $comments;
	$result->actor = $user;
	$result->next_sak = user_get_sak();
	
	// Send json data
	echo json_encode($result);
}

function discussion_lock() {
	$user = get_name_if_mod_authed();
	
	if (!$user) {
		sorry("The action you have requested is not currently implemented.");
	}
	
	$user = new User($user);
	
	if (!array_key_exists("id", $_GET)) {
		sorry("Need an id to lock.");
	}
	
	$discussion = $_GET["id"];
	$discussion = new Discussion($discussion);
	$discussion->toggle_locked();
	
	alert("Discussion ID $discussion->id " . ($discussion->is_locked() ? "locked" : "unlocked") . " by @$user->name", $discussion->get_url());
	
	if (array_key_exists("after", $_GET)) {
		redirect($_GET["after"]);
	}
	else {
		sorry("It's done but no clue what page you were on...");
	}
}

function discussion_view() {
	if (get_config("enable_discussions", "enabled") !== "enabled") {
		sorry("Can't do that right now.");
	}
	
	$discussion = $_GET["id"];
	$discussion = new Discussion($discussion);
	
	$real_id = $discussion->get_id();
	
	if ($real_id === null) {
		sorry("Discussion empty!");
	}
	
	include_header();
	
	echo "<h1>Viewing #$real_id</h1>";
	
	$discussion->display();
	
	include_footer();
}

$gEndMan->add("discussion-hide", function (Page $page) {
	$page->set_mode(PAGE_MODE_API);
	
	$user = user_get_current();
	
	if (!$user) {
		$page->info("not_authed", "Please log in first!");
	}
	
	$id = $page->get("id");
	$index = $page->get("index");
	$sak = $page->get("sak");
	
	$d = new Discussion($id);
	
	if (($d->get_author($index) !== $user->name && !$user->is_mod()) || (!$user->verify_sak($sak))) {
		$page->info("not_permitted", "You cannot hide a comment which you have not written.");
	}
	
	$d->hide_comment($index);
	
	$page->set("status", "done");
	$page->set("message", "The comment has been hidden.");
	$page->set("next_sak", $user->get_sak());
});

$gEvents->add("user.delete", function (User $user) {
	// Wipe discussions
	discussion_nuke_comments_by($user->name);
});
