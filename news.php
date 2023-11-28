<?php

function push_recent(string $name) {
	/**
	 * Add an article to the list of recently updated artcles.
	 */
	
	$db = new Database("news");
	
	if ($db->has("recent")) {
		$info = $db->load("recent");
		
		if (sizeof($info) > 10) {
			array_splice($info, 0, 1);
		}
		
		$info[] = $name;
		$db->save("recent", $info);
	}
	else {
		$db->save("recent", array($name));
	}
}

function get_recent() {
	/**
	 * Get recently updated articles.
	 */
	
	$db = new Database("news");
	
	if ($db->has("recent")) {
		return $db->load("recent");
	}
	else {
		return array();
	}
}

function get_news_edit_button(string $name) : string {
	$user = get_name_if_authed();
	
	if ($user) {
		$user = new User($user);
		
		if ($user->is_admin()) {
			return "<p class=\"centred\"><a href=\"./?a=update_news&n=$name\"><button><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">edit</span> Edit article</button></a> <a href=\"./?a=news-history&n=$name\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">history</span> History</button></a></p>";
		}
	}
	
	return "";
}

function show_news_edit_button(string $name) : void {
	echo get_news_edit_button($name);
}

class Article {
	public $name;
	public $title;
	public $body;
	public $created;
	public $updated;
	public $author;
	public $authors;
	public $permissions;
	public $comments;
	
	function __construct(string $name, int $revision = -1) {
		$db = new RevisionDB("article");
		
		if ($db->has($name)) {
			$info = $db->load($name, $revision);
			
			$this->name = $info->name;
			$this->title = $info->title;
			$this->body = $info->body;
			$this->created = $info->created;
			$this->updated = $info->updated;
			$this->authors = $info->authors;
			$this->permissions = property_exists($info, "permissions") ? $info->permissions : "private";
			$this->comments = property_exists($info, "comments") ? $info->comments : random_discussion_name();
			
			// If there weren't discussions before, save them now.
			if (!property_exists($info, "comments")) {
				$this->save();
			}
			
			$disc = new Discussion($this->comments);
			$disc->set_url("./?n=$this->name");
		}
		else {
			$this->name = $name;
			$this->title = "Untitled article";
			$this->body = "";
			$this->created = time();
			$this->updated = time();
			$this->authors = array();
			$this->permissions = "private";
			$this->comments = random_discussion_name();
		}
	}
	
	function save() {
		$db = new RevisionDB("article");
		
		$db->save($this->name, $this);
	}
	
	function get_html() {
		/**
		 * Get a news article as HTML.
		 */
		
		// Legacy formatting
		//return rich_format($this->body, true);
		
		// Parsedown
		$p = new Parsedown();
		return $p->text($this->body);
	}
	
	function update(string $title, string $content, string $whom = "") {
		$this->title = htmlspecialchars($title);
		$this->body = $content;
		$this->updated = time();
		
		// Add to the authors list if not already in it.
		if ($whom && !in_array($whom, $this->authors)) {
			$this->authors[] = $whom;
		}
		
		// Set author for revision
		$this->author = $whom;
		
		push_recent($this->name);
		
		//$this->save();
	}
	
	function set_permissions(string $name) {
		$this->permissions = $name;
		//$this->save();
	}
	
	function display_history() {
		echo "<h1>History of " . ($this->title ? $this->title : $this->name) . "</h1>";
		
		$db = new RevisionDB("article");
		$history = $db->history($this->name);
		
		echo "<ul>";
		
		for ($i = (sizeof($history) - 1); $i >= 0; $i--) {
			$rev = $history[$i];
			
			echo "<li><a href=\"./?n=$this->name&index=$i\">Edit at " . date("Y-m-d H:i:s", $rev->updated) . "</a> by <a href=\"./?u=$rev->author\">$rev->author</a> &mdash; $rev->reason</li>";
		}
		
		echo "</ul>";
	}
	
	function display_update() {
		require("../../data/_news_editor.html");
	}
	
	function display() {
		/**
		 * Echo out a news article.
		 */
		
		$has_sidebar = ($this->name != "sidebar" && $this->name != "home");
		
		if ($has_sidebar) {
			echo "<div class=\"article-page-body\">";
			echo "<div class=\"article-page-body-main\">";
		}
		
		echo "<h1>$this->title</h1>";
		
		// Make icons
		$icon_date = "<span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">today</span>";
		$icon_people = "<span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">supervisor_account</span>";
		
		// Make editor list
		$editors = "";
		
		for ($i = 0; $i < sizeof($this->authors); $i++) {
			$editors .= get_nice_display_name($this->authors[$i]);
			
			// Nice ands and commas
			if (($i + 2) == sizeof($this->authors)) {
				$editors .= " and ";
			}
			else if (($i + 1) != sizeof($this->authors)) {
				$editors .= ", ";
			}
		}
		
		// Display article creation date
		echo "<p style=\"text-align: center; opacity: 0.7;\">$icon_date Created " . date("Y-m-d", $this->created) . (($this->created != $this->updated) ? (" (edited " . date("Y-m-d", $this->updated) . ")") : ("")) . "<span style=\"padding-left: 1em\"/>$icon_people Edited by $editors</p>";
		
		// Edit button for article
		show_news_edit_button($this->name);
		
		echo "<div class=\"article-body\">";
		echo $this->get_html();
		echo "</div>";
		
		// Divider for comments
		echo "<div style=\"border-bottom: 1px solid var(--colour-primary-b); margin-bottom: 1em;\"></div>";
		
		// Display comments
		$disc = new Discussion($this->comments);
		$disc->display("Comments", "./?n=" . $this->name);
		
		// Has sidebar ?
		if ($has_sidebar) {
			echo "</div>";
			echo "<div class=\"article-page-body-sidebar\">";
			echo (new Article("sidebar"))->get_html();
			echo "</div>";
			echo "</div>";
		}
	}
	
