<?php

if (!defined("APP_LOADED")) {
	die();
}

define("SANITISE_HTML", 1);
define("SANITISE_EMAIL", 2);
define("SANITISE_NONE", 3);

define("PAGE_MODE_API", 1);
define("PAGE_MODE_HTML", 2);
define("PAGE_MODE_RAW", 3);

class Page {
	public $body;
	public $mode;
	public $request;
	public $headers;
	
	function __construct() {
		$this->set_mode(PAGE_MODE_API);
	}
	
	function set_mode(int $mode) : void {
		$this->mode = $mode;
		
		switch ($mode) {
			case PAGE_MODE_API:
				$this->body = [];
				$this->type("application/json");
				$this->request = $this->get_json();
				break;
			
			case PAGE_MODE_RAW:
				$this->body = "";
				$this->type("text/plain");
				$this->request = null;
				break;
			
			case PAGE_MODE_HTML:
				$this->body = "";
				$this->type("text/html");
				$this->request = null;
				break;
		}
	}
	
	function http_header(string $key, string $value) : void {
		$this->headers[$key] = $value;
	}
	
	function send_headers() : void {
		foreach ($this->headers as $key => $value) {
			header("$key: $value");
		}
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
		$this->send();
	}
	
	function type(string $contenttype) : void {
		$this->http_header("Content-Type", $contenttype);
	}
	
	function allow_cache() : void {
		$this->http_header("Cache-Control", "max-age=86400");
	}
	
	function force_download(string $named) : void {
		$this->http_header("Content-Disposition", "attachment; filename=\"$named\"");
	}
	
	function info($title = "Done", $desc = "The action completed successfully.") : void {
		if ($this->mode != PAGE_MODE_API) {
			echo "$title\n\n$desc";
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
			$this->info("api_error", "Error: parameter '$key' is required.");
		}
		
		// Validate length
		if ($length && strlen($value) > $length) {
			if ($require) {
				$this->info("too_long", "The parameter '$key' is too long. The max length is $length characters.");
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
	
	function add(mixed $data) : void {
		if ($data instanceof Form) {
			$this->body .= $data->render();
		}
		else {
			$this->body .= $data;
		}
	}
	
	function heading(int $i, string $h) : void {
		$this->add("<h$i>$h</h$i>");
	}
	
	function para(string $h) : void {
		$this->add("<p>$h</p>");
	}
	
	private function render_json() : string {
		assert($this->mode === PAGE_MODE_API);
		
		return json_encode($this->body);
	}
	
	function render() : string {
		switch ($this->mode) {
			case PAGE_MODE_API:
				return $this->render_json();
				break;
			default:
				return $this->body;
				break;
		}
	}
	
	function send() : void {
		$this->send_headers();
		echo $this->render();
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
