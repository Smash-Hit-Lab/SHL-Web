<?php

class Contest {
	public $id;
	public $creator;
	public $created;
	public $title;
	public $due;
	public $submissions;
	
	function __construct(?string $id) {
		$db = new Database("contests");
		
		if ($id && $db->has($id)) {
			copy_object_vars($this, $db->load($id));
		}
		else {
			$this->id = random_base32(32);
			$this->creator = "";
			$this->created = time();
			$this->title = "";
			$this->due = time();
			$this->submissions = [];
		}
	}
	
	function save() {
		$db = new Database("contests");
		$db->save($this->id, $this);
	}
	
	function add_submission(string $id) {
		$this->submissions[] = $id;
	}
}

function contest_exists(string $id) {
	$db = new Database("contests");
	return $db->has($id);
}

class ContestSubmission {
	public $id;
	public $anon;
	public $author;
	public $created;
	public $download;
	public $desc;
	
	function __construct(?string $id) {
		$db = new Database("contest_submissions");
		
		if ($id && $db->has($id)) {
			copy_object_vars($this, $db->load($id));
		}
		else {
			$this->id = random_base32(32);
			$this->anon = true;
			$this->author = "";
			$this->created = time();
			$this->download = "";
			$this->desc = "";
		}
	}
	
	function save() {
		$db = new Database("contest_submissions");
		$db->save($this->id, $this);
	}
}

$gEndMan->add("contest-create", function (Page $page) {
	$user = user_get_current();
	
	if ($user && $user->is_admin() && $user->has_role("contest_manager")) {
		if (!$page->has("submit")) {
			$page->title("Create a new contest");
			$page->heading(1, "Create a new contest");
			
			$form = new Form("./?a=contest-create&submit=1");
			$form->textbox("title", "Title", "The name of the contest.");
			$form->textbox("due", "Due date", "The date that the contest will stop accepting submissions.");
			$form->submit("Create contest");
			
			$page->add($form);
		}
		else {
			$contest = new Contest(null);
			
			$contest->creator = $user->name;
			$contest->title = $page->get("title");
			$contest->due = strtotime($page->get("due"));
			
			$contest->save();
			
			alert("Contest manager @$user->name created a new contest with id $contest->id");
			
			$page->redirect("./?a=contest-view&id=$contest->id");
		}
	}
	else {
		$page->info("Error", "You don't have permissions to create contests.");
	}
});

$gEndMan->add("contest-view", function (Page $page) {
	// Not sure if this page should be limited to the contest author or not...
	$user = user_get_current();
	
	$contest_id = $page->get("id");
	
	if (!contest_exists($contest_id)) {
		$page->info("Sorry!", "There is no contest by that ID.");
	}
	
	$contest = new Contest($id);
	
	$page->title($contest->title);
	$page->heading(1, $contest->title);
	$page->para("<b>Created on:</b> " . date("Y-m-d H:i:s", $this->created));
	$page->para("<b>Ends on:</b> " . date("Y-m-d H:i:s", $this->due));
	$page->para("<b>Hosted by:</b> @$contest->creator");
	$page->heading(3, "Submissions");
});
