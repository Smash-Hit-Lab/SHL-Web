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
	public $creator;
	public $created;
	public $title;
	public $url;
	public $desc;
	
	function __construct(?string $id) {
		$db = new Database("contest_submissions");
		
		if ($id && $db->has($id)) {
			copy_object_vars($this, $db->load($id));
		}
		else {
			$this->id = random_base32(48);
			$this->anon = true;
			$this->creator = "";
			$this->created = time();
			$this->title = "";
			$this->url = "";
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
			$page->force_bs();
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
			$contest->title = $page->get("title", true, 200);
			$contest->due = strtotime($page->get("due"));
			
			$contest->save();
			
			alert("Contest manager @$user->name created a new contest with id $contest->id", "./?a=contest-view&id=$contest->id");
			
			$page->redirect("./?a=contest-view&id=$contest->id");
		}
	}
	else {
		$page->info("Error", "You don't have permissions to create contests.");
	}
});

$gEndMan->add("contest-view", function (Page $page) {
	$user = user_get_current();
	
	$contest_id = $page->get("id");
	
	if (!contest_exists($contest_id)) {
		$page->info("Sorry!", "There is no contest by that ID.");
	}	
	
	$contest = new Contest($contest_id);
	
	$page->force_bs();
	$page->title($contest->title);
	
	// Contest title and basic stats
	$page->heading(1, $contest->title);
	$page->para("<b>Created on:</b> " . date("Y-m-d H:i:s", $contest->created));
	$page->para("<b>Ends on:</b> " . date("Y-m-d H:i:s", $contest->due));
	$page->para("<b>Hosted by:</b> @$contest->creator");
	
	// Submissions section
	$page->heading(3, "Submissions");
	
	if ($contest->due > time()) {
		$page->link_button("", "Create a submission", "./?a=contest-submit&id=$contest_id", true);
	}
	
	if (sizeof($contest->submissions) == 0) {
		$page->para("<i>There are no submissions to list!</i>");
	}
	else {
		for ($i = sizeof($contest->submissions) - 1; $i >= 0; $i--) {
			$submission = new ContestSubmission($contest->submissions[$i]);
			
			$creator_text = $submission->anon ? "<i>$submission->creator (anon)</i>" : "<a href=\"./@$submission->creator\">@$submission->creator</a>";
			$time_text = date("Y-m-d H:i", $submission->created);
			$desc_text = str_replace("\n", "<br/>", $submission->desc);
			
			$page->add("
			<div id=\"sub-$submission->id\" class=\"card\" style=\"margin: 12px 0 12px 0;\">
				<div class=\"card-header\"><b>$submission->title</b> by $creator_text</div>
				<div class=\"card-body\">
					<p class=\"card-text\"><b>Submitted on:</b> $time_text</p>
					<p class=\"card-text\"><b>URL:</b> <a href=\"$submission->url\">$submission->url</a></p>
					<p class=\"card-text\"><b>Description:</b><br/>$desc_text</p>
				</div>
				<div class=\"card-footer text-body-secondary\">Submission ID: <a class=\"text-body-secondary\" href=\"#sub-$submission->id\">$submission->id</a></div>
			</div>");
		}
	}
});

$gEndMan->add("contest-submit", function (Page $page) {
	$user = user_get_current();
	
	$contest_id = $page->get("id");
	
	if (!contest_exists($contest_id)) {
		$page->info("Sorry!", "There is no contest by that ID.");
	}
	
	$contest = new Contest($contest_id);
	
	if ($contest->due <= time()) {
		$page->info("Sorry!", "This contest has closed and is no longer accepting submissions.");
	}
	
	if (!$page->has("submit")) {
		$page->force_bs();
		$page->title("Submit to $contest->title");
		$page->heading(1, "Submit to $contest->title");
		
		$form = new Form("./?a=contest-submit&id=$contest_id&submit=1");
		
		$form->textbox("title", "Title", "This is the title that you would like this submission to be known by.");
		
		if (!$user) {
			$form->textbox("creator", "Creator", "Enter the name you want to be credited as. If you want to be credited with your SHL account, please <a href=\"./?a=auth-login\">log in first</a>.");
		}
		
		$form->textbox("url", "URL", "The link to download your submission or a showcase of it, depending on the contest rules.");
		$form->textaera("desc", "Description", "An optional short description of your submission.");
		
		$form->submit("Submit");
		
		$page->add($form);
	}
	else {
		$submission = new ContestSubmission(null);
		
		if ($user) {
			$submission->anon = false;
			$submission->creator = $user->name;
		}
		else {
			$submission->creator = $page->get("creator", true, 100);
		}
		
		$submission->title = $page->get("title", true, 100);
		$submission->url = $page->get("url", true, 3000);
		$submission->desc = $page->get("desc", false, 5000);
		
		if (!$submission->desc) { $submission->desc = ""; }
		
		$submission->save();
		
		// Save the contest info as well
		$contest->add_submission($submission->id);
		$contest->save();
		
		alert("New submission in contest $contest->title ($contest->id) with title $submission->title", "./?a=contest-view&id=$contest->id");
		
		// Redirect back to contest view page
		$page->redirect("./?a=contest-view&id=$contest->id");
	}
});
