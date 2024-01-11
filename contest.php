<?php

class Contest {
	public $id;
	public $creator;
	public $created;
	public $title;
	public $due;
	public $submissions;
	public $desc;
	
	function __construct(?string $id) {
		$db = new Database("contests");
		
		if ($id && $db->has($id)) {
			copy_object_vars($this, $db->load($id));
			
			if (!isset($this->desc)) {
				$this->desc = "";
			}
		}
		else {
			$this->id = random_base32(32);
			$this->creator = "";
			$this->created = time();
			$this->title = "";
			$this->due = time();
			$this->submissions = [];
			$this->desc = "";
		}
	}
	
	function save() {
		$db = new Database("contests");
		$db->save($this->id, $this);
	}
	
	function add_submission(string $id) {
		$this->submissions[] = $id;
	}
	
	function disassociate_submission(string $id) : bool {
		$index = array_search($id, $this->submissions, true);
		
		if ($index === false) {
			return false;
		}
		
		array_splice($this->submissions, $index, 1);
		
		return true;
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
	
	function delete() {
		$db = new Database("contest_submissions");
		$db->delete($this->id);
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
			$form->textaera("desc", "Description", "Optionally describe the contest and its rules.");
			$form->submit("Create contest");
			
			$page->add($form);
		}
		else {
			$contest = new Contest(null);
			
			$contest->creator = $user->name;
			$contest->title = $page->get("title", true, 200);
			$contest->due = strtotime($page->get("due"));
			$contest->desc = $page->get("desc", false, 10000, SANITISE_NONE);
			
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
	
	if ($user && $contest->creator === $user->name) {
		$page->link_button("", "Edit contest info", "./?a=contest-edit&id=$contest_id", true);
	}
	
	$page->para("<b>Created on:</b> " . date("Y-m-d H:i:s", $contest->created));
	$page->para("<b>" . ($contest->due > time() ? "Ends" : "Ended") . " on:</b> " . date("Y-m-d H:i:s", $contest->due));
	$page->para("<b>Hosted by:</b> @$contest->creator");
	
	// Description section
	if ($contest->desc) {
		$page->heading(2, "Description");
		$pd = new Parsedown();
		$pd->setSafeMode(true);
		$page->add($pd->text($contest->desc));
	}
	
	// Submissions section
	$page->heading(2, "Submissions");
	
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
			$desc_text = $desc_text ? "<p class=\"card-text\"><b>Description:</b><br/>$desc_text</p>" : "";
			$delete_text = ($user && $contest->creator === $user->name) ? ("<a href=\"./?a=contest-delete-submission&cid=$contest->id&sid=$submission->id&csrf=" . $user->get_sak() . "\" class=\"btn btn-outline-danger\">Delete</a>") : "";
			
			$page->add("
			<div id=\"sub-$submission->id\" class=\"card\" style=\"margin: 12px 0 12px 0;\">
				<div class=\"card-header\"><b>$submission->title</b> by $creator_text</div>
				<div class=\"card-body\">
					<p class=\"card-text\"><b>Submitted on:</b> $time_text</p>
					<p class=\"card-text\"><b>URL:</b> <a href=\"$submission->url\">$submission->url</a></p>
					$desc_text
					$delete_text
				</div>
				<div class=\"card-footer text-body-secondary\">Submission ID: <a class=\"text-body-secondary\" href=\"#sub-$submission->id\">$submission->id</a></div>
			</div>");
		}
	}
});

$gEndMan->add("contest-edit", function (Page $page) {
	$user = user_get_current();
	
	if ($user) {
		$id = $page->get("id");
		
		if (!contest_exists($id)) {
			$page->info("Whoops!", "The contest that you want to edit doesn't seem to exist.");
		}
		
		$contest = new Contest($id);
		
		// Handle contest editor permissions
		if ($contest->creator !== $user->name) {
			$page->info("Sorry", "You are not allowed to edit this contest becuase you did not create it.");
		}
		
		if (!$page->has("submit")) {
			$page->force_bs();
			$page->title("Edit $contest->title");
			$page->heading(1, "Edit $contest->title");
			
			$form = new Form("./?a=contest-edit&id=$contest->id&submit=1");
			$form->textbox("title", "Title", "The name of the contest.", $contest->title);
			$form->textbox("due", "Due date", "The date that the contest will stop accepting submissions.", date("Y-m-d H:i:s", $contest->due));
			$form->textaera("desc", "Description", "Optionally describe the contest and its rules.", $contest->desc);
			$form->submit("Save changes");
			
			$page->add($form);
		}
		else {
			$contest->title = $page->get("title", true, 200);
			$contest->due = strtotime($page->get("due"));
			$contest->desc = $page->get("desc", false, 10000, SANITISE_NONE);
			
			$contest->save();
			
			alert("Contest manager @$user->name updated contest $contest->id", "./?a=contest-view&id=$contest->id");
			
			$page->redirect("./?a=contest-view&id=$contest->id");
		}
	}
	else {
		$page->info("Whoops!", "Please log in to edit contests.");
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

$gEndMan->add("contest-delete-submission", function (Page $page) {
	$user = user_get_current();
	$cid = $page->get("cid");
	$sid = $page->get("sid");
	
	if ($user) {
		if (!$user->verify_sak($page->get("csrf"))) {
			$page->info("CSRF detected", "A possible CSRF attack was detected.");
		}
		
		if (!contest_exists($cid)) {
			$page->info("Contest not found", "The contest with the given ID was not found.");
		}
		
		$contest = new Contest($cid);
		
		if ($contest->creator === $user->name) {
			if (!$contest->disassociate_submission($sid)) {
				$page->info("Failed to delete", "Failed to delete item.");
			}
			
			$contest->save();
			
			$submission = new ContestSubmission($sid);
			$submission->delete();
			
			alert("Contest manager @$user->name deleted submission ID $submission->id ($submission->title)", "./?a=contest-view&id=$contest->id");
			
			$page->redirect("./?a=contest-view&id=$contest->id");
		}
		else {
			$page->info("Sorry", "Only the creator of a contest can delete a submission from it.");
		}
	}
	else {
		$page->info("Not logged in", "You need to log in to delete a submission.");
	}
});
