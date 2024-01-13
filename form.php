<?php

define("FORM_CONTAINER_BLANK", 0);
define("FORM_CONTAINER_NORMAL", 1);

class Form {
	/**
	 * Makes a nice form
	 * 
	 * TODO Split the templates from the form function that so they can be used
	 * outside of forms if needed.
	 */
	
	public $body;
	public $container_type;
	
	function __construct(string $url, string $method = "post") {
		$method = htmlspecialchars($method);
		$url = htmlspecialchars($url);
		$this->body = "<form action=\"$url\" method=\"$method\" enctype=\"multipart/form-data\">";
		$this->container_type = FORM_CONTAINER_NORMAL;
	}
	
	function container(string $title, string $desc, string $data) {
		/**
		 * The basic container for everything else.
		 */
		
		$a = $this->body;
		
		switch ($this->container_type) {
			case FORM_CONTAINER_BLANK:
				$a .= "<div style=\"text-align: center; margin-top: 1.5em; margin-bottom: 1em;\">$data</div>";
				break;
			case FORM_CONTAINER_NORMAL: {
				$a .= "<div class=\"mod-edit-property\">";
					$a .= "<div class=\"mod-edit-property-label\">";
						// If there is no title there is little reason for a desc. as well.
						if ($title) {
							$a .= "<h4>$title</h4>";
							$a .= "<p>$desc</p>";
						}
					$a .= "</div>";
					$a .= "<div class=\"mod-edit-property-data\">";
						$a .= "<p>$data</p>";
					$a .= "</div>";
				$a .= "</div>";
				break;
			}
		}
		
		$this->body = $a;
	}
	
	function set_container_type(int $type) {
		$this->container_type = $type;
	}
	
	function textbox(string $name, string $title, string $desc, string $value = "", bool $enabled = true) {
		$s = ($enabled) ? "" : " readonly";
		$data = "<input class=\"form-control\" type=\"text\" name=\"$name\" placeholder=\"$title\" value=\"$value\" $s/>";
		
		$this->container($title, $desc, $data);
	}
	
	function password(string $name, string $title, string $desc, string $value = "", bool $enabled = true, bool $twice = false) {
		$s = ($enabled) ? "" : " readonly";
		$data = "<input class=\"form-control\" type=\"password\" name=\"$name\" placeholder=\"$title\" value=\"$value\" $s/>";
		
		if ($twice) {
			$data .= "<input class=\"form-control\" type=\"password\" name=\"$name"."2\" placeholder=\"$title (type again)\" value=\"$value\" $s/>";
		}
		
		$this->container($title, $desc, $data);
	}
	
	function textaera(string $name, string $title, string $desc, string $value = "", bool $enabled = true) {
		$s = ($enabled) ? "" : " readonly";
		$data = "<textarea class=\"form-control\" name=\"$name\" $s>$value</textarea>";
		
		$this->container($title, $desc, $data);
	}
	
	function select(string $name, string $title, string $desc, array $options, ?string $value = null, bool $enabled = true) {
		$data = "<select class=\"form-select form-control\" name=\"$name\">";
		$k = array_keys($options);
		
		for ($i = 0; $i < sizeof($k); $i++) {
			$key = $k[$i];
			$val = $options[$k[$i]];
			$selected = ($key == $value) ? "selected" : "";
			
			$data .= "<option value=\"$key\" $selected>$val</option>";
		}
		
		$data .= "</select>";
		
		$this->container($title, $desc, $data);
	}
	
	function hidden(string $name, string $value) {
		$this->body .= "<input name=\"$name\" type=\"hidden\" value=\"$value\" readonly/>";
	}
	
	function day(string $name, string $title, string $desc) {
		$data = "";
		
		// Year
		$data .= "<select name=\"$name-year\" style=\"width: 5em; margin-right: 0.5em;\">";
			$data .= "<option value=\"\" selected disabled hidden>Year</option>";
			for ($i = 2023; $i >= 1950; $i--) {
				$data .= "<option value=\"$i\">$i</option>";
			}
		$data .= "</select>";
		
		// Month
		$data .= "<select name=\"$name-month\" style=\"width: 8em; margin-right: 0.5em;\">";
			$data .= "<option value=\"\" selected disabled hidden>Month</option>";
			$data .= "<option value=\"1\">Janurary</option>";
			$data .= "<option value=\"2\">Feburary</option>";
			$data .= "<option value=\"3\">March</option>";
			$data .= "<option value=\"4\">April</option>";
			$data .= "<option value=\"5\">May</option>";
			$data .= "<option value=\"6\">June</option>";
			$data .= "<option value=\"7\">July</option>";
			$data .= "<option value=\"8\">August</option>";
			$data .= "<option value=\"9\">September</option>";
			$data .= "<option value=\"10\">October</option>";
			$data .= "<option value=\"11\">November</option>";
			$data .= "<option value=\"12\">December</option>";
		$data .= "</select>";
		
		// Day
		$data .= "<select name=\"$name-day\" style=\"width: 4em;\">";
			$data .= "<option value=\"\" selected disabled hidden>Day</option>";
			for ($i = 32; $i > 0; $i--) {
				$data .= "<option value=\"$i\">$i</option>";
			}
		$data .= "</select>";
		
		$this->container($title, $desc, $data);
	}
	
	function submit(string $text = "Continue") {
		$sak = user_get_sak();
		$data = "<input type=\"hidden\" name=\"key\" value=\"$sak\">";
		$data .= "<input class=\"btn btn-primary\" type=\"submit\" value=\"$text\"/>";
		
		$this->container("", "", $data);
		$this->body .= "</form>";
	}
	
	function upload(string $name, string $title, string $desc) {
		$data = "<input type=\"file\" name=\"$name\" />";
		
		$this->container($title, $desc, $data);
	}
	
	function render() {
		return $this->body;
	}
}
