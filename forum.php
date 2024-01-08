<?php

define("MAX_THREADS", 500);

class ForumThread {
	/**
	 * A bit of info about a forum thread. This is basically just the ID, date
	 * and author.
	 */
	
	public $id;
	public $title;
	public $created;
	public $author;
	
	function __construct(string $id) {
		$db = new Database("thread");
		
		if ($db->has($id)) {
			$info = $db->load($id);
			
			$this->id = $info->id;
			$this->title = $info->title;
			$this->created = $info->created;
			$this->author = $info->author;
		}
		else {
			$this->id = $id;
			$this->title = "Untitled";
			$this->created = time();
			$this->author = "";
		}
	}
	
	function save() {
		$db = new Database("thread");
		$db->save($this->id, $this);
	}
	
	function exists() {
		$db = new Database("thread");
		return $db->has($this->id);
	}
	
	function delete() {
		$disc = new Discussion($this->id);
		$disc->delete();
		
		$db = new Database("thread");
		$db->delete($this->id);
	}
}

$gEndMan->add("forum-home", function (Page $page) {
	$actor = user_get_current();
	
	$page->force_bs();
	$page->heading(1, "Forum");
	
	if ($actor) {
		$page->add("<p><button class=\"btn btn-primary\" onclick=\"shl_show_dialogue('new-thread')\">Create thread</button></p>");
		
		$page->add(create_form_dialogue_code('new-thread', "./?a=forum-create&submit=1", "Create a new thread", "<p><input class=\"form-control\" type=\"text\" name=\"title\" placeholder=\"Title\"/></p>
		<p><textarea class=\"form-control\" style=\"height: 200px; font-family: monospace;\" name=\"content\" placeholder=\"Type your message (supports markdown)...\"></textarea></p>", "<button>Create thread</button>", '25em'));
	}
	
	$recent = get_config("forum_recent", []);
	
	for ($i = 0; $i < sizeof($recent); $i++) {
		$thread = new ForumThread($recent[$i]);
		
		if ($thread->exists()) {
			$user = new User($thread->author);
			
			$page->add("<div class=\"card thread-card\" style=\"margin-bottom: 15px;\"><div class=\"card-body\">
	<div style=\"display: grid; grid-template-columns: 80px auto;\">
		<div style=\"grid-column: 1;\">
			<img src=\"$user->image\" style=\"width: 80px; height: 80px; border-radius: 40px;\"/>
		</div>
		<div style=\"grid-column: 2; margin-left: 1em;\">
			<h4><a href=\"./?a=forum-view&thread=$thread->id\">$thread->title</a></h4>
			<p><a href=\"./?u=$user->name\">$user->display</a> (@$user->name) · " . date("Y-m-d H:i:s", $thread->created) . "</p>
		</div>
	</div>
</div></div>");
		}
	}
});

$gEndMan->add("forum-create", function (Page $page) {
	$actor = user_get_current();
	
	$page->force_bs();
	
	if ($actor) {
		if (!$page->has("submit")) {
			$page->info("Error", "There is no form for this action. Use the new thread dailogue on the main page.");
		}
		else {
			// Forum threads use the same id's as their discussions
			$thread = new ForumThread(random_discussion_name());
			
			// Get content
			$content = $page->get("content", true, 3500);
			
			// Set properties
			$thread->title = $page->get("title", true, 120);
			$thread->author = $actor->name;
			
			// Create discussion
			$disc = new Discussion($thread->id);
			$disc->set_url("./?a=forum-view&thread=$thread->id");
			$disc->add_comment($thread->author, $content);
			
			// Save thread info and discussion
			$thread->save();
			
			// Add to list of recent threads
			// We record up to 20 recent threads, the others go unlisted
			$recent = get_config("forum_recent", []);
			$recent = array_merge([$thread->id, ], array_slice($recent, 0, MAX_THREADS - 1));
			set_config("forum_recent", $recent);
			
			// Redirect to thread
			$page->redirect("./?a=forum-view&thread=$thread->id");
		}
	}
	else {
		$page->info("Not logged in", "Please log in or sign up to create a new thread.");
	}
});

$gEndMan->add("forum-view", function (Page $page) {
	$page->force_bs();
	$actor = user_get_current();
	
	// Get ID
	$id = $page->get("thread");
	
	$thread = new ForumThread($id);
	
	if (!$thread->exists()) {
		$page->info("No such thread", "The thread you wanted does not seem to exist. Maybe it's been deleted?");
	}
	
	// Display title
	$page->add("<h1>$thread->title</h1>");
	
	// Moderation actions
	if ($actor && $actor->is_mod()) {
	    $page->add("<p><a href=\"./?a=forum-rename&thread=$thread->id\"><button class=\"button secondary\">Rename</button></a> <a href=\"./?a=forum-delete&thread=$thread->id\"><button class=\"button secondary\">Delete</button></a></p>");
	}
	
	// Display the full discussion
	$disc = new Discussion($id);
	$page->add($disc->render());
});

$gEndMan->add("forum-delete", function (Page $page) {
	$actor = user_get_current();
	
	if ($actor && $actor->is_mod()) {
		if (!$page->has("submit")) {
			$page->heading(1, "Delete thread");
			
			// Main deletion form
			$form = new Form("./?a=forum-delete&submit=1");
			$form->textbox("thread", "Thread ID", "The ID of the thread to delete.", $page->get("thread", false), !$page->has("thread"));
			$form->textbox("reason", "Reason", "Reason for deletion of the thread.");
			$form->submit("Delete thread");
			
			$page->add($form);
		}
		else {
			$thread = new ForumThread($page->get("thread"));
			
			if (!$thread->exists()) {
				$page->info("Invalid thread", "That thread does not exist and cannot be deleted.");
			}
			
			$thread->delete();
			
			alert("Moderator @$actor->name deleted thread and discussion $thread->id\n\nReason: " . $page->get("reason", false));
			
			$page->redirect("./?a=forum-home");
		}
	}
	else {
		$page->info("Sorry", "You need to log in to preform this action.");
	}
});

$gEndMan->add("forum-rename", function (Page $page) {
	$actor = user_get_current();
	
	if ($actor && $actor->is_mod()) {
		if (!$page->has("submit")) {
			$page->heading(1, "Rename thread");
			
			// Main deletion form
			$form = new Form("./?a=forum-rename&submit=1");
			$form->textbox("thread", "Thread ID", "The ID of the thread to reame.", $page->get("thread", false), !$page->has("thread"));
			$form->textbox("name", "Name", "New name of the thread.");
			$form->submit("Rename thread");
			
			$page->add($form);
		}
		else {
			$thread = new ForumThread($page->get("thread"));
			
			if (!$thread->exists()) {
				$page->info("Invalid thread", "That thread does not exist and cannot be renamed.");
			}
			
			$thread->title = $page->get("name");
			$thread->save();
			
			alert("Moderator @$actor->name renamed thread $thread->id to $thread->title" . $page->get("reason", false));
			
			$page->redirect("./?a=forum-view&thread=$thread->id");
		}
	}
	else {
		$page->info("Sorry", "You need to log in to preform this action.");
	}
});
