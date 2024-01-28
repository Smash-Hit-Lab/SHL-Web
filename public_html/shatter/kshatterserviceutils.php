<?php

if (!defined("APP_LOADED")) {
    die();
}

function KSHeader(Page $page, string $title = "Shatter") {
	$page->add("<!DOCTYPE html>
<html>
	<head>
		<title>$title</title>
        <link rel=\"stylesheet\" type=\"text/css\" href=\"/shatter/ui.css\">
	</head>
	<body class=\"main-content-fullpage\">");
}

function KSFooter(Page $page) {
	$page->add("
	</body>
</html>");
}
