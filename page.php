<?php

define("SANITISE_HTML", 1);
define("SANITISE_EMAIL", 2);
define("SANITISE_NONE", 3);

define("PAGE_MODE_API", 1);
define("PAGE_MODE_HTML", 2);
define("PAGE_MODE_RAW", 3);

class Page {
	public $title;
	public $body;
	public $mode;
	public $request;
	public $forceBS;
	
	function __construct() {
		$this->title = null;
		$this->body = "";
		$this->mode = PAGE_MODE_HTML;
		$this->forceBS = false;
	}
	
	function set_mode(int $mode) : void {
		$this->mode = $mode;
		
		if ($mode == PAGE_MODE_API) {
			$this->body = [];
			$this->type("application/json");
			$this->request = $this->get_json();
		}
	}
	
	function http_header(string $key, string $value) : void {
		// TODO: Defer?
		header("$key: $value");
	}
	
	function cookie(string $key, string $value, int $expire = 1209600) {
		// TODO: Defer?
		setcookie($key, $value, time() + $expire, "/");
	}
	
	function get_cookie(string $key) {
		if (array_key_exists($key, $_COOKIE)) {
			return $_COOKIE[$key];
		}
		
		return null;
	}
	
	function redirect(string $url) : void {
		$this->http_header("Location", $url);
		die();
	}
	
	function type(string $contenttype) : void {
		$this->http_header("Content-Type", $contenttype);
	}
	
	function allow_cache() : void {
		$this->http_header("Cache-Control", "max-age=86400");
	}
	
	function info($title = "Done", $desc = "") : void {
		if ($this->mode != PAGE_MODE_API) {
			include_header(true);
			echo "<h1>$title</h1><p>$desc</p>";
			include_footer(true);
		}
		else {
			$this->set("status", $title);
			$this->set("message", $desc);
			$this->send();
		}
		die();
	}
	
	function get(string $key, bool $require = true, ?int $length = null, int $sanitise = SANITISE_HTML, $require_post = false) : ?string {
		$value = null;
		
		if ($this->mode == PAGE_MODE_API && array_key_exists($key, $this->request)) {
			$value = $this->request[$key];
		}
		else if (array_key_exists($key, $_POST)) {
			$value = $_POST[$key];
		}
		
		if (!$require_post && array_key_exists($key, $_GET)) {
			$value = $_GET[$key];
		}
		
		// We consider a blank string not to be a value
		if ($value === "") {
			$value = null;
		}
		
		// NOTE We need account for the fact that the string zero in php is
		// considered truthy :/
		if ($require && !$value && $value !== "0") {
			$this->info("An error occured", "Error: parameter '$key' is required.");
		}
		
		// Validate length
		if ($length && strlen($value) > $length) {
			if ($require) {
				$this->info("Max length exceded", "The parameter '$key' is too long. The max length is $length characters.");
			}
			else {
				return null;
			}
		}
		
		// If we have the value, we finally need to sanitise it.
		if ($value) {
			switch ($sanitise) {
				case SANITISE_HTML: {
					$value = htmlspecialchars($value);
					break;
				}
				case SANITISE_NONE: {
					break;
				}
				default: {
					$value = "";
					break;
				}
			}
		}
		
		return $value;
	}
	
	function get_file(string $key, string $require_format = null, int $max_size = 400000) {
		if (!array_key_exists($key, $_FILES)) {
			$this->info("Whoops!", "You didn't put the file by id '$key'.");
		}
		
		$file = $_FILES[$key];
		
		$name = $file["name"];
		$size = $file["size"];
		$format = $file["type"];
		
		// Checks
		if ($require_format && $require_format !== $format) {
			$this->info("Whoops!", "That's not a $require_format file!");
		}
		
		if ($size > $max_size) {
			$this->info("Whoops!", "The file " . htmlspecialchars($name) . " is too large!");
		}
		
		// Get contents
		$contents = file_get_contents($file["tmp_name"]);
		
		// Return contents
		return $contents;
	}
	
	function get_json() {
		/**
		 * If in API mode, get the body of the request as JSON.
		 */
		
		try {
			$result = json_decode(file_get_contents("php://input"), true);
			
			if (!$result) {
				return [];
			}
			
			return $result;
		}
		catch (Exception $e) {
			return [];
		}
	}
	
	function set(string $key, mixed $value) {
		/**
		 * Set an output value for JSON mode
		 */
		
		$this->body[$key] = $value;
	}
	
