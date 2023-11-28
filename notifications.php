<?php
/**
 * User notifications
 */

class Notification {
	/**
	 * A single notification.
	 */
	
	public $title; // Title of the notification
	public $url; // Notification URL
	public $created; // Time of the notification
	
	function __construct(?string $title = null, ?string $url = null) {
		if ($title !== null && $url !== null) {
			$this->title = $title;
			$this->url = $url;
			$this->created = time();
		}
	}
	
	function set(string $title, string $url) {
		$this->title = $title;
		$this->url = $url;
		$this->created = time();
		
		return $this;
	}
	
	function load(object $info) {
		$this->title = $info->title;
		$this->url = $info->url;
		$this->created = $info->created;
		
		return $this;
	}
	
	function render() {
		$title = htmlspecialchars($this->title);
		$url = htmlspecialchars($this->url);
		$date = date("Y-m-d H:i:s", $this->created);
		
		return ($url) ? "<li>$date &mdash; <a href=\"$url\">$title</a></li>" : "<li>$date &mdash; $title</li>";
	}
}

class UserNotifications {
	/**
	 * The notifications for a single user.
	 */
	
	public $name; // Name of the user
	public $notifications; // The user's noifications
	public $db_name; // The database to use (default: notify)
	
	function __construct(string $name, string $db_name = "notify") {
		$db = new Database($db_name);
		
		if ($db->has($name)) {
			$info = $db->load($name);
			
			$this->name = $info->name;
			$this->notifications = $info->notifications;
			
			// Just as we do with user comments, we make sure these are
			// really notification objects.
			for ($i = 0; $i < sizeof($this->notifications); $i++) {
				$this->notifications[$i] = (new Notification())->load($this->notifications[$i]);
			}
		}
		else {
			$this->name = $name;
			$this->notifications = array();
		}
		
		$this->db_name = $db_name;
	}
	
	function save() {
		$db = new Database($this->db_name);
		$db->save($this->name, $this);
	}
	
	function clear() {
		$this->notifications = array();
		$this->save();
	}
	
	function notify(string $title, string $url = "") {
		$this->notifications[] = (new Notification())->set($title, $url);
		$this->save();
	}
	
	function count() {
		return sizeof($this->notifications);
	}
	
	function render() {
		$content = "";
		
		if (sizeof($this->notifications) === 0) {
			return "<p><i>No new notifications!</i></p>";
		}
		
		$content .= "<ul>";
		
		for ($i = 0; $i < sizeof($this->notifications); $i++) {
			$content .= $this->notifications[$i]->render();
		}
		
		$content .= "</ul>";
		
		return $content;
	}
	
	function display($title = "Notifications") {
		if ($title) {
			echo "<h1>$title</h1>";
		}
		
		if (sizeof($this->notifications) === 0) {
			echo "<p><i>No new notifications!</i></p>";
			return;
		}
		
		echo "<ul>";
		
		for ($i = 0; $i < sizeof($this->notifications); $i++) {
			echo $this->notifications[$i]->render();
		}
		
		echo "</ul>";
	}
}

function notify(string $user, string $title, string $url) {
	/**
	 * Add a notification to a user's inbox.
	 */
	
	$un = new UserNotifications($user);
	$un->notify($title, $url);
}

function notify_many(array $users, string $title, string $url) {
	/**
	 * Notify a list of a users
	 */
	
	for ($i = 0; $i < sizeof($users); $i++) {
		if (user_exists($users[$i])) {
			notify($users[$i], $title, $url);
		}
	}
}

function notify_scan(string $text, string $where) : void {
	/**
	 * Scan formatted text for user ats and notify those users.
	 */
	
	$filtered = htmlspecialchars($text);
	
	for ($i = 0; $i < strlen($filtered); $i++) {
		$s = substr($filtered, $i);
		
		if (str_starts_with($s, "\\")) {
			$i += 1;
		}
		else if (str_starts_with($s, "@")) {
			$end = strcspn($s, " \r\n\t", 1, 24);
			
			// If we didn't find it then just use whatever...
			if ($end < 0) {
				$end = strlen($s);
			}
			
			$handle = substr($s, 1, $end);
			
			if (user_exists($handle)) {
				// Skip the handle text
				$i += strlen($handle);
				
				if (!user_block_has($handle, get_name_if_authed())) {
					notify($handle, "You were mentioned in a post", $where);
				}
			}
		}
	}
}

$gEndMan->add("notifications", function(Page $page) {
	$user = get_name_if_authed();
	
	if (!$user) {
		$page->info("Sorry!", "Logged out users don't have notifications. Please log in or create an account to check your notifications!");
	}
	
	$un = new UserNotifications($user);
	$page->heading(1, "Notifications");
	
	$page->add("<div style=\"text-align: center;\">");
	$page->add("<a href=\"./?a=notifications-clear\"><button class=\"button\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">clear</span> Clear all</button></a>");
	if (get_name_if_admin_authed()) {
		$page->add(" <a href=\"./?a=send_notification\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">add</span> Create</button></a>");
		$page->add(" <a href=\"./?a=notifications-send-multicast\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">add</span> Multicast</button></a>");
	}
	$page->add("</div>");
	
	$page->add($un->render());
});

$gEndMan->add("notifications-clear", function(Page $page) {
	$user = get_name_if_authed();
	
	if (!$user) {
		$page->info("Sorry!", "Logged out users don't have notifications. Please log in or create an account to check your notifications!");
	}
	
	$un = new UserNotifications($user);
	$un->clear();
	
	$page->redirect("./?a=notifications");
});

function display_notification_charm(string $name) {
	/**
	 * Display the notifications text in the navbar, if there are any to display.
	 */
	
	$un = new UserNotifications($name);
	$count = $un->count();
	
	if ($count) {
		echo "<div class=\"cb-top-item\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">notifications</span><a href=\"./?a=notifications\">Notifications ($count)</a></div>";
	}
}
