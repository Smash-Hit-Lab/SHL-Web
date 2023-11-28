<?php

class Styles {
	/**
	 * Site CSS generator
	 * 
	 * "key": "value" pair gets to !(key) -> value
	 */
	
	public $base;
	public $vars;
	public $db;
	
	function __construct() {
		$this->base = file_get_contents("../../data/_styles.css");
		
		$this->db = new Database("site");
		
		if ($this->db->has("styles")) {
			$this->vars = (array) $this->db->load("styles")->vars;
		}
		else {
			$this->vars = [
				"PrimaryColour" => "#107cff",
				"PrimaryColour.Darker" => "#0b5ab4",
				"PrimaryColour.Hover" => "#0b5ab4",
				"PrimaryColour.Text" => "#ffffff",
				"LightBackground" => "#cde4ff",
				"LightBackground.Text" => "#000000",
				"Background" => "#ebf4ff",
				"Background.Text" => "#000000",
				"DarkBackground" => "#a5cfff",
				"DarkBackground.Text" => "#000000",
				"DarkBackground.TextHover" => "#000000",
				"Button.Glow.Offset" => "0.2em",
				"Button.Glow.Radius" => "0.4em",
				"NavBar.Radius" => "1.2em",
				"Font.Main" => "Titillium Web",
				"Font.Main.Escaped" => "Titillium+Web",
			];
		}
	}
	
	function save() : void {
		// We don't save the actual css contents
		$a = $this->base;
		unset($this->base);
		$this->db->save("styles", $this);
		$this->base = $a;
	}
	
	function get(string $key) : string {
		if (array_key_exists($key, $this->vars)) {
			return $this->vars[$key];
		}
		else {
			return "";
		}
	}
	
	function set(string $key, string $value) : void {
		$this->vars[$key] = $value;
	}
	
	function render() : string {
		$out = $this->base;
		
		// Do the variable replacements
		foreach ($this->vars as $key => $value) {
			$out = str_replace("!(" . $key . ")", $this->vars[$key], $out);
		}
		
		return $out;
	}
}

function site_styles_form(Page $page) {
	/**
	 * Creates the styles update form
	 */
	
	$s = new Styles();
	
	$page->global_header();
	$page->heading(1, "Site styles");
	
	$form = new Form("./?a=site-styles&submit=1");
	
	$form->container("About styles", "A note about this page.", "This page contains the raw variables (also called design tokens) used for the size, colour and positoning of elements. It is not really meant to be edited by hand, but it is provided so that you can customise your site in any way you like.");
	$form->textbox("PrimaryColour", "PrimaryColour", "", $s->get("PrimaryColour"));
	$form->textbox("PrimaryColour-Darker", "PrimaryColour.Darker", "", $s->get("PrimaryColour.Darker"));
	$form->textbox("PrimaryColour-Hover", "PrimaryColour.Hover", "", $s->get("PrimaryColour.Hover"));
	$form->textbox("PrimaryColour-Text", "PrimaryColour.Text", "", $s->get("PrimaryColour.Text"));
	$form->textbox("LightBackground", "LightBackground", "", $s->get("LightBackground"));
	$form->textbox("LightBackground-Text", "LightBackground.Text", "", $s->get("LightBackground.Text"));
	$form->textbox("Background", "Background", "", $s->get("Background"));
	$form->textbox("Background-Text", "Background.Text", "", $s->get("Background.Text"));
	$form->textbox("DarkBackground", "DarkBackground", "", $s->get("DarkBackground"));
	$form->textbox("DarkBackground-Text", "DarkBackground.Text", "", $s->get("DarkBackground.Text"));
	$form->textbox("DarkBackground-TextHover", "DarkBackground.TextHover", "", $s->get("DarkBackground.TextHover"));
	$form->textbox("Button-Glow-Offset", "Button.Glow.Offset", "", $s->get("Button.Glow.Offset"));
	$form->textbox("Button-Glow-Radius", "Button.Glow.Radius", "", $s->get("Button.Glow.Radius"));
	$form->textbox("NavBar-Radius", "NavBar.Radius", "", $s->get("NavBar.Radius"));
	$form->textbox("Font-Main", "Font.Main", "", $s->get("Font.Main"));
	
	$form->submit("Update styles");
	
	$page->add($form);
	
	$page->global_footer();
}

