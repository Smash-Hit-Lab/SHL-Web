<?php

require_once "database.php"; // Needed for the article stuff

function form_feild($name, $type, $title, $desc, $value, $enabled = true, $options = null) : void {
	edit_feild($name, $type, $title, $desc, $value, $enabled, $options);
}

function form_textbox($name, $title, $desc, $value = "") : void {
	/**
	 * @deprecated Use forms
	 */
	
	form_feild($name, "text", $title, $desc, $value);
}

function form_textarea($name, $title, $desc, $value = "") : void {
	/**
	 * @deprecated Use forms
	 */
	
	form_feild($name, "textarea", $title, $desc, $value);
}

function edit_feild($name, $type, $title, $desc, $value, $enabled = true, $options = null) : void {
	/**
	 * @deprecated Use forms
	 */
	
	if (!$value) {
		$value = "";
	}
	
	echo "<div class=\"mod-edit-property\">";
		echo "<div class=\"mod-edit-property-label\">";
			// If there is no title there is little reason for a desc. as well.
			if ($title) {
				echo "<h4>$title</h4>";
				echo "<p>$desc</p>";
			}
		echo "</div>";
		echo "<div class=\"mod-edit-property-data\">";
			switch ($type) {
				case "text":
					$s = ($enabled) ? "" : " readonly";
					echo "<input type=\"text\" name=\"$name\" placeholder=\"$title\" value=\"$value\" $s/>";
					break;
				case "textarea":
					echo "<textarea name=\"$name\">$value</textarea>";
					break;
				case "select":
					echo "<select name=\"$name\">";
					$k = array_keys($options);
					
					for ($i = 0; $i < sizeof($k); $i++) {
						$key = $k[$i];
						$val = $options[$k[$i]];
						$selected = ($key == $value) ? "selected" : "";
						
						echo "<option value=\"$key\" $selected>$val</option>";
					}
					
					echo "</select>";
					break;
				case "button":
					$k = array_keys($options);
					
					echo "<div id=\"$name\">";
					
					for ($i = 0; $i < sizeof($k); $i++) {
						$key = $k[$i];
						$val = $options[$k[$i]];
						
						echo "<p><a href=\"$key\"><button>$val</button></a></p>";
					}
					
					echo "</div>";
					
					break;
				case "label":
					echo $value;
					break;
				case "submit":
					$sak = user_get_sak();
					echo "<input type=\"hidden\" name=\"key\" value=\"$sak\">";
					echo "<input type=\"submit\" value=\"$desc\"/>";
					break;
				default:
					echo "$value";
					break;
			}
			if (!$enabled) {
				echo "<p><i>This value is read-only.</i></p>";
			}
		echo "</div>";
	echo "</div>";
}

function form_start(string $url, string $method = "post", string $csrf = "") {
	/**
	 * @deprecated Use forms
	 */
	
	$url = htmlspecialchars($url);
	echo "<form action=\"$url\" method=\"$method" . (($csrf) ? "&key=" . $csrf : "") . "\">";
}

function form_end(string $submit_text = "Submit form") {
	/**
	 * @deprecated Use forms
	 */
	
	//echo "<input type=\"submit\" value=\"$submit_text\"/>";
	edit_feild(null, "submit", null, $submit_text, null);
	echo "</form>";
}

function action_button(string $url, string $title) {
	$csrf = (new User(get_name_if_authed()))->get_sak();
	form_start($url, "post", $csrf);
	form_end($title);
}

function mod_property($title, $desc, $value, $conditional = false) : void {
	if ($conditional && !$value) {
		return;
	}
	
	echo "<div class=\"mod-property\">";
		echo "<div class=\"mod-property-label\">";
			echo "<p><b>$title</b></p>";
			echo "<p class=\"small-text\">$desc</p>";
		echo "</div>";
		echo "<div class=\"mod-property-data\">";
			if (str_starts_with($value ? $value : "", "https://")) {
				echo "<p><a href=\"" . htmlspecialchars($value) . "\">" . $value . "</a></p>";
			} else {
				echo "<p>" . ($value ? "$value" : "<i>Not available</i>") . "</p>";
			}
		echo "</div>";
	echo "</div>";
}

function sorry(string $why = "There was a problem.", string $extra = "") {
	/**
	 * @deprecated Use page->info()
	 */
	
	global $gTitle;
	$gTitle = "There was an error !!!";
	
	include_header();
	echo "<h1>Sorry</h1><p>$why</p>";
	echo $extra;
	include_footer();
	die();
}

function inform(string $title = "Success", string $why = "The action completed successfully.", string $extra = "") {
	/**
	 * @deprecated Use page->info()
	 */
	
	global $gTitle;
	$gTitle = "Success !!!";
	
	include_header();
	echo "<h1>$title</h1><p>$why</p>";
	echo $extra;
	include_footer();
	die();
}

function validate_length(string $feild_name, string $what, int $max_length = 100) : void {
	if (strlen($what) > $max_length) {
		sorry("$feild_name should be at most $max_length characters.");
	}
}

