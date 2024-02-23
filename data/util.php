<?php

function http_get(string $url, string $headers = "") {
	/**
	 * Do a GET request to the given URL with the given body.
	 */
	
	$options = [
		"http" => [
			"method" => "GET",
			"header" => $headers,
			"timeout" => 3,
		]
	];
	
	$context = stream_context_create($options);
	$result = @file_get_contents($url, false, $context);
	
	return $result;
}

function post(string $url, string $body, string $content_type = "application/json", string $headers = "") {
	/**
	 * Do a POST request to the given URL with the given body.
	 */
	
	$options = [
		"http" => [
			"method" => "POST",
			"header" => "Content-Type: $content_type\r\n$headers",
			"content" => $body,
			"timeout" => 3,
		]
	];
	
	$context = stream_context_create($options);
	$result = @file_get_contents($url, false, $context);
	
	return $result;
}

function send_discord_message(string $message, string $webhook_url = "") {
	if (!$webhook_url) {
		return;
	}
	
	$body = [
		"content" => $message,
	];
	
	post($webhook_url, json_encode($body));
}

$gCustomEmotes = [
	"TailsHeya" => "https://smashhitlab.000webhostapp.com/lab/storage/5f36e7c6bad6e4d8d6b486efc8d40119a193a64759d649de06562cd5c2710243.webp",
	"TailsHeh" => "https://smashhitlab.000webhostapp.com/lab/storage/46201a8e8233236f9c21638898e0c06cfdc034f2b261ed6b185c9402d33f1def.png",
	"TailsPlushStare" => "https://smashhitlab.000webhostapp.com/lab/storage/92d417e7fb8997b72212fad4af6770e9718f676e5de8a36b571b697b575f233e.webp",
];

function render_markdown(string $body) : string {
	global $gCustomEmotes;
	
	$pd = new Parsedown();
	$pd->setSafeMode(true);
	$pd->setBreaksEnabled(true);
	$body = $pd->text($body);
	
	foreach ($gCustomEmotes as $name => $url) {
		$body = str_replace(":$name:", "<img src=\"$url\" alt=\":$name:\" title=\":$name:\" height=\"24px\"/>", $body);
	}
	
	return $body;
}

function alert(string $title, string $url = "") {
	/**
	 * Add a notification to a user's inbox.
	 */
	
	// Create the message
	$webhook_url = get_config("discord_webhook", "");
	$message = date("Y-m-d H:i:s", time()) . " — " . $title . ($url ? "\n[Relevant link](https://smashhitlab.000webhostapp.com/lab/$url)" : "");
	
	// Send via primary webhook
	send_discord_message($message, $webhook_url);
	
	// Send to secondary webhook
	$webhook_url = get_config("secondary_discord_webhook", "");
	
	if ($webhook_url) {
		send_discord_message($message, $webhook_url);
	}
}

function crush_ip(?string $ip = null) : string {
	/**
	 * Crush an IP address into a partial hash.
	 * 
	 * Normally IP addresses are used to deny access, so it's okay if there are
	 * collisions (and in fact this should help with privacy).
	 * 
	 * TODO IPv6 address might not be handled as well
	 * 
	 * TODO This is also used for denying tokens from the wrong IP, so it's worth
	 * considering if this mitigates that.
	 */
	
	if ($ip === null) {
		$ip = $_SERVER["REMOTE_ADDR"];
	}
	
	return substr(md5($ip), 0, 6);
}

function dechexa(int $num) {
	if ($num < 16) {
		return "0" . dechex($num);
	}
	else {
		return dechex($num);
	}
}

function frand() : float {
	return mt_rand() / mt_getrandmax();
}

function copy_object_vars(object &$to, object $from) {
	/**
	 * Load everything from object $from into object $to
	 */
	
	foreach (get_object_vars($from) as $key => $value) {
		$to->$key = $value;
	}
}

function __js_style_var__(string $var, string $val) : string {
	return "qs.style.setProperty('$var', '$val');";
}

function render_accent_script(string $colour) {
	$p = new Piece();
	
	$swatch = derive_pallete_from_colour(colour_from_hex($colour));
	$darkest = $swatch[0];
	$dark = $swatch[1];
	$darkish = $swatch[2];
	$bright = $swatch[3];
	
	$p->add("<script>var qs = document.querySelector(':root');");
	
	$p->add(__js_style_var__("--colour-primary", $bright));
	$p->add(__js_style_var__("--colour-primary-darker", "#ffffff"));
	$p->add(__js_style_var__("--colour-primary-hover", "#ffffff"));
	$p->add(__js_style_var__("--colour-primary-a", $bright . "40"));
	$p->add(__js_style_var__("--colour-primary-b", $bright . "80"));
	$p->add(__js_style_var__("--colour-primary-c", $bright . "c0"));
	$p->add(__js_style_var__("--colour-primary-text", "#000000"));
	
	$p->add(__js_style_var__("--colour-background-light", $darkish));
	$p->add(__js_style_var__("--colour-background-light-a", $darkish . "40"));
	$p->add(__js_style_var__("--colour-background-light-b", $darkish . "80"));
	$p->add(__js_style_var__("--colour-background-light-c", $darkish . "c0"));
	$p->add(__js_style_var__("--colour-background-light-text", $bright));
	
	$p->add(__js_style_var__("--colour-background", $dark));
	$p->add(__js_style_var__("--colour-background-a", $dark . "40"));
	$p->add(__js_style_var__("--colour-background-b", $dark . "80"));
	$p->add(__js_style_var__("--colour-background-c", $dark . "c0"));
	$p->add(__js_style_var__("--colour-background-text", $bright));
	
	$p->add(__js_style_var__("--colour-background-dark", $darkest));
	$p->add(__js_style_var__("--colour-background-dark-a", $darkest . "40"));
	$p->add(__js_style_var__("--colour-background-dark-b", $darkest . "80"));
	$p->add(__js_style_var__("--colour-background-dark-c", $darkest . "c0"));
	$p->add(__js_style_var__("--colour-background-dark-text", $bright));
	$p->add(__js_style_var__("--colour-background-dark-text-hover", $bright));
	
	$p->add("</script>");
	
	return $p->render();
}

function create_form_dialogue_code(string $id, string $url, string $title, string $body, string $button, string $height = "80vh") {
	return "<form action=\"$url\" method=\"post\">
<div id=\"shl-dialogue-container-$id\" class=\"dialogue-bg\" style=\"display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #000c; z-index: 1000;\">
	<div class=\"card dialogue-surface\" style=\"position: relative; top: 50%; left: 50%; transform: translate(-50%, -50%); width: max(50vw, 20em); height: $height;\"><div class=\"card-body\">
		<div class=\"dialogue-seperation\" style=\"display: grid; grid-template-rows: 3em auto 3em; height: 100%;\">
			<div style=\"grid-row: 1; margin-bottom: 3em;\">
				<h4>$title</h4>
			</div>
			<div style=\"grid-row: 2;\">
				$body
			</div>
			<div style=\"grid-row: 3;\">
				<div style=\"display: grid; grid-template-columns: auto auto;\">
					<div style=\"grid-column: 1;\">
						<button type=\"button\" class=\"btn btn-outline-secondary button secondary\" onclick=\"shl_hide_dialogue('$id')\">Close</button>
					</div>
					<div style=\"grid-column: 2; text-align: right;\">
						$button
					</div>
				</div>
			</div>
		</div>
	</div></div>
</div>
</form>";
}
