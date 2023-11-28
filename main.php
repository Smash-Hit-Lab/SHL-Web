<?php

// Main endpoint manager
require_once "endpoint.php";

// Event manager
require_once "event.php";

// Parsedown library for markdown formatting
require_once "Parsedown.php";

// Everying Everywhere All At Once
require_once "admin.php";
require_once "auth.php";
require_once "config.php";
require_once "crypto.php";
require_once "database.php";
require_once "discussion.php";
require_once "form.php";
require_once "forum.php";
require_once "ipblock.php";
require_once "mod.php";
require_once "mod_services.php";
require_once "news.php";
require_once "notifications.php";
require_once "page.php";
require_once "site.php";
require_once "styles.php";
require_once "templates.php";
require_once "user.php";
require_once "userblock.php";
require_once "util.php";
require_once "version.php";

function handle_action(string $action, Page $page) {
	switch ($action) {
	// ---- MOD PAGES ---- //
		case "mod_update":
		/*DEPRECATED*/ case "edit_mod": edit_mod(); break;
		case "save_mod": save_mod(); break;
		case "mod_history": mod_history(); break;
		case "mod_delete":
		case "delete_mod": delete_mod(); break;
		case "list_mods": list_mods(); break;
	// ---- MORE USER ACCOUNT STUFF ---- //
		case "edit_account": edit_account(); break;
		case "save_account": save_account(); break;
		case "account_delete": account_delete(); break;
	// ---- NEWS STUFF ---- //
		case "update_news": update_news(); break;
		case "save_news": save_news(); break;
	// ---- DISCUSSIONS ---- //
		case "discussion_update": discussion_update(); break;
		case "discussion_hide": discussion_hide(); break;
		case "discussion_follow": discussion_follow(); break;
		case "discussion_lock": discussion_lock(); break;
		case "discussion_view": discussion_view(); break;
		case "discussion_poll": discussion_poll(); break;
	// ---- ADMIN ACTION PAGES ---- //
		case "site_config": do_site_config(); break;
		case "backup_db": do_backup_db(); break;
		case "storage_download": do_storage_download(); break;
		case "storage_list": do_storage_list(); break;
		case "user_roles": do_user_roles(); break;
		case "user_ban": do_user_ban(); break;
		case "user_delete": do_user_delete(); break;
		case "user_verify": user_verify(); break;
		case "admin_dashboard": do_admin_dashboard(); break;
		case "send_notification": do_send_notification(); break;
		// Transitioning to using Endpoint Manager
		default: {
			global $gEndMan; $okay = $gEndMan->run($action, $page);
			
			if (!$okay) {
				$page->info("Sorry", "The action you have requested is not currently implemented.");
			}
			/// @hack This is here for now b/c we can't have it elsewhere right now
			else {
				$page->send();
			}
			
			break;
		}
	}
}

function main() {
	/**
	 * Called in the index.php script
	 */
	
	$page = new Page();
	
	if (0) {
	    http_response_code(203);
	    
	    $page->heading(1, "Sorry");
	    $page->para("The Smash Hit Lab has closed temporarially as of 31 July 2023. It will reopen when it is transferred to someone who can take care of it better.");
	    
	    $page->send();
	}
	
	// Mastodon are fucks
	// See https://jort.link/
	$agent = $_SERVER['HTTP_USER_AGENT'];
	
	
	if (str_contains(strtolower($agent), "mastodon") || !$agent) {
		http_response_code(203);
		echo "<html><head><title>Go away</title></head><body>Go away</body></html>";
		die();
	}
	
	if (get_config("require_logins_everywhere", false) && ((array_key_exists("a", $_GET)) ? (!str_starts_with($_GET["a"], "auth-")) : (true)) && !user_get_current()) {
		header("Location: /?a=auth-login&redirect=" . urlencode($_SERVER['REQUEST_URI']));
		die();
	}
	
	if (array_key_exists("a", $_GET)) {
		handle_action($_GET["a"], $page);
	}
	else if (array_key_exists("m", $_GET)) {
		display_mod();
	}
	else if (array_key_exists("u", $_GET)) {
		display_user($_GET["u"]);
	}
	else if (array_key_exists("n", $_GET)) {
		display_news($_GET["n"]);
	}
	// DEPRECATED: Static pages are deprecated, should use news articles now!
	// Update: They now redirect to news articles.
	else if (array_key_exists("p", $_GET)) {
		display_news($_GET["p"]);
	}
	else {
		// Redirect to home page
		header("Location: ./?n=home");
		die();
	}
}
