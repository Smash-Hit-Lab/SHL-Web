<?php

function validate_modpage_name(string $name) : bool {
	$chars = str_split("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890._-:");
	
	// Charset limit
	for ($i = 0; $i < strlen($name); $i++) {
		if (array_search($name[$i], $chars, true) === false) {
			return false;
		}
	}
	
	// Size limit
	if (strlen($name) > 36) {
		return false;
	}
	
	return true;
}

class ModPage {
	public $package;
	public $name;
	public $creators;
	public $description;
	public $download;
	public $code;
	public $version;
	public $updated;
	public $created;
	public $author;
	public $reason;
	public $reviews;
	public $visibility;
	
	function __construct(string $package, int $revision = -1) {
		$db = new RevisionDB("mod");
		
		if ($db->has($package)) {
			$mod = $db->load($package, $revision);
			
			$this->package = $mod->package;
			$this->name = $mod->name;
			$this->creators = $mod->creators;
			$this->description = $mod->description;
			$this->download = $mod->download;
			$this->code = $mod->code;
			$this->version = $mod->version;
			$this->updated = $mod->updated;
			$this->created = property_exists($mod, "created") ? $mod->created : time();
			$this->author = property_exists($mod, "author") ? $mod->author : "";
			$this->reason = property_exists($mod, "reason") ? $mod->reason : "";
			$this->status = $mod->status;
			$this->reviews = property_exists($mod, "reviews") ? $mod->reviews : random_discussion_name();
			$this->image = property_exists($mod, "image") ? $mod->image : "";
			$this->colour = property_exists($mod, "colour") ? $mod->colour : "";
			$this->visibility = property_exists($mod, "visibility") ? $mod->visibility : "public";
			
			// If there weren't discussions before, save them now.
			if (!property_exists($mod, "reviews")) {
				$this->save();
			}
			
			// Update discussions URL
			$disc = new Discussion($this->reviews);
			$disc->set_url("./~$this->package");
		}
		else {
			$this->package = $package;
			$this->name = "Untitled Mod";
			$this->creators = array();
			$this->description = null;
			$this->download = null;
			$this->code = null;
			$this->version = null;
			$this->updated = time();
			$this->created = time();
			$this->author = "";
			$this->reason = "";
			$this->status = "Released";
			$this->reviews = random_discussion_name();
			$this->image = "";
			$this->colour = "";
			$this->visibility = "public";
		}
	}
	
	function save() {
		$db = new RevisionDB("mod");
		$db->save($this->package, $this);
	}
	
	function exists() : bool {
	    $db = new RevisionDB("mod");
	    return $db->has($this->package);
	}
	
	function rename(string $new_slug) : bool {
		/**
		 * Rename the page, checking if it already exists.
		 * 
		 * Returns: false = page already exists, true = renamed successfully
		 */
		
		$db = new RevisionDB("mod");
		
		$new_slug = str_replace(" ", "_", $new_slug);
		
		// Check if page already exists
		if ($db->has($new_slug) || !validate_modpage_name($new_slug)) {
			return false;
		}
		
		// Delete old page
		$db->delete($this->package);
		
		// Create new page
		$this->package = $new_slug;
		$this->save();
		
		return true;
	}
	
	function get_display_name() {
		return ($this->name ? $this->name : $this->package);
	}
	
	function has_edit_perms(?User $user) {
		return $user && ( // one of:
			in_array($user->name, $this->creators, true)
			|| $user->is_mod()
			|| !$this->exists()
		);
	}
	
	function delete() {
		$db = new RevisionDB("mod");
		$db->delete($this->package);
		discussion_delete_given_id($this->reviews);
	}
	
	function get_title() {
		return str_replace("_", " ", $this->package);
	}
}