	function has(string $key) : bool {
		return (array_key_exists($key, $_POST) || array_key_exists($key, $_GET));
	}
	
	function csrf(User $user) {
		if (!$user->verify_sak($this->get("key"))) {
			$this->info("CSRF attack detected", "A CSRF attack was detected. If you encoutered this while trying to use the site normally, please report this issue to staff.");
		}
	}
	
	function title(string $title) : void {
		$this->title = $title;
	}
	
	function heading(int $level, string $data, ?string $size = null) : void {
		$size = ($size) ? (" style=\"font-size: $size;\"") : ("");
		$this->add("<h$level$size>$data</h$level>");
	}
	
	function para(string $text) : void {
		$this->add("<p>$text</p>");
	}
	
	function section_start(string $title, string $desc) : void {
		$a = "";
		$a .= "<div class=\"mod-edit-property\">";
			$a .= "<div class=\"mod-edit-property-label\">";
				// If there is no title there is little reason for a desc. as well.
				if ($title) {
					$a .= "<h4>$title</h4>";
					$a .= "<p>$desc</p>";
				}
			$a .= "</div>";
			$a .= "<div class=\"mod-edit-property-data\">";
		
		$this->add($a);
	}
	
	function section_end() {
		$a = "";
			$a .= "</div>";
		$a .= "</div>";
		
		$this->add($a);
	}
	
	function link_button(string $icon, string $title, string $url, bool $primary = false) : void {
		$this->add("<a href=\"$url\"><button class=\"btn " . ($primary ? "btn-primary" : "btn-outline-secondary") . "\">$title</button></a>");
	}
	
	function global_header() : void {
		assert($this->mode === PAGE_MODE_HTML);
	}
	
	function global_footer() : void {
		assert($this->mode === PAGE_MODE_HTML);
	}
	
	function force_bs() : void {
	    $this->forceBS = true;
	}
	
	function add(string | Form $data) : void {
		if ($data instanceof Form) {
			$this->body .= $data->render();
		}
		else {
			$this->body .= $data;
		}
	}
	
	function addFromFile(string $path) : void {
		/**
		 * String should be a literal otherwise you almost certianly have local
		 * file inclusion security flaws.
		 */
		
		$this->add(file_get_contents($path));
	}
	
	private function render_html() : string {
		assert($this->mode === PAGE_MODE_HTML);
		
		$data = "";
		
		$data .= $this->body;
		
		return $data;
	}
	
	private function render_json() : string {
		assert($this->mode === PAGE_MODE_API);
		
		return json_encode($this->body);
	}
	
	function render() : string {
		switch ($this->mode) {
			case PAGE_MODE_HTML:
				return $this->render_html();
				break;
			case PAGE_MODE_API:
				return $this->render_json();
				break;
			default:
				return $this->body;
				break;
		}
	}
	
	function send() : void {
		if ($this->mode === PAGE_MODE_HTML) {
			global $gTitle; $gTitle = $this->title;
			include_header($this->forceBS);
		}
		
		echo $this->render();
		
		if ($this->mode === PAGE_MODE_HTML) {
			include_footer($this->forceBS);
		}
		
		die();
	}
}

class Piece {
	/**
	 * A page piece
	 */
	
	public $data;
	
	function __construct() {
		$this->data = "";
	}
	
	function add(string $s) {
		$this->data .= $s;
	}
	
	function render() {
		return $this->data;
	}
}

function get_page_name() {
	return str_replace("/", ",", str_replace(".", ",", $_GET["p"]));
}

function include_header($bs = false) {
    $v = (array_key_exists("usebootstrap", $_GET) || $bs) ? "2" : "";
	include_once("../../data/_header$v.html");
}

function include_static_page() {
	// If we have no static page then don't do anything.
	if (!array_key_exists("p", $_GET)) {
		echo "<h1>Sorry</h1><p>That page does not exist!</p>";
		return;
	}
	
	$page_name = get_page_name();
	$path = "../../data/pages/static/" . $page_name . ".html";
	
	if (file_exists($path)) {
		readfile($path);
	}
	else {
		echo "<h1>Sorry</h1><p>That page does not exist!</p>";
	}
}

function include_footer($bs = false) {
    $v = (array_key_exists("usebootstrap", $_GET) || $bs) ? "2" : "";
	include_once("../../data/_footer$v.html");
}