	function render() {
		/**
		 * Renders a news article's HTML.
		 */
		
		$base = "";
		$has_sidebar = ($this->name != "sidebar" && $this->name != "home");
		
		if ($has_sidebar) {
			$base .= "<div class=\"article-page-body\">";
			$base .= "<div class=\"article-page-body-main\">";
		}
		
		$base .= "<h1>$this->title</h1>";
		
		// Make icons
		$icon_date = "<span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">today</span>";
		$icon_people = "<span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">supervisor_account</span>";
		
		// Make editor list
		$editors = "";
		
		for ($i = 0; $i < sizeof($this->authors); $i++) {
			$editors .= get_nice_display_name($this->authors[$i]);
			
			// Nice ands and commas
			if (($i + 2) == sizeof($this->authors)) {
				$editors .= " and ";
			}
			else if (($i + 1) != sizeof($this->authors)) {
				$editors .= ", ";
			}
		}
		
		// Display article creation date
		$base .= "<p style=\"text-align: center; opacity: 0.7;\">$icon_date Created " . date("Y-m-d", $this->created) . (($this->created != $this->updated) ? (" (edited " . date("Y-m-d", $this->updated) . ")") : ("")) . "<span style=\"padding-left: 1em\"/>$icon_people Edited by $editors</p>";
		
		// Edit button for article
		//show_news_edit_button($this->name);
		
		$base .= "<div class=\"article-body\">";
		$base .= $this->get_html();
		$base .= "</div>";
		
		// Divider for comments
		$base .= "<div style=\"border-bottom: 1px solid var(--colour-primary-b); margin-bottom: 1em;\"></div>";
		
		// Display comments
		//$disc = new Discussion($this->comments);
		//$disc->display("Comments", "./?n=" . $this->name);
		
		// Has sidebar ?
		if ($has_sidebar) {
			$base .= "</div>";
			$base .= "<div class=\"article-page-body-sidebar\">";
			$base .= (new Article("sidebar"))->get_html();
			$base .= "</div>";
			$base .= "</div>";
		}
		
		return $base;
	}
}

function article_exists(string $name) : bool {
	/**
	 * Check if a news article exsists.
	 */
	
	$db = new RevisionDB("article");
	return $db->has($name);
}

function display_news(string $name) : void {
	/**
	 * Display the specified news article.
	 */
	
	if (!article_exists($name)) {
		sorry("It seems like we don't have a news article by that name.", get_news_edit_button($name));
	}
	
	$index = -1;
	
	if (get_name_if_admin_authed() && array_key_exists("index", $_GET)) {
		$index = (int) $_GET["index"];
	}
	
	$article = new Article($name, $index);
	
	// HACK for article titles
	global $gTitle; $gTitle = $article->title;
	
	include_header();
	
	if (($article->permissions == "public") || (get_name_if_admin_authed() != null)) {
		$article->display();
	}
	else {
		sorry("It seems like we don't have a news article by that name.", get_news_edit_button($name));
	}
	
	include_footer();
}

$gEndMan->add("news-history", function (Page $page) {
	if (get_name_if_admin_authed()) {
		$page->set_mode(PAGE_MODE_RAW);
		
		include_header();
		$article = new Article($_GET["n"]);
		$article->display_history();
		include_footer();
	}
	else {
		$page->info("Sorry", "You cannot preform this action.");
	}
});

$gEndMan->add("news-view", function (Page $page) {
	$id = $page->get("id");
	
	if (!article_exists($id)) {
		$page->info("Whoops!", "It seems like we don't have a news article by that name. Maybe check to make sure you've spelled everything alright?");
	}
	
	$article = new Article($id);
	
	// HACK for article titles
	global $gTitle; $gTitle = $article->title;
	
	if (($article->permissions == "public") || (get_name_if_admin_authed() != null)) {
		$page->add($article->render());
	}
});

function pretend_error() : void {
	include_header();
	echo "<h1>Sorry</h1><p>The action you have requested is not currently implemented.</p>";
	include_footer();
}

function update_news() : void {
	$user = get_name_if_admin_authed();
	
	if (!$user || !array_key_exists("n", $_GET)) {
		pretend_error();
		return;
	}
	
	// We are allowed to update the article ...
	$article = new Article($_GET["n"]);
	
	include_header();
	$article->display_update();
	include_footer();
}

function save_news() : void {
	$user = get_name_if_admin_authed();
	
	if (!$user || !array_key_exists("n", $_GET)) {
		pretend_error();
		return;
	}
	
	// We are allowed to update the article ...
	$article = new Article($_GET["n"]);
	$article->update($_POST["title"], $_POST["body"], $user);
	$article->set_permissions($_POST["permissions"]);
	$article->save();
	
	alert("Article \"$article->title\" ($article->name) has been updated by @$user", "./?n=$article->name");
	
	redirect("./?n=$article->name");
}
