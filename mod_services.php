<?php

class ServiceMod {
	public $id;
	public $title;
	public $persistent;
	public $revision;
	public $ad_png;
	public $ad_xml;
	public $imperssions;
	
	function __construct(?string $id) {
		$db = new Database("services");
		
		if ($id && $db->has($id)) {
			$info = $db->load($id);
			
			$this->id = $info->id;
			$this->title = $info->title;
			$this->persistent = (property_exists($info, "persistent")) ? $info->persistent : true;
			$this->revision = (property_exists($info, "revision")) ? $info->revision : 0;
			$this->ad_png = $info->ad_png;
			$this->ad_xml = $info->ad_xml;
			$this->imperssions = (property_exists($info, "imperssions")) ? $info->imperssions : 0;
		}
		else {
			$this->id = $id;
			$this->title = "New untitled instance";
			$this->persistent = true;
			$this->revision = 0;
			$this->ad_png = "";
			$this->ad_xml = "";
			$this->imperssions = 0;
		}
	}
	
	function save() {
		$db = new Database("services");
		$db->save($this->id, $this);
	}
	
	function exists() : bool {
		$db = new Database("services");
		return $db->has($this->id);
	}
	
	function delete() : void {
		$db = new Database("services");
		$db->delete($this->id);
	}
	
	function create(User $user, string $title) {
		// Generate ID
		$db = new Database("services");
		
		do {
			$this->id = random_base64(5);
		} while ($db->has($this->id));
		
		// Set everything else
		$this->title = $title;
		$this->save();
		
		$user->add_mod($this->id);
	}
	
	function set_ads(string $ad_png, string $ad_xml) {
		$this->imperssions = 0;
		$this->revision += 1;
		$this->ad_png = base64_encode($ad_png);
		$this->ad_xml = base64_encode($ad_xml);
		$this->save();
	}
	
	function incr_imperssions() {
		$this->imperssions += 1;
		$this->save();
	}
}

$gEndMan->add("services-home", function (Page $page) {
	$user = user_get_current();
	
	$page->force_bs();
	
	if ($user) {
		$page->heading(1, "Mod Services");
		$page->link_button("add", "Create instance", "./?a=services-create", true);
		
		$page->add("<p style=\"text-align: center;\">");
		$page->add("<ul>");
		for ($i = 0; $i < sizeof($user->mods); $i++) {
			$id = $user->mods[$i];
			$sv = new ServiceMod($id);
			$page->add("<li><a href=\"./?a=services-info&id=$id\">$sv->title</a> (id = $id)</li>");
		}
		$page->add("</ul>");
		$page->add("</p>");
	}
	else {
		$page->info("Log in to access services!", "You need to <a href=\"./?a=auth-login\">log in</a> or <a href=\"./?a=auth-register\">create an account</a> to access mod services.");
	}
});

$gEndMan->add("services-create", function (Page $page) {
	$user = user_get_current();
	
	$page->force_bs();
	
	if ($user && $user->is_verified()) {
		if (!$page->has("submit")) {
			$page->heading(1, "Create instance");
			
			$form = new Form("./?a=services-create&submit=1");
			$form->textbox("title", "Title", "The title of mod services instance.");
			$form->submit("Create instance");
			
			$page->add($form);
		}
		else {
			$sv = new ServiceMod(null);
			$sv->create($user, $page->get("title"));
			
			alert("@$user->name created a mod service with id $sv->id (Title: $sv->title)", "./?a=services-info&id=$sv->id");
			
			if (strtolower($sv->title) === "owo") {
				$page->info("OwO what's this?", "<a href=\"./?a=services-info&id=$sv->id\">Are wou twying to cewatwe a mawd swewcie? Amawswing! UwU</a>");
			}
			else {
				$page->redirect("./?a=services-info&id=$sv->id");
			}
		}
	}
	else if ($user && !$user->is_verified()) {
		$page->info("We're sorry, but ...", "... you need to be verified to use this service, as it could be abused if we allow unverified users to use it.");
	}
	else {
		$page->info();
	}
});

