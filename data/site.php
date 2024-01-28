<?php
/**
 * Misc. things to do with the site.
 */

$gSitename = null;

function get_site_name() : string {
	global $gSitename;
	
	if ($gSitename === null) {
		$gSitename = get_config("sitename");
	}
	
	return $gSitename ? $gSitename : "My New Community";
}
