<?php

class EventManager {
	public $events;
	
	function __construct() {
		$events = [];
	}
	
	function add(string $name, $func) : void {
		/**
		 * Adds the function $func to handle an event by name $name.
		 */
		
		if (isset($this->events[$name])) {
			$this->events[$name][] = $func;
		}
		else {
			$this->events[$name] = [$func];
		}
	}
	
	function trigger(string $name, mixed $extra = null) : int {
		/**
		 * Calls each event on-trigger function. Returns number of triggers run.
		 * $extra is the first argument to the function.
		 */
		
		if (!isset($this->events[$name])) {
			return 0;
		}
		
		$triggers = $this->events[$name];
		$i = 0;
		
		for (; $i < sizeof($triggers); $i++) {
			$triggers[$i]($extra);
		}
		
		return $i;
	}
}

$gEvents = new EventManager();
