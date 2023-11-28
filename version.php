<?php

$gLabbywareVersion = [0, 8, 3];

$gEndMan->add("about", function (Page $page) {
	global $gLabbywareVersion;
	
	$major = $gLabbywareVersion[0];
	$minor = $gLabbywareVersion[1];
	$patch = $gLabbywareVersion[2];
	
	$page->heading(1, "About LabbyWare");
	$page->para("This website is powered by Labbyware. Labbyware is Copyright &#169; 2023 Smash Hit Lab");
	$page->para("Running labbyware version: v$major.$minor.$patch");
});
