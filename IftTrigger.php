<?php

/**
 * Class that holds information for an Ift Trigger
 *
 * @property int $id Database ID of the trigger
 * @property string $title Description of what the trigger does
 * @property string $hook Hook string, i.e. Pages::saveReady
 * @property string $condition Selector string of condition to match
 * @property array $fieldChanges array containing names of fields to check for changes (as optional part of the condition)
 * @property array $actions Array of PageAction module names to run if this trigger matches
 * @property array $settings Additional settings to pass along to the PageActino modules (indexed by module name)
 * @property int $flags Flags that indicate toggles for the IftTrigger (see flags* constants)
 * @proeprty int $sort Order that the trigger runs in, or is displayed in.
 * 
 */ 

class IftTrigger extends WireData {

	const flagsActive = 2;
	const flagsBefore = 4; 
	const flagsAfter = 8; 
	const flagsLater = 16; // action is queued rather than running immediately
	const flagsEmail = 512; // notify by email
	const flagsNotice = 1024; // notify by system notice

	public function __construct() { 

		// database id of the trigger
		$this->set('id', 0); 

		// what this trigger does
		$this->set('title', ''); 

		// hooks string, i.e. Pages::saveReady
		$this->set('hook', ''); 

		// selector string
		$this->set('condition', ''); 

		// module names
		$this->set('actions', array()); 

		// names of field that changed, if required as part of conditions
		$this->set('fieldChanges', array());

		// settings for actions modules, indexed by module name. Also stores the 'changes' property, when used.
		$this->set('settings', array()); 

		// see flags consts
		$this->set('flags', self::flagsActive | self::flagsAfter); 

		// order that it runs in (or is displayed in)
		$this->set('sort', 0); 
	}

	public function set($key, $value) {

		if($key == 'fieldChanges') {
			// convert string changes list of field names to array
			if(is_array($value)) {
				if(!trim(implode('', $value))) $value = array();
			} else {
				$value = explode(' ', $value); 	
			}
			// sanitize
			foreach($value as $k => $v) {
				$required = strpos($v, '+') === 0; 
				if($required) $v = ltrim($v, '+'); 
				$v = $this->wire('sanitizer')->fieldName($v); 
				if(empty($v)) continue; 
				$value[$k] = ($required ? '+' : '') . $v; 
			}
		}

		return parent::set($key, $value); 
	}

}

