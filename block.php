<?php

require_once "database.php";

class BlockIP {
	/**
	 * A blocked IP address.
	 * 
	 * Does not support infinite blocking since IPs rotate.
	 */
	
	public $ip;
	public $expire;
	
	function __construct(string $ip) {
		$db = new Database("blockip");
		
		if ($db->has($ip)) {
			$info = $db->load($ip);
			
			$this->ip = $info->ip;
			$this->expire = $info->expire;
		}
		else {
			$this->ip = $ip;
			$this->expire = null;
		}
	}
	
	function save() {
		$db = new Database("blockip");
		$db->save($this->ip, $this);
	}
	
	function delete() : void {
		$db = new Database("blockip");
		
		if ($db->has($this->ip)) {
			$db->delete($this->ip);
		}
	}
	
	function set_block(?int $expire) : void {
		if ($expire == -1) {
			$expire = 7257600;
		}
		
		if ($expire) {
			// IP blocks are at most 3 months
			$this->expire = min(time() + $expire, time() + 7257600);
			$this->save();
		}
		else {
			$this->delete();
		}
	}
	
	function is_blocked() : bool {
		if ($this->expire) {
			if ($this->expire >= time()) {
				return true;
			}
			else {
				$this->delete();
				return false;
			}
		}
		else {
			return false;
		}
	}
}

function block_ip(string $ip, int $until) : void {
	/**
	 * Block an ip address
	 */
	
	$ip = new BlockIP($ip);
	$ip->set_block($until);
}

function is_ip_blocked(string $ip) : bool {
	/**
	 * Check if an ip is blocked
	 */
	
	$ip = new BlockIP($ip);
	return $ip->is_blocked();
}

class BlockUser {
	// An object containg a list of users that this user has blocked.
}
