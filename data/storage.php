<?php
/**
 * Site storage and management
 */

$gStoragePath = "../../data/storage";

class SiteStorage {
	public $path;
	
	function __construct(string $path) {
		$this->path = $path;
		
		if (!file_exists($this->path)) {
			mkdir($this->path, 0777, true);
		}
	}
	
	function get_real_path(string $item) : string {
		return $this->path . "/" . str_replace(".", "_", str_replace("/", "_", $item));
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

$gStorage = new SiteStorage($gStoragePath);
