<?php

function validate_modpage_name(string $name) : bool {
	$chars = str_split("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890_-.");
	
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
	public $wiki;
	public $description;
	public $download;
	public $code;
	public $tags;
	public $version;
	public $updated;
	public $created;
	public $author;
	public $reason;
	public $security;
	public $reviews;
	public $visibility;
	
	function __construct(string $package, int $revision = -1) {
		$db = new RevisionDB("mod");
		
		if ($db->has($package)) {
			$mod = $db->load($package, $revision);
			
			$this->package = $mod->package;
			$this->name = $mod->name;
			$this->creators = $mod->creators;
			$this->wiki = $mod->wiki;
			$this->description = $mod->description;
			$this->download = $mod->download;
			$this->code = $mod->code;
			$this->tags = $mod->tags;
			$this->version = $mod->version;
			$this->updated = $mod->updated;
			$this->created = property_exists($mod, "created") ? $mod->created : time();
			$this->author = property_exists($mod, "author") ? $mod->author : "";
			$this->reason = property_exists($mod, "reason") ? $mod->reason : "";
			$this->security = $mod->security;
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
			$disc->set_url("./?m=$this->package");
		}
		else {
			$this->package = $package;
			$this->name = "Untitled Mod";
			$this->creators = array();
			$this->wiki = null;
			$this->description = null;
			$this->download = null;
			$this->code = null;
			$this->tags = array();
			$this->version = null;
			$this->updated = time();
			$this->created = time();
			$this->author = "";
			$this->reason = "";
			$this->security = "";
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
	
	function display() {
		$stalker = user_get_current();
		
		if ($this->image) {
			echo "<div class=\"mod-banner\" style=\"background-image: linear-gradient(to top, #222c, #0008), url('$this->image');\">";
			echo "<h1>" . $this->get_display_name() . "</h1>";
			echo "</div>";
		}
		else {
			echo "<h1>" . $this->get_display_name() . "</h1>";
		}
		
		// Header
		if ($stalker) {
			echo "<p class=\"centred\">";
			if (in_array($stalker->name, $this->creators, true) || $stalker->is_mod()) {
			    echo "<a href=\"./?a=edit_mod&m=$this->package\"><button><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">edit</span> Edit this mod</button></a> ";
			}
			echo "<a href=\"./?a=mod_history&m=$this->package\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">history</span> History</button></a> ";
			
			if (get_name_if_mod_authed()) {
			    echo "<a href=\"./?a=mod-rename&oldslug=$this->package\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">edit_location</span> Rename</button></a> ";
				echo "<a href=\"./?a=mod_delete&package=$this->package\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">delete</span> Delete</button></a> ";
			}
			
			echo "</p>";
		}
		
		if ($this->description) {
			echo "<h3>Description</h3>";
			
			
			$pd = new Parsedown();
			$pd->setSafeMode(true);
			echo "<div id=\"mod-about\" class=\"shl-magic-editor-feild\">";
			echo $pd->text($this->description);
			echo "</div>";
		}
		
		if ($this->download || $this->version || $this->creators) {
			echo "<h3>Basics</h3>";
		}
		
		// Download area
		$download_content = "";
		
		if (!str_starts_with($this->download, "http")) {
			$download_content = $this->download;
		}
		else if (!$stalker && (time() - $this->created) < (60 * 60 * 24 * 3)) {
			$download_content = "<div class=\"thread-card\">
			<p><b>You need an account to view this info.</b></p>
			<p>To prevent spam, mods created in the last 3 days cannot be downloaded by users without an account. Please create an account or come back soon!</p>
			<p><a href=\"./?a=auth-login\"><button><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">login</span> Login</button></a> <a href=\"./?a=auth-register\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">person_add</span> Register</button></a></p>
		</div>";
		}
		else {
			$download_content = "<p>
			<a href=\"$this->download\"><button><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">save_alt</span> Download</button></a>
			<button id=\"shl-mod-copy-url\" class=\"button secondary\" onclick=\"shl_copy('$this->download', 'shl-mod-copy-url')\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">content_copy</span> Copy link</button>
			</p>
			<p class=\"small-text\"><b>Note</b>: Anyone with an account can edit links. Make sure you trust a link before installing a mod from it.</p>";
		}
		
		// Creators list
		// This code sucks
		$creators_content = "<!-- Where the fuck does this p tag come from??? -->";
		
		for ($i = 0; $i < sizeof($this->creators); $i++) {
			$user = $this->creators[$i];
			
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
		
		mod_property("Download", "A link to where the mod can be downloaded.", $download_content, true);
		mod_property("Version", "The latest version of this mod.", $this->version, true);
		mod_property("Creators", "The people who created this mod.", $creators_content, true);
		//mod_property("Security", "A short statement on this mod's security.", $this->security, true);
		
		if ($this->wiki || $this->code || $this->status || $this->package) {
			echo "<h3>Stats</h3>";
		}
		
		mod_property("Wiki article", "A relevant wiki article about the mod.", $this->wiki, true);
		mod_property("Source code", "A link to where the source code for a mod can be found.", $this->code, true);
		mod_property("Status", "A short description of the mod's development status.", $this->status, true);
		
		echo "<p class=\"small-text\">This page was last updated at " . date("Y-m-d H:i", $this->updated) . " by " . get_nice_display_name($this->author) . "</p>";
		
		echo "<script>shl_magic_editor_init();</script>";
		
		$disc = new Discussion($this->reviews);
		$disc->display_reverse("Reviews", "./?m=" . $this->package);
		
		if ($this->colour) {
			echo render_accent_script($this->colour);
		}
	}
	
	function display_history() {
		echo "<h1>History of " . ($this->name ? $this->name : $this->package) . "</h1>";
		
		$db = new RevisionDB("mod");
		$history = $db->history($this->package);
		
		echo "<ul>";
		
		for ($i = (sizeof($history) - 1); $i >= 0; $i--) {
			$rev = $history[$i];
			
			echo "<li><a href=\"./?m=$this->package&index=$i\">Edit at " . date("Y-m-d H:i:s", $rev->updated) . "</a> by <a href=\"./@$rev->author\">$rev->author</a> &mdash; $rev->reason</li>";
		}
		
		echo "</ul>";
	}
	
	function display_edit() {
		$current = user_get_current();
		$is_mod = $current->is_mod();
		$is_verified = $current->is_verified();
		
		echo "<h1>Editing " . ($this->name ? $this->name : $this->package) . "</h1>";
		echo "<form action=\"./?a=save_mod&amp;m=$this->package\" method=\"post\">";
		echo "<h3>Main</h3>";
		edit_feild("name", "text", "Name", "The name that will be displayed with the mod.", $this->name);
		edit_feild("description", "textarea", "About", "One or two paragraphs that describe the mod.", htmlspecialchars($this->description));
		
		if ($is_verified) {
			echo "<h3>Appearance</h3>";
			edit_feild("image", "text", "Banner image", "The URL of the banner image to use for this mod.", $this->image);
			edit_feild("colour", "text", "Colour", "Colourise the appearance of the site for this mod (hex code)", $this->colour);
		}
		
		echo "<h3>Basic</h3>";
		edit_feild("download", "text", "Download", "A link to where the mod can be downloaded.", $this->download);
		edit_feild("version", "text", "Version", "The latest version of this mod.", $this->version);
		edit_feild("creators", "text", "Creators", "The people who created this mod.", create_comma_array($this->creators));
		//edit_feild("security", "text", "Security", "A short statement on this mod's security.", $this->security);
		edit_feild("wiki", "text", "Wiki article", "A relevant wiki article about the mod.", $this->wiki);
		
		echo "<h3>Extra</h3>";
		//edit_feild("code", "text", "Source code", "A link to where the source code for a mod can be found.", $this->code);
		edit_feild("tags", "text", "Tags", "Keywords and categorical description of this mod.", create_comma_array($this->tags));
		
		edit_feild("status", "select", "Status", "A short description of the mod's development status.", $this->status, true, [
			"" => "None",
			"Released" => "Released",
			"Abandoned" => "Abandoned",
			"Completed" => "Completed",
			"On hiatus" => "On hiatus",
			"Incomplete" => "Incomplete",
			"Planning" => "Planning"
		]);
		
		edit_feild("visibility", "select", "Visibility", "Choose if this mod page will appear on the main mods list.", $this->visibility, true, [
			"public" => "Public",
			"unlisted" => "Unlisted",
		]);
		
		echo "<h3>Edit info</h3>";
		edit_feild("reason", "text", "Edit reason", "Optional description of why this mod was edited.", "");
		
		form_end("Save edits");
	}
	
	function save_edit(string $whom) {
	    $current = user_get_current();
		$is_mod = $current->is_mod();
		$is_verified = $current->is_verified();
		
		validate_length("Name", $_POST["name"], 100);
		validate_length("creators", $_POST["creators"], 300);
		validate_length("wiki", $_POST["wiki"], 500);
		validate_length("description", $_POST["description"], 2000);
		validate_length("download", $_POST["download"], 500);
		//validate_length("code", $_POST["code"], 500);
		validate_length("tags", $_POST["tags"], 300);
		validate_length("version", $_POST["version"], 100);
		//validate_length("security", $_POST["security"], 200);
		validate_length("status", $_POST["status"], 50);
		validate_length("reason", $_POST["reason"], 400);
		
		$this->name = htmlspecialchars($_POST["name"]);
		$this->creators = parse_comma_array(htmlspecialchars($_POST["creators"]));
		$this->wiki = htmlspecialchars($_POST["wiki"]);
		$this->description = $_POST["description"]; // Rich text feild
		$this->download = htmlspecialchars($_POST["download"]);
		//$this->code = htmlspecialchars($_POST["code"]);
		$this->tags = parse_comma_array(htmlspecialchars($_POST["tags"]));
		$this->version = htmlspecialchars($_POST["version"]);
		$this->updated = time();
		$this->author = $whom;
		//$this->security = htmlspecialchars($_POST["security"]);
		$this->status = htmlspecialchars($_POST["status"]);
		$this->reason = htmlspecialchars($_POST["reason"]);
		$this->visibility = htmlspecialchars($_POST["visibility"]);
		
		if (!in_array($whom, $this->creators, true) && !$is_mod && !$this->exists()) {
		    $this->creators = array($whom);
		}
		
		// For some reason I didn't put this behind an adminwall before :skull:
		if ($is_verified) {
			validate_length("image", $_POST["image"], 1000);
			validate_length("colour", $_POST["colour"], 15);
			
			$this->image = htmlspecialchars($_POST["image"]);
			$this->colour = htmlspecialchars($_POST["colour"]);
		}
		
		$this->save();
	}
	
	function save_edit_api(Page $page, string $whom, $data) {
		if (strlen($data["about"]) > 4000) {
			$page->info("too_long", "The about section for this mod is too long. Please limit the about section to 4000 characters.");
		}
		
		$this->updated = time();
		$this->author = $whom;
		$this->description = $data["about"];
		
		$this->save();
	}
	
	function delete() {
		$db = new RevisionDB("mod");
		$db->delete($this->package);
		discussion_delete_given_id($this->reviews);
	}
}

function display_mod() {
	/**
	 * Ugly HACK -ed version to make the title display without much effort.
	 */
	
	$revision = array_key_exists("index", $_GET) ? $_GET["index"] : -1;
	
	if (!((new RevisionDB("mod"))->has($_GET["m"]))) {
		include_header();
		echo "<h1>We don't have that!</h1><p>This mod page does not exist.</p>";
		include_footer();
		return;
	}
	
	$mod = new ModPage($_GET["m"], $revision);
	
	global $gTitle; $gTitle = $mod->name;
	
	if ($revision >= 0) {
		$gTitle = $gTitle . " (old rev $revision)";
	}
	
	include_header();
	$mod->display();
	include_footer();
}

function edit_mod() : void {
	include_header();
	
	if (!array_key_exists("m", $_GET)) {
		echo "<h1>Sorry</h1><p>Bad request.</p>";
		include_footer();
		return;
	}
	
	$mod_name = $_GET["m"];
	
	if (!validate_modpage_name($mod_name)) {
		echo "<h1>Invalid page name</h1><p>The mod page slug is not a valid slug. They should only include lowercase and uppercase basic latin letters, abraic numerals and the underscore, dot and dash.</p>";
		include_footer();
		return;
	}
	
	if (!get_name_if_authed()) {
		echo "<h1>Sorry</h1><p>You need to <a href=\"./?p=login\">log in</a> or <a href=\"./?p=register\">create an account</a> to edit pages.</p>";
		include_footer();
		return;
	}
	
	$mod = new ModPage($mod_name);
	$mod->display_edit();
	
	include_footer();
}

function mod_history() : void {
	include_header();
	
	if (!array_key_exists("m", $_GET)) {
		echo "<h1>Sorry</h1><p>Bad request.</p>";
		include_footer();
		return;
	}
	
	$mod_name = $_GET["m"];
	
	if (!get_name_if_authed()) {
		echo "<h1>Sorry</h1><p>You need to <a href=\"./?p=login\">log in</a> or <a href=\"./?p=register\">create an account</a> to view page history.</p>";
		include_footer();
		return;
	}
	
	$mod = new ModPage($mod_name);
	$mod->display_history();
	
	include_footer();
}

function save_mod() : void {
	if (!array_key_exists("m", $_GET)) {
		sorry("Malformed request.");
	}
	
	$mod_name = $_GET["m"];
	$user = get_name_if_authed();
	
	if (!$user) {
		sorry("You need to <a href=\"./?p=login\">log in</a> or <a href=\"./?p=register\">create an account</a> to save pages.");
	}
	
	$mod = new ModPage($mod_name);
    
	// Make sure the user has permissions to edit the page
	if (!(in_array($user, $mod->creators, true) || user_get_current()->is_mod() || !$mod->exists())) {
	    sorry("You don't have permission to edit this page!");
	}
	
	$mod->save_edit($user);
	
	// Admin alert!
	alert("Mod page $mod_name updated by @$user\nUsed legacy editor\nReason: $mod->reason", "./?m=$mod_name");
	
	redirect("./?m=$mod_name");
}

$gEndMan->add("mod-save", function (Page $page) {
	$user = get_name_if_authed();
	
	$page->set_mode(PAGE_MODE_API);
	
	//if (!$user) {
	if (true) {
		$page->set("status", "not_authed");
		$page->set("message", "You need to log in first!");
		$page->send();
	}
	else {
    	$data = $page->get_json();
    	
    	$mod = new ModPage($data["page"]);
    	$mod->save_edit_api($page, $user, $data);
    	
    	alert("Mod page #$mod->package updated by @$user\nUsed magic editor", "./?m=$mod->package");
    	
    	$page->set("status", "done");
    	$page->set("message", "The changes were saved successfully!");
	}
});

function delete_mod() : void {
	$user = get_name_if_mod_authed();
	
	if ($user) {
		if (!array_key_exists("page", $_POST)) {
			include_header();
			
			echo "<h1>Delete mod page</h1>";
			
			$default = false;
			
			if (array_key_exists("package", $_GET)) {
				$default = $_GET["package"];
			}
			
			form_start("./?a=delete_mod");
			edit_feild("page", "text", "Page name", "The name of the page to delete. This is the same as the mod's package name.", $default, !$default);
			edit_feild("reason", "text", "Reason", "Type a short reason that you would like to delete this page (optional).", "");
			form_end("Delete mod page");
			
			include_footer();
		}
		else {
			$mod = htmlspecialchars($_POST["page"]);
			$reason = htmlspecialchars($_POST["reason"]);
			
			if (!$reason) { $reason = "<i>No reason given</i>"; }
			
			$mod = new ModPage($mod);
			$mod->delete();
			
			alert("Mod page $mod->package deleted by @$user\n\nReason: $reason");
			
			include_header();
			echo "<h1>Page was deleted!</h1><p>The mod page and assocaited discussion was deleted successfully.</p>";
			include_footer();
		}
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}

$gEndMan->add("mod-list", function (Page $page) {
	$actor = user_get_current();
	
	$db = new RevisionDB("mod");
	
	$list = $db->enumerate();
	
	//include_header();
	$page->add( "<h1>List of Mods</h1>" );
	
	// Make mod modual
	if (get_name_if_authed()) {
		$page->addFromFile('../../data/_mkmod.html');
	}
	
	// Join message
	if (!$actor) {
		$page->add( "<div class=\"thread-card\">
			<p><b>Want to add your mod here?</b></p>
			<p>Log in or create an account to add your mod to the database.</p>
			<p><a href=\"./?a=auth-login\"><button><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">login</span> Login</button></a> <a href=\"./?a=auth-register\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">person_add</span> Register</button></a></p>
		</div>" );
	}
	
	// Grid of mods
	$page->add( "<div class=\"mod-listing\">" );
	
	for ($i = 0; $i < sizeof($list); $i++) {
		$mp = new ModPage($list[$i]);
		
		if ($mp->visibility !== "public") {
			continue;
		}
		
		$title = $mp->name ? $mp->name : $mp->package;
		$desc = htmlspecialchars(substr($mp->description, 0, 100));
		
		if (strlen($desc) >= 100) {
			$desc = $desc . "...";
		}
		
		$url = "./?m=" . urlencode($mp->package);
		$img = $mp->image ? $mp->image : "./?a=generate-logo-coloured&seed=$title";
		
		$page->add("
		<div class=\"mod-card-outer\">
			<a href=\"$url\">
			<div class=\"mod-card-image\" style=\"background-image: url('$img');\"></div>
			<div class=\"mod-card-data\">
				<h4>$title</h4>
				<p>$desc</p>
			</div>
			</a>
		</div>");
	}
	
	$page->add( "</div>" );
	
	//include_footer();
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
			$new_slug = $page->get("newslug");
			
			// Rename the page
			$mod = new ModPage($old_slug);
			$result = $mod->rename($new_slug);
			
			if ($result) {
				alert("@$user renamed mod page '$old_slug' to '$new_slug'", "./?m=$new_slug");
				$page->redirect("./?m=$new_slug");
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