function site_styles_update(Page $page) {
	/**
	 * Saves styles
	 */
	
	$s = new Styles();
	
	$s->set("PrimaryColour", $page->get("PrimaryColour", true, 9));
	$s->set("PrimaryColour.Darker", $page->get("PrimaryColour-Darker", true, 9));
	$s->set("PrimaryColour.Hover", $page->get("PrimaryColour-Hover", true, 9));
	$s->set("PrimaryColour.Text", $page->get("PrimaryColour-Text", true, 9));
	$s->set("LightBackground", $page->get("LightBackground", true, 9));
	$s->set("LightBackground.Text", $page->get("LightBackground-Text", true, 9));
	$s->set("Background", $page->get("Background", true, 9));
	$s->set("Background.Text", $page->get("Background-Text", true, 9));
	$s->set("DarkBackground", $page->get("DarkBackground", true, 9));
	$s->set("DarkBackground.Text", $page->get("DarkBackground-Text", true, 9));
	$s->set("Button.Glow.Offset", $page->get("Button-Glow-Offset", true, 9));
	$s->set("Button.Glow.Radius", $page->get("Button-Glow-Radius", true, 9));
	$s->set("NavBar.Radius", $page->get("NavBar-Radius", true, 9));
	
	$font = $page->get("Font-Main", true, 100);
	$s->set("Font.Main", $font);
	$s->set("Font.Main.Escaped", str_replace(" ", "+", $font));
	
	$s->save();
	
	$page->info("Styles saved", "The site styles were updated successfully! You might have to clear your browser's cache in order to see the changes, though.");
}

$gEndMan->add("site-styles", function(Page $page) {
	$user = get_name_if_admin_authed();
	
	if ($user) {
		$submitting = $page->get("submit", false);
		
		if ($submitting) {
			site_styles_update($page);
		}
		else {
			site_styles_form($page);
		}
	}
	else {
		$page->info("Sorry", "The action you have requested is not currently implemented.");
	}
});

$gEndMan->add("site-css", function(Page $page) {
	/**
	 * Render and serve the css file
	 */
	
	$s = new Styles();
	
	$page->type("text/css");
	$page->allow_cache();
	$page->set_mode(PAGE_MODE_RAW);
	$page->add($s->render());
	$page->send();
});

$gEndMan->add("site-js", function(Page $page) {
	/**
	 * Render and serve the js file
	 */
	
	$page->type("text/javascript");
	$page->allow_cache();
	$page->set_mode(PAGE_MODE_RAW);
	$page->add(file_get_contents("../../data/_app.js"));
	$page->send();
});

$gEndMan->add("generate-logo-coloured", function(Page $page) {
	$cb = str_split(md5($page->get("seed")), 6);
	
	$bg = $cb[0];
	$fg = $cb[1];
	
	$page->type("image/svg+xml");
	$page->allow_cache();
	$page->add("<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>
<svg
   width=\"256\"
   height=\"256\"
   viewBox=\"0 0 67.733332 67.733335\"
   version=\"1.1\"
   id=\"svg5\"
   xmlns=\"http://www.w3.org/2000/svg\"
   xmlns:svg=\"http://www.w3.org/2000/svg\">
  <defs
     id=\"defs2\" />
  <g
     id=\"layer1\">
    <rect
       style=\"fill:#$bg;stroke-width:0.264583\"
       id=\"rect163\"
       width=\"67.73333\"
       height=\"67.73333\"
       x=\"0\"
       y=\"0\"
       ry=\"0\" />
    <path
       id=\"path1506\"
       style=\"fill:#$fg;stroke:none;stroke-width:0.112875px;stroke-linecap:butt;stroke-linejoin:miter;stroke-opacity:1\"
       d=\"M 33.866665,4.9708017 4.970501,33.866965 33.866665,62.762531 62.762829,33.866965 Z\" />
    <path
       id=\"path226\"
       style=\"fill:#$bg;stroke:none;stroke-width:0.0798143px;stroke-linecap:butt;stroke-linejoin:miter;stroke-opacity:1\"
       d=\"M 48.314708,19.418624 H 19.418622 l 2.99e-4,28.895785 28.895787,3e-4 z\" />
    <path
       id=\"path232\"
       style=\"fill:#$fg;stroke:none;stroke-width:0.0564364px;stroke-linecap:butt;stroke-linejoin:miter;stroke-opacity:1\"
       d=\"M 33.866665,19.418971 19.41882,33.866816 33.866665,48.314362 48.31451,33.866816 Z\" />
  </g>
</svg>");
	$page->set_mode(PAGE_MODE_RAW);
	$page->send();
});