$gEndMan->add("services-info", function (Page $page) {
	$user = user_get_current();
	$id = $page->get("id");
	
	$page->force_bs();
	
	if ($user && (($user->has_mod($id) && $user->is_verified()) || $user->is_admin())) {
		$really_owns_mod = $user->has_mod($id);
		
		$sv = new ServiceMod($id);
		
		$page->heading(1, $sv->title);
		
		$page->heading(3, "Advertisements");
		
		if ($really_owns_mod) {
			$page->section_start("Advertisements", "You can create and update the ad channel for your mod.");
			$page->link_button("new_releases", "Update adverts", "./?a=services-adverts&id=$id");
			$page->section_end();
		}
		
		$page->section_start("Preview ads", "Preview what your ad looks like.");
		$page->link_button("image", "Preview adverts", "./?a=services-adverts-preview&id=$id");
		$page->section_end();
		
		if ($really_owns_mod) {
			$page->heading(3, "Tools");
			
			$page->section_start("Patches", "Patches that you can apply to libsmashhit.so.");
			$page->link_button("layers", "How to patch", "./?a=services-patch&id=$id");
			$page->section_end();
		}
		
		$page->heading(3, "Other things");
		
		if ($really_owns_mod) {
			$page->section_start("Edit properties", "Update information about this mod services instance.");
			$page->link_button("edit", "Edit properties", "./?a=services-update&id=$id");
			$page->section_end();
		}
		
		$page->section_start("Delete services", "Delete this mod service.");
		$page->link_button("delete", "Delete service", "./?a=services-delete&id=$id");
		$page->section_end();
		
		$page->section_start("Mod ID", "The identifier for your mod.");
		$page->para("<code>$sv->id</code>");
		$page->section_end();
	}
	else {
		$page->info("Sorry!", "You do not have access to this mod!");
	}
});

$gEndMan->add("services-update", function (Page $page) {
	$user = user_get_current();
	$id = $page->get("id");
	
	$page->force_bs();
	
	if ($user && $user->has_mod($id) && $user->is_verified()) {
		if (!$page->has("submit")) {
			$sv = new ServiceMod($id);
			
			$page->heading(1, "Editing \"$sv->title\"");
			
			$form = new Form("./?a=services-update&id=$id&submit=1");
			$form->textbox("title", "Title", "The title of your services instance.", $sv->title);
			$form->select("persist", "Agerssion", "Agression controls how often your ad will be shown to players.", [
				"0" => "Show only once per revision",
				 "1" => "Show ads every time",
			], $sv->persistent ? "1" : "0");
			$form->submit("Update instance");
			
			$page->add($form);
		}
		else {
			$sv = new ServiceMod($id);
			$sv->title = $page->get("title");
			$sv->persistent = ($page->get("persist") === "1");
			$sv->save();
			
			alert("@$user->name updated mod service with id $id (\"$sv->title\")", "./?a=services-info&id=$id");
			
			$page->redirect("./?a=services-info&id=$id");
		}
	}
	else {
		$page->info("Sorry!", "You do not have access to this mod!");
	}
});

$gEndMan->add("services-delete", function (Page $page) {
	$user = user_get_current();
	$id = $page->get("id");
	
	$page->force_bs();
	
	if ($user && (($user->has_mod($id) && $user->is_verified()) || $user->is_admin())) {
		if (!$page->has("submit")) {
			$page->heading(1, "Delete services");
			
			$sv = new ServiceMod($id);
			
			$form = new Form("./?a=services-delete&id=$id&key=" . $user->get_sak() . "&submit=1");
			$form->container("Warning", "", "Preforming this action will <b>delete the mod service \"$sv->title\" ($id) forever and break any mods using it</b>. Please consider this carefully.");
			$form->submit("Delete service");
			
			$page->add($form);
		}
		else {
			if (!$user->verify_sak($page->get("key"))) {
				$page->info("Error", "Key not accepted.");
			}
			
			$sv = new ServiceMod($id);
			$sv->delete();
			
			$user->remove_mod($id);
			
			alert("@$user->name deleted mod service with id $id (\"$sv->title\")");
			
			$page->redirect("./?a=services-home");
		}
	}
	else {
		$page->info("Sorry!", "You do not have access to this mod!");
	}
});

