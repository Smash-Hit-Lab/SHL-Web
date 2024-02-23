<?php

$gEndMan->add("public-upload", function (Page $page) {
	/**
	 * Upload a file to public storage
	 */
	
	global $gPublicStorage;
	
	$user = user_get_current();
	
	if ($user && $user->is_verified()) {
		if (!$page->has("submit")) {
			$page->title("Upload a file");
			$page->heading(1, "Upload a file");
			
			$form = new Form("./?a=public-upload&submit=1");
			$form->upload("file", "File", "The file you would like to upload.");
			$form->submit("Upload");
			
			$page->add($form);
		}
		else {
			$page->csrf($user);
			
			$fname = "none.bin";
			
			$content = $page->get_file("file", null, 350000, $fname);
			
			$ext = pathinfo($fname, PATHINFO_EXTENSION);
			$hash = sha256($content);
			$realname = "$hash.$ext";
			
			$gPublicStorage->save($realname, $content);
			
			alert("New file $realname uploaded by @$user->name", "./storage/$realname");
			
			$page->title("Upload complete");
			$page->heading(1, "File uploaded!");
			$page->para("Your file was uploaded as <code><a href=\"./storage/$realname\">$realname</a></code>.");
		}
	}
	else {
		$page->info("Please log in", "You need to be logged in to files.");
	}
});