function rich_format(string $base, bool $trusted = false) : string {
	/**
	 * Sanitise and convert rich formatted user text.
	 */
	
	$filtered = htmlspecialchars($base);
	
	// Here comes the parser ... !!!
	$body = "<p>";
	$bold = false; // Are we currently bold?
	$italic = false; // Are we currently italic?
	$code = false; // Are we currently code?
	$pre = false; // Are we in a pre tag?
	$big = false; // Are we big text?
	
	// This parser is really not great, but it's simple and does what it does
	// do quite well and really I don't feel like a big parser right now.
	for ($i = 0; $i < strlen($filtered); $i++) {
		$s = substr($filtered, $i);
		
		if (str_starts_with($s, "\\")) {
			// Escape seqence
			if (strlen($s) > 1) {
				$body = $body . $s[1];
			}
			
			$i += 1;
		}
		else if (str_starts_with($s, "**")) {
			// If we are bold, then don't do it again..
			if ($bold) {
				$body = $body . "</b>";
			}
			else {
				$body = $body . "<b>";
			}
			
			$i += 1; // Add an extra so we don't just get italics
			$bold = !$bold;
		}
		else if (str_starts_with($s, "__")) {
			if ($italic) {
				$body = $body . "</i>";
			}
			else {
				$body = $body . "<i>";
			}
			
			$i += 1;
			$italic = !$italic;
		}
		else if (str_starts_with($s, "```")) {
			if ($pre) {
				$body = $body . "</pre>";
			}
			else {
				$body = $body . "<pre>";
			}
			
			$i += 2;
			
			// We should skip over newlines for the start and end
			(str_starts_with($s, "```\n") && !$pre) ? $i += 1 : $i += 2;
			
			$pre = !$pre;
		}
		else if (str_starts_with($s, "`")) {
			if ($code) {
				$body = $body . "</code>";
			}
			else {
				$body = $body . "<code>";
			}
			
			$code = !$code;
		}
		else if (str_starts_with($s, "^^^")) {
			if ($big) {
				$body = $body . "</span>";
			}
			else {
				$body = $body . "<span class=\"cb-quote\">";
			}
			
			$i += 2;
			$big = !$big;
		}
		else if (str_starts_with($s, "@")) {
			$end = strcspn($s, " \r\n\t", 1, 24);
			
			// If we didn't find it then just use whatever...
			if ($end < 0) {
				$end = strlen($s);
			}
			
			$handle = substr($s, 1, $end);
			
			if (!user_exists($handle)) {
				$body = $body . "@";
			}
			else {
				// Get display name and handle
				$body = $body . get_nice_display_name($handle, false);
				
				// Skip the handle text
				$i += strlen($handle);
			}
		}
		else if ($trusted && str_starts_with($s, "{{")) {
			$end = strpos($s, "}}");
			
			// oH FUCK  so much indentation
			if ($end < 0) {
				$body = $body . "{";
			}
			else {
				$length = $end;
				$url = substr($s, 2, $end - 2);
				
				// Yay, a database lookup during parsing ...
				$db = new RevisionDB("article");
				
				// If this is an article reference ...
				if ($db->has($url)) {
					$art = $db->load($url);
					
					$title = $art->title;
					$date = "Last updated " . date("Y-m-d H:i", $art->updated);
					$text = htmlspecialchars(str_replace(array("\n", "_", "*", "`", "{", "}"), " ", substr($art->body, 0, 100))) . "...";
					
					$body = $body . "<div class=\"new-news-article-card\"><h4><a href=\"./?n=$url\">$title</a></h4><p class=\"small-text\">$date</p><p>$text</p></a></div>";
				}
				// Otherwise try for a youtube embed URL ...
				else if (str_starts_with($url, "yt:")) {
					$url = substr($url, 3);
					
					$body = $body . "<iframe width=\"100%\" height=\"600px\" src=\"https://www.youtube-nocookie.com/embed/$url\" frameborder=\"0\" allow=\"accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share\" style=\"border-radius: 0.5em;\" allowfullscreen></iframe>";
				}
				// Try for an image...
				else if (str_starts_with($url, "img:")) {
					$url = substr($url, 4);
					
					$body = $body . "</p><img class=\"billboard\" src=\"$url\"/><p>";
				}
				// Otherwise this is just a bare URL ...
				else {
					$body = $body . "<a href=\"$url\" rel=\"nofollow\">$url</a>";
				}
				
				$i += $end + 1; // Skip the unneeded chars
			}
		}
		else {
			$body = $body . $filtered[$i];
		}
	}
	
	// If we are still bold or italics or anything else we need to stop that!
	if ($bold) {
		$body = $body . "</b>";
	}
	
	if ($italic) {
		$body = $body . "</i>";
	}
	
	if ($code) {
		$body = $body . "</code>";
	}
	
	if ($pre) {
		$body = $body . "</pre>";
	}
	
	if ($big) {
		$body = $body . "</span>";
	}
	
	// Dobule newlines -> paragraphs
	$body = str_replace("\r\n\r\n", "</p><p>", $body);
	$body = str_replace("\n\n", "</p><p>", $body);
	
	// Single newlines -> linebreaks
	$body = str_replace("\r\n", "<br/>", $body);
	$body = str_replace("\n", "<br/>", $body);
	
	// Final closing tag
	$body = $body . "</p>";
	
	return $body;
}

function parse_comma_array(string $s) : array {
	return explode(",", str_replace(" ", "", $s));
}

function create_comma_array(array $s) : string {
	return join(",", $s);
}

function create_comma_array_nice(array $s) : string {
	return join(", ", $s);
}

function redirect(string $location) {
	header("Location: $location");
	die();
}

function download_file(string $file) : void {
	/**
	 * Force a file download
	 * 
	 * NOTE: This is taken from the php manual.
	 */
	
	if (file_exists($file)) {
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="'.basename($file).'"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($file));
		readfile($file);
		die();
	}
	else {
		sorry("The file that you wanted to download doesn't seem to exist.");
	}
}

function list_folder(string $path) {
	/**
	 * List the contents of a folder
	 */
	
	$array = scandir($path);
	array_shift($array);
	array_shift($array);
	
	return $array;
}

function datetounix($d, $m, $y) {
	return strtotime("$d-$m-$y");
}
