<?php

/**
 * Class to hold information for a live or queued action
 *
 * Native properties: 
 *
 * @property int $id Database ID of this IftAction (if queued)
 * @property int $parentID Database ID of the parent of this IftAction or 0 if no parent. 
 * @proeprty int $rootParentID Database ID of the root parent of this IftAction or 0 if it is the root parent.
 * @property IftTrigger $trigger Instance of the IftTrigger (if available)
 * @property int $triggerID Database ID of the IftTrigger
 * @property int $userID ID of the user that initiated the action
 * @property string $moduleName PageAction module name
 * @property array $itemIDs Page IDs of items to act upon
 * @property array $settings Additional settings to pass along to the PageAction
 * @property int $priority Priority of the action (higher number = higher priority)
 * @property int $created Unix timestamp of date/time created
 * @property int $flags Flags bitmask
 *
 * Runtime only properties:
 *
 * @property null|IftAction $parent Parent IftAction
 * @property null|IftAction $rootParent Root parent IftAction that is the first in a set of actions
 * @property PageArray $items Items for the action to act upon
 * @property PageAction $module PageAction module to run
 * @property User $user User that initiated the action
 * @property string $summary Text summary of what the action did
 *
 */

class IftAction extends WireData {

	/**
	 * Notify user by email when action is finished
	 *
	 */
	const flagsEmail = 512; 

	/**
	 * Notify user by session notices when action is finished (not yet implemented)
	 *
	 */
	const flagsNotice = 1024; 

	/**
	 * Instance of IftRunner
	 *
	 */
	protected $runner = null;

	/**
	 * Construct and set default property values
	 *
	 */
	public function __construct() {

		$this->set('id', 0); 
		$this->set('title', '');
		$this->set('parentID', null); 
		$this->set('rootParentID', null); 
		$this->set('triggerID', 0); 
		$this->set('userID', 0); 
		$this->set('moduleName', ''); 
		$this->set('itemIDs', array()); 
		$this->set('settings', array()); 
		$this->set('priority', 0); 
		$this->set('created', 0); 
		$this->set('flags', 0);

		// runtime only
		$this->set('completed', false); 
		$this->set('summary', ''); 

		$this->runner = $this->wire('modules')->get('IftRunner'); 
	}

	/**
	 * Get an IftAction property
	 *
	 */
	public function get($key) {

		if($key == 'items') {
			$itemIDs = parent::get('itemIDs'); 
			if(count($itemIDs)) $value = $this->wire('pages')->getById($itemIDs); 
				else $value = new PageArray();
			return $value; 

		} else if($key == 'module') {
			$moduleName = parent::get('moduleName'); 
			if($moduleName) {
				$value = $this->wire('modules')->get($moduleName); 
				if($value instanceof PageAction) return $value; 
			}

		} else if($key == 'user') {
			return $this->wire('users')->get((int) parent::get('userID')); 

		} else if($key == 'parent') {
			$parentID = parent::get('parentID'); 
			return $parentID ? $this->runner->actions->get($parentID) : null;

		} else if($key == 'rootParent') {
			$rootParentID = parent::get('rootParentID'); 
			return $rootParentID ? $this->runner->actions->get($rootParentID) : null;

		} else if($key == 'trigger') {
			$triggerID = parent::get('triggerID'); 
			return $triggerID ? $this->runner->triggers->get($triggerID) : null;
		}

		return parent::get($key); 
	}

	/**
	 * Set an IftAction property value
	 *
	 */
	public function set($key, $value) {

		if($key == 'items') {
			$itemIDs = array();
			foreach($value as $item) $itemIDs[] = $item->id; 
			return parent::set('itemIDs', $itemIDs); 

		} else if($key == 'trigger') {
			parent::set('triggerID', is_object($value) ? $value->id : 0); 

		} else if($key == 'module') {
			if(is_object($value) && $value instanceof PageAction) return parent::set('moduleName', $value->className()); 
			if(is_string($value)) $key == 'moduleName';

		} else if($key == 'user') {
			if($value instanceof User) return parent::set('userID', $value->id); 

		} else if($key == 'parent') {
			return parent::set('parentID', $value instanceof IftAction ? $value->id : 0); 

		} else if($key == 'rootParent') {
			return parent::set('rootParentID', $value instanceof IftAction ? $value->id : 0); 
		}
	
		return parent::set($key, $value); 
	}

	/**
	 * Get or set a setting
	 *
	 * In get mode, you specify only a key and you get the value of the setting. 
	 * In set mode, you specify both key and value and $this is returned. 
	 *
	 * @param string $key
	 * @param string|int|object|null $value
	 * @return mixed Returns $this if you set a setting, or returns the setting value if you are getting a setting. 
	 *	Returns null if a requested setting does not exist (in get mode)
	 *
	 */
	public function settings($key, $value = null) {
		$settings = parent::get('settings'); 
		if(is_null($value)) {
			$value = isset($settings[$key]) ? $settings[$key] : null;
			return $value; 
		}
		$settings[$key] = $value; 
		parent::set('settings', $settings); 
		return $this; 
	}

	/**
	 * Return a human-readable summary of this Action
	 *
	 * @return string
	 *
	 */
	public function getSummary() {

		$triggerName = $this->triggerID ? $this->trigger->title : $this->_('N/A');

		if($this->completed) $status = $this->_('Completed'); 
			else $status = $this->_('Pending'); 

		$out = 	$this->_('Title') . ': ' . $this->title . "\n" . 	
			$this->_('ID') . ': #' . $this->id . "\n" . 
			$this->_('Status') . ': ' . $status . "\n" . 
			$this->_('Items') . ': ' . count($this->itemIDs) . "\n" . 
			$this->_('Trigger') . ': ' . $triggerName . "\n" . 
			$this->_('Created') . ': ' . date('Y/m/d H:i', $this->created) . "\n" . 
			$this->_('Module') . ': ' . $this->moduleName . "\n" . 
			$this->_('User') . ': ' . $this->user->name;

		return $out; 
	}

	public function __toString() {
		return $this->getSummary();
	}

}
