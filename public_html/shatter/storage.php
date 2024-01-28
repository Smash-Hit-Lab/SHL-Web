<?php
/**
 * Site storage and management
 */

$gStoragePath = "../../$gAppName/storage";

class SiteStorage {
	public $path;
	
	function __construct() {
		global $gStoragePath;
		
		$this->path = $gStoragePath;
		
		if (!file_exists($this->path)) {
			mkdir($this->path, 0777, true);
		}
	}
	
	function get_real_path(string $item) : string {
		return $this->path . "/" . str_replace("/", "", $item);
	}
	
	function load(string $item) : string {
		return file_get_contents($this->get_real_path($item));
	}
	
	function save(string $item, string $content) : void {
		file_put_contents($this->get_real_path($item), $content);
	}
	
	function has(string $item) : bool {
		return file_exists($this->get_real_path($item));
	}
	
	function delete(string $item) : void {
		unlink($this->get_real_path($item));
	}
}

$gStorage = new SiteStorage();
