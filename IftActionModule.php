<?php

abstract class IftActionModule extends PageAction implements Module {

	public static function getModuleInfo() {
		return array(
			'title' => 'Ift Action Module', 
			'summary' => 'Base class for Ift action modules.', 
			'version' => 1, 
			'author' => 'Avoine and ProcessWire', 
			'requires' => 'IftRunner',
			); 
	}

	/**
	 * Instance of IfAction
	 *
	 */
	protected $iftAction = null;

	/**
	 * Instance of HookEvent
	 *
	 */
	protected $hookEvent = null;

	public function getTrigger() {
		return $this->action ? $this->action->trigger : null; 
	}

	public function getIftAction() {
		return $this->iftAction;
	}

	public function setIftAction(IftAction $iftAction) {
		$this->iftAction = $iftAction;
	}

	public function getHookEvent() {
		return $this->hookEvent;
	}

	public function setHookEvent(HookEvent $hookEvent) {
		$this->hookEvent = $hookEvent; 
	}

}