$gEndMan->add("services-adverts", function (Page $page) {
	$user = user_get_current();
	$id = $page->get("id");
	
	$page->force_bs();
	
	if ($user && $user->has_mod($id) && $user->is_verified()) {
		if (!$page->has("submit")) {
			$page->heading(1, "Update advertisements");
			
			$form = new Form("./?a=services-adverts&id=$id&submit=1");
			$form->upload("file_xml", "XML file", "The UI XML file for your advert.");
			$form->upload("file_png", "PNG file", "The PNG image for your advert.");
			$form->submit("Save adverts");
			
			$page->add($form);
		}
		else {
			$sv = new ServiceMod($id);
			$sv->set_ads(
				$page->get_file("file_png", "image/png"),
				$page->get_file("file_xml", "text/xml"));
			
			alert("@$user->name pushed new ads revision for mod service $id (\"$sv->title\")", "./?a=services-adverts-preview&id=$id");
			
			$page->redirect("./?a=services-info&id=$id");
		}
	}
	else {
		$page->info("Sorry!", "You do not have access to this mod!");
	}
});

$gEndMan->add("services-adverts-preview", function (Page $page) {
	$user = user_get_current();
	$id = $page->get("id");
	
	$page->force_bs();
	
	if ($user && (($user->has_mod($id) && $user->is_verified()) || $user->is_admin())) {
		$sv = new ServiceMod($id);
		
		$page->heading(1, "Preview advertisement");
		$page->add("<img src=\"data:image/png;base64," . $sv->ad_png . "\"/>");
		$page->add("<pre>" . htmlspecialchars(base64_decode($sv->ad_xml)) . "</pre>");
	}
	else {
		$page->info("Sorry!", "You do not have access to this mod!");
	}
});

$gEndMan->add("services-patch", function (Page $page) {
	$user = user_get_current();
	$id = $page->get("id");
	
	$page->force_bs();
	
	if ($user && $user->has_mod($id) && $user->is_verified()) {
		$page->heading(1, "Patching libsmashhit binary");
		$page->para("This is how to download the Patch Tool to patch your <code>libsmashhit.so</code> for the ad service.");
		$page->add("<ol>
			<li>To start, <a href=\"https://github.com/Smashing-Tech/Libsmashhit-Tools/releases/download/v0.3.2/patch.py\">download this script</a> and open it.</li>
			<ol>
				<li>TL;DR of how to do this: download <a herf=\"https://python.org/\">Python</a> and then run <code>py ./patch.py</code> in a command prompt (exact command depending on platform).</li>
			</ol>
			<li>Open your libsmashhit in the patch tool. Please note that it only supports ARM64 1.4.2 and 1.4.3 binaries and ARM32 binaries cannot be patched.</li>
			<li>Find the option that mentions ads and the mod ID.</li>
			<li>Check the box to the left of the option to enable the ads, then paste the string <code>$id</code> in the text box.</li>
			<li>Click the button at the bottom that says \"Patch libsmashhit.so\" to patch your binary.</li>
			<li>You are done and can now close the patch tool.</li>
		</ol>");
	}
	else {
		$page->info("Sorry!", "You do not have access to this mod!");
	}
});

$gEndMan->add("get-ads-info", function (Page $page) {
	// Platform is where the ID goes unless we really have the id
	$platform = $page->has("id") ? $page->get("id") : $page->get("platform");
	// due to the way I implement the patch (the EZ way) that means i cannot
	// require the version of the game
	//$version = $page->get("version");
	$rev = (int) $page->get("rev");
	$date = $page->get("date");
	
	// Determine what the revision to push to user is
	$sv = new ServiceMod($platform);
	
	if ($sv->persistent) {
		$rev += 1;
	}
	else {
		$rev = $sv->revision;
	}
	
	// temp
	//(new ServiceMod($platform))->incr_imperssions();
	
	$page->set_mode(PAGE_MODE_RAW);
	$page->type("text/xml");
	$page->add("<ads revision=\"$rev\" showfront=\"1\" onlyfree=\"0\" sale=\"0\" folder=\"?a=get-ads-data&amp;id=$platform&amp;name=\"/>");
});

$gEndMan->add("get-ads-data", function (Page $page) {
	$id = $page->get("id");
	$name = $page->get("name");
	
	$sv = new ServiceMod($id);
	
	$page->set_mode(PAGE_MODE_RAW);
	
	if (str_ends_with($name, "png")) {
		$page->type("image/png");
		$page->add(base64_decode($sv->ad_png));
	}
	else if (str_ends_with($name, "xml")) {
		$page->type("text/xml");
		$page->add(base64_decode($sv->ad_xml));
	}
});
