<?php

class UserBlock {
	/**
	 * User block node
	 */
	
	public $node;
	public $from;
	public $to;
	
	function __construct(string $node) {
		$db = new Database("userblock");
		
		if ($db->has($node)) {
			$info = $db->load($node);
			
			$this->node = $info->node;
			$this->from = $info->from;
			$this->to = $info->to;
		}
		else {
			$this->node = $node;
			$this->from = array();
			$this->to = array();
		}
	}
	
	function save() : void {
		$db = new Database("userblock");
		$db->save($this->node, $this);
	}
	
	function has_from(string $name) : bool {
		return in_array($name, $this->from, true);
	}
	
	function add_from(string $name) : void {
		$this->from[] = $name;
	}
	
	function remove_from(string $name) : void {
		$i = array_search($name, $this->from, true);
		
		if ($i < 0) {
			return;
		}
		
		array_splice($this->from, $i, 1);
	}
	
	function has_to(string $name) : bool {
		return in_array($name, $this->to, true);
	}
	
	function add_to(string $name) : void {
		$this->to[] = $name;
	}
	
	function remove_to(string $name) : void {
		$i = array_search($name, $this->to, true);
		
		if ($i < 0) {
			return;
		}
		
		array_splice($this->to, $i, 1);
	}
}

function user_block_has(string $blocker, string $blockee, bool $from = true, bool $to = true) : bool {
	/**
	 * If a user has a block in any direction:
	 * user_block_has($blocker, $blockee);
	 * 
	 * To find if the blocker has blocked the blockee:
	 * user_block_has($blocker, $blockee, false);
	 */
	
	$blocker = new UserBlock($blocker);
	$blockee = new UserBlock($blockee);
	
	// Has the blocker blocked the possible blockee?
	$a = ($blockee->has_from($blocker->node) || $blocker->has_to($blockee->node)) && $to;
	
	// Has the blockee blocked the blocker?
	$b = ($blockee->has_to($blocker->node) || $blocker->has_from($blockee->node)) && $from;
	
	return $a || $b;
}

function user_block_add(string $blocker, string $blockee) : void {
	/**
	 * Add a block from blockee to blocker.
	 */
	
	$blocker = new UserBlock($blocker);
	$blockee = new UserBlock($blockee);
	
	$blocker->add_to($blockee->node);
	$blockee->add_from($blocker->node);
	
	$blocker->save();
	$blockee->save();
}

function user_block_remove(string $blocker, string $blockee) : void {
	/**
	 * Remove a block from blockee to blocker.
	 */
	
	$blocker = new UserBlock($blocker);
	$blockee = new UserBlock($blockee);
	
	$blocker->remove_to($blockee->node);
	$blockee->remove_from($blocker->node);
	
	$blocker->save();
	$blockee->save();
}

$gEndMan->add("account-toggle-block", function(Page $page) {
	$user = get_name_if_authed();
	
	if ($user) {
		$user = new User($user);
		
		$blockee = $page->get("handle");
		
		if (!$user->verify_sak($page->get("key"))) {
			$page->info("csrf attempt detected", "csrf security key is missing, so the action was not preformed.");
		}
		
		// If the block exists
		if (user_block_has($user->name, $blockee, false)) {
			user_block_remove($user->name, $blockee);
			$page->info("Block removed", "Your block on this user has been removed.");
		}
		// If the block does not exist
		else {
			user_block_add($user->name, $blockee);
			$page->info("Block added", "You have blocked this user.");
		}
	}
	else {
		$page->info("Please sign in", "You need to sign in to block other users.");
	}
});