$gEndMan->add("mod-view", function (Page $page) {
	$stalker = user_get_current();
	$mod_id = $page->get("id");
	
	$page->force_bs();
	
	if (!((new RevisionDB("mod"))->has($mod_id))) {
		$page->info("Whoops!", "That mod page wasn't found.");
	}
	
	$mod = new ModPage($mod_id);
	
	$page->title($mod->get_title());
	
	$img = $mod->image ? $mod->image : "./?a=generate-logo-coloured&seed=" . $mod->get_title();
	
	if ($img) {
		$page->add("<div class=\"mod-banner\" style=\"background-image: linear-gradient(to top, #000c, #0008), url('$img');\">");
		$page->add("<h1 style=\"text-align: center;\">" . $mod->get_title() . "</h1>");
		$page->add("</div>");
	}
	else {
		$page->add("<h1>" . $mod->get_title() . "</h1>");
	}
	
	// Header
	if ($stalker) {
		$page->add("<p style=\"margin: 15px 0 15px 0;\">");
		
		if (in_array($stalker->name, $mod->creators, true) || $stalker->is_mod()) {
			$page->add("<a href=\"./?a=mod-edit&m=$mod->package\"><button class=\"btn btn-primary\">Edit page</button></a> ");
		}
		
		// $page->add("<a href=\"./?a=mod_history&m=$mod->package\"><button class=\"btn btn-outline-primary\">History</button></a> ");
		
		if (get_name_if_mod_authed()) {
			$page->add("<a href=\"./?a=mod-rename&oldslug=$mod->package\"><button class=\"btn btn-outline-primary\">Rename</button></a> ");
			$page->add("<a href=\"./?a=mod-delete&id=$mod->package\"><button class=\"btn btn-outline-danger\">Delete</button></a> ");
		}
		
		$page->add("</p>");
	}
	
	$page->add("<nav style=\"margin-bottom: 20px;\">
			<div class=\"nav nav-tabs\">
				<button class=\"nav-link active\" id=\"nav-about-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#nav-about\" type=\"button\" role=\"tab\" aria-controls=\"nav-about\" aria-selected=\"true\">About</button>
				<button class=\"nav-link\" id=\"nav-more-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#nav-more\" type=\"button\" role=\"tab\" aria-controls=\"nav-more\" aria-selected=\"false\">More</button>
				<button class=\"nav-link\" id=\"nav-reviews-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#nav-reviews\" type=\"button\" role=\"tab\" aria-controls=\"nav-reviews\" aria-selected=\"false\">Reviews</button>
			</div>
		</nav>");
	
	// Start of tabbed content area
	$page->add("<div class=\"tab-content\" id=\"nav-tabContent\">");
	
	// About
	$page->add("<div class=\"tab-pane fade show active\" id=\"nav-about\" role=\"tabpanel\" aria-labelledby=\"nav-about-tab\" tabindex=\"0\">");
	
	// Download area
	$download_content = "";
	
	if (!$mod->download) {
		$download_content = "<p><i>No download is available!</i></p>";
	}
	else if (!str_starts_with($mod->download, "http")) {
		$download_content = "<p>$mod->download</p>";
	}
	else if (!$stalker && (time() - $mod->created) < (60 * 60 * 24 * 3)) {
		$download_content = "<div class=\"thread-card\">
		<p><b>You need an account to view this info.</b></p>
		<p>To prevent spam, mods created in the last 3 days cannot be downloaded by users without an account. Please create an account or come back soon!</p>
		<p><a href=\"./?a=auth-login\"><button>Login</button></a> <a href=\"./?a=auth-register\"><button class=\"button secondary\">Register</button></a></p>
	</div>";
	}
	else {
		$download_content = "<p>
		<a href=\"$mod->download\"><button class=\"btn btn-primary\">Download</button></a>
		<button id=\"shl-mod-copy-url\" class=\"btn btn-outline-primary\" onclick=\"shl_copy('$mod->download', 'shl-mod-copy-url')\">Copy link</button>
		</p>";
	}
	
	// Creators list
	// This code sucks
	$creators_content = "<!-- Where the fuck does this p tag come from??? -->";
	
	for ($i = 0; $i < sizeof($mod->creators); $i++) {
		$user = $mod->creators[$i];
		
		$on_site = user_exists($user);
		$pfp = "./?a=generate-logo-coloured&seed=$user";
		
		if ($on_site) {
			$user = new User($user);
			$pfp = $user->image;
		}
		
		$creators_content .= "<div style=\"display: grid; grid-template-columns: 32px auto;\">
<div style=\"grid-column: 1; align-items: center;\"><img src=\"$pfp\" style=\"width: 32px; height: 32px; border-radius: 16px;\"/></div>
<div style=\"grid-column: 2; margin-left: 0.5em; align-items: center;\"><p>" . ($on_site ? "<a href=\"./@$user->name\">$user->display</a> (@$user->name)" : $user) . "</p></div>
</div>";
	}
	
	if ($mod->description) {
		$pd = new Parsedown();
		$pd->setSafeMode(true);
		$page->add($pd->text($mod->description));
	}
	
	$page->add("<h3>Download</h3>" . $download_content);
	$page->add("<h3>Creators</h3>" . $creators_content);
	
	$page->add("</div>");
	
	// More
	$page->add("<div class=\"tab-pane fade\" id=\"nav-more\" role=\"tabpanel\" aria-labelledby=\"nav-more-tab\" tabindex=\"0\">");
	
	$qiEntries = [
		["version", "Version", "s"],
		["code", "Source files", "s"],
		["status", "Status", "s"],
		["created", "Created at", "t"],
		["updated", "Updated at", "t"],
		["author", "Updated by", "s"],
	];
	
	for ($i = 0; $i < sizeof($qiEntries); $i++) {
		$propname = $qiEntries[$i][0];
		
		if ($mod->$propname) {
			switch ($qiEntries[$i][2]) {
				case "s": {
					$page->para("<b>" . $qiEntries[$i][1] . ":</b> " . $mod->$propname);
					break;
				}
				case "t": {
					$page->para("<b>" . $qiEntries[$i][1] . ":</b> " . date("Y-m-d H:i", $mod->$propname));
					break;
				}
			}
		}
	}
	
	// $page->add("<p class=\"small-text\">This page was last updated at " . date("Y-m-d H:i", $mod->updated) . " by " . get_nice_display_name($mod->author) . "</p>");
	$page->add("</div>");
	
	// Reviews
	$page->add("<div class=\"tab-pane fade\" id=\"nav-reviews\" role=\"tabpanel\" aria-labelledby=\"nav-reviews-tab\" tabindex=\"0\">");
	$disc = new Discussion($mod->reviews);
	$page->add($disc->render_reverse("Reviews", "./~" . $mod->package));
	$page->add("</div>");
	
	// End of tabbed area
	$page->add("</div>");
});

$gEndMan->add("mod-edit", function (Page $page) {
	$mod_name = $page->get("m");
	
	$mod_name = str_replace(" ", "_", $mod_name);
	
	if (!validate_modpage_name($mod_name)) {
		$page->info("Invalid page name", "The mod page slug is not a valid slug. They should only include lowercase and uppercase basic latin letters, abraic numerals and the underscore, dot and dash.");
	}
	
	$user = user_get_current();
	$is_mod = $user->is_mod();
	$is_verified = $user->is_verified();
	$mod = new ModPage($mod_name);
	
	if ($mod->has_edit_perms($user)) {
		if (!$page->has("submit")) {
			$page->add("<h1>Editing " . $mod->get_title() . "</h1>");
			
			$form = new Form("./?a=mod-edit&m=$mod->package&submit=1");
			
			$form->textarea("description", "About", "One or two paragraphs that describe the mod.", htmlspecialchars($mod->description));
			
			$form->textbox("image", "Banner image", "The URL of the banner image to use for this mod. This can only be edited by verified users.", $mod->image, $is_verified);
			
			$form->textbox("download", "Download", "A link to where the mod can be downloaded.", $mod->download);
			$form->textbox("code", "Source files", "An optional link to where the source files can be found.", $mod->code);
			$form->textbox("version", "Version", "The latest version of this mod.", $mod->version);
			$form->textbox("creators", "Creators", "People who will have premission to edit this mod's page and be credited with creating it.", create_comma_array($mod->creators));
			
			$form->select("status", "Status", "A short description of the mod's development status.", [
				"" => "None",
				"Released" => "Released",
				"Abandoned" => "Abandoned",
				"Completed" => "Completed",
				"On hiatus" => "On hiatus",
				"Incomplete" => "Incomplete",
				"Planning" => "Planning"
			], $mod->status);
			
			$form->select("visibility", "Visibility", "Choose if this mod page will appear on the main mods list.", [
				"public" => "Public",
				"unlisted" => "Unlisted",
			], $mod->visibility);
			
			$form->submit("Save page");
			
			$page->add($form);
		}
		else {
			// $mod->name = $page->get("name", true, 100);
			$mod->creators = parse_comma_array($page->get("creators", true, 1000));
			$mod->description = $page->get("description", NOT_NIL, 4000, SANITISE_NONE); // Rich text feild
			$mod->download = $page->get("download", NOT_NIL, 500);
			$mod->code = $page->get("code", NOT_NIL, 500);
			$mod->version = $page->get("version", NOT_NIL, 30);
			$mod->updated = time();
			$mod->author = $user->name;
			$mod->status = $page->get("status", NOT_NIL, 20);
			$mod->visibility = $page->get("visibility", true, 20);
			
			if (!in_array($user->name, $mod->creators, true) && !$mod->exists()) {
				$mod->creators = [$user->name];
			}
			
			if ($is_verified) {
				$mod->image = $page->get("image", NOT_NIL, 500);
			}
			
			$mod->save();
			
			alert("Mod page $mod->package updated by @$user->name", "./~$mod->package");
			
			$page->redirect("./~$mod->package");
		}
	}
	else {
		$page->info("Sorry", "You need to <a href=\"./?p=login\">log in</a> or <a href=\"./?p=register\">create an account</a> to edit pages.");
	}
});

$gEndMan->add("mod-delete", function (Page $page) {
	$user = user_get_current();
	
	if ($user && $user->is_mod()) {
		if (!$page->has("submit")) {
			$page->add("<h1>Delete mod page</h1>");
			
			$form = new Form("./?a=mod-delete&submit=1");
			$form->textbox("id", "Page name", "The name of the page to delete. This is the same as the mod's package name.", $page->get("id"), false);
			$form->textbox("reason", "Reason", "Type a short reason that you would like to delete this page.", "");
			$form->submit("Delete page");
			
			$page->add($form);
		}
		else {
			$page->csrf($user);
			
			$mod = $page->get("id", true, 30);
			$reason = $page->get("reason", NOT_NIL, 300);
			
			$mod = new ModPage($mod);
			$mod->delete();
			
			alert("Mod page $mod->package deleted by @$user\n\nReason: $reason");
			
			$page->info("Success", "The mod page and assocaited discussion was deleted successfully.");
		}
	}
	else {
		$page->info("Not logged in", "The action you have requested is not currently implemented.");
	}
});

$gEndMan->add("mod-list", function (Page $page) {
	$actor = user_get_current();
	
	$db = new RevisionDB("mod");
	
	$list = $db->enumerate();
	
	$page->title("List of mods");
	
	$page->heading(1, "List of Mods" );
	
	// Make mod modual
	if ($actor) {
		$page->addFromFile('../../data/_mkmod.html');
	}
	// Join message
	else {
		$page->add( "<div class=\"card thread-card\"><div class=\"card-body\">
			<p><b>Want to add your mod here?</b></p>
			<p>Log in or create an account to add your mod to the database.</p>
			<p class=\"card-text\"><a href=\"./?a=auth-login\"><button><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">login</span> Login</button></a> <a href=\"./?a=auth-register\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">person_add</span> Register</button></a></p>
		</div></div>" );
	}
	
	// Grid of mods
	$page->add( "<div class=\"mod-listing\">" );
	
	for ($i = 0; $i < sizeof($list); $i++) {
		$mp = new ModPage($list[$i]);
		
		if ($mp->visibility !== "public") {
			continue;
		}
		
		$title = $mp->get_title();
		$desc = htmlspecialchars(substr($mp->description, 0, 100));
		
		if (strlen($desc) >= 100) {
			$desc = $desc . "...";
		}
		
		$url = "./~" . urlencode($mp->package);
		$img = $mp->image ? $mp->image : "./?a=generate-logo-coloured&seed=$title";
		
		$page->add("
		<div class=\"mod-card-outer\">
			<a class=\"mod-card-link\" href=\"$url\">
			<div class=\"mod-card-image\" style=\"background-image: url('$img');\"></div>
			<div class=\"mod-card-data\">
				<h4>$title</h4>
				<p>$desc</p>
			</div>
			</a>
		</div>");
	}
	
	$page->add( "</div>" );
});

$gEndMan->add("mod-rename", function(Page $page) {
	$user = user_get_current();
	
	if ($user && $user->is_mod()) {
		if (!$page->has("submit")) {
			$form = new Form("./?a=mod-rename&submit=1");
			$form->hidden("oldslug", $page->get("oldslug"));
			$form->textbox("newslug", "New name", "What do you want the new name of the page to be?", $page->get("oldslug"));
			$form->submit("Rename page");
			
			$page->heading(1, "Rename page");
			$page->add($form);
		}
		else {
			$old_slug = $page->get("oldslug");
			$new_slug = str_replace(" ", "_", $page->get("newslug"));
			
			// Rename the page
			$mod = new ModPage($old_slug);
			$result = $mod->rename($new_slug);
			
			if ($result) {
				alert("@$user->name renamed mod page '$old_slug' to '$new_slug'", "./~$new_slug");
				$page->redirect("./~$new_slug");
			}
			else {
				$page->info("Something happened", "A page with this name already exists.");
			}
		}
	}
	else {
		$page->info("Sorry!", "You need to be logged in and at least a moderator to rename pages.");
	}
});
