<?php

if (!defined("APP_LOADED")) {
    die();
}

class SegmentClaim {
	public $id;
	public $by;
	public $created;
	
	function __construct(string $id) {
		$db = new Database("segmentclaim");
		
		if ($db->has($id)) {
			copy_object_vars($this, $db->load($id));
		}
		else {
			$this->id = $id;
			$this->by = "";
			$this->created = time();
		}
	}
	
	function set_by_and_save(string $by) : void {
		$this->by = $by;
		$db = new Database("segmentclaim");
		$db->save($this->id, $this);
	}
}

function segment_claim_exists(string $id) : bool {
	/**
	 * Check if a weak user exists
	 */
	
	$db = new Database("segmentclaim");
	return $db->has($id);
}

function hash_segment_data(?string $data) : ?string {
	return $data ? hash("sha3-256", str_replace("\r\n", "\n", $data)) : null;
}

$gEndMan->add("weak-user-claim", function (Page $page) {
	if ($page->get("magic") != md5("popularFurryVtuberYorshex")) {
		$page->info("error", "There was an error doing that.");
	}
	
	$weak = weak_user_current($page->get("uid"), $page->get("token"));
	
	if ($weak) {
		$hash = hash_segment_data($page->get("data", true, 400000, SANITISE_NONE));
		
		if (segment_claim_exists($hash)) {
			$page->info("already_exists", "This segment has already been claimed.");
		}
		
		$sc = new SegmentClaim($hash);
		$sc->set_by_and_save($weak->id);
		
		$page->info("done", "You have claimed the segment with the SHA3-256 hash of $hash!");
	}
	else {
		$page->info("not_authed", "You need to be logged in to claim segments.");
	}
});

$gEndMan->add("segment-lookup-ui", function (Page $page) {
	$page->set_mode(PAGE_MODE_HTML);
	
	KSHeader($page, "Segment info lookup");
	
	if (!$page->has("submit")) {
		$page->heading(1, "Look up segment info");
		
		// By segment file
		$page->heading(3, "By segment file");
		
		$form = new Form("./api.php?action=segment-lookup-ui&submit=1");
		$form->upload("data", "Segment", "");
		$form->submit("Look up details");
		
		$page->add($form);
		
		// By hash
		$page->heading(3, "By SHA3-256 hash");
		
		$form = new Form("./api.php?action=segment-lookup-ui&submit=1");
		$form->textbox("hash", "Hash", "");
		$form->submit("Look up details");
		
		$page->add($form);
	}
	else {
		$hash = $page->get("hash", false, 512);
		
		if (!$hash) {
			$hash = hash_segment_data($page->get_file("data"));
		}
		
		if (!segment_claim_exists($hash)) {
			$page->heading(1, "This segment is not claimed!");
			$page->para("No one has claimed this segment at the moment, so no data is available.");
			$page->para("SHA3-256 hash: $hash");
			KSFooter($page);
			$page->send();
		}
		
		$claim = new SegmentClaim($hash);
		$user = new WeakUser($claim->by);
		
		$creator_name = ($user->is_deleted() ? "<i>Account deleted</i>" : ($user->creator ? $user->creator : "<i>None</i>"));
		
		$page->heading(1, "Segment lookup results");
		$page->para("SHA3-256 hash: $hash");
		$page->heading(3, "<b>Creator</b>");
		$page->para("Shatter User ID: $user->id");
		$page->para("Creator name: $creator_name");
		$page->para("<span style=\"opacity: 0.6;\">Note: The creator can put anything into the feild. Make sure to be careful with links, and don't assume that this is the original creator.</span>");
		$page->para("Creator registered on: " . get_formatted_datetime($user->created));
		$page->para("Segment claimed on: " . get_formatted_datetime($claim->created));
		$page->para("<a href=\"https://discord.gg/28kHvwVP9z\">Report abuse on Shatter discord</a>");
	}
	
	KSFooter($page);
});
