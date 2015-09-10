<?php

/**
 * Class to manage Ift Actions that are in the database
 *
 */
class IftActions extends Wire {

	/**
	 * Instance of IftRunner
	 *
	 */
	protected $runner = null;

	/**
	 * Construct the IftActions queue
	 *
	 * @param IftRunner $runner
	 *
	 */
	public function __construct($runner) {
		$this->setRunner($runner); 
	}

	/**
	 * Return a new IftAction() instance
	 *
	 * @return IftAction
	 *
	 */
	public function getNew() {
		return new IftAction();
	}

	/**
	 * Save and queue an IftAction to the database
	 *
	 * @param IftAction
	 * @return int Returns the database ID or 0 on failure
	 * @throws WireException
	 *
	 */
	public function save(IftAction $action) {

		if(!count($action->itemIDs)) throw new WireException("There are no items in this action"); 

		if(is_null($action->parentID) || is_null($action->rootParentID)) {
			throw new WireException("Please set the action parent and rootParent before saving. If no parent/rootParent, then set them to 0."); 
		}

		$sql = 	($action->id ? 'UPDATE' : 'INSERT INTO') . ' ift_actions SET ' . 
			'title=:title, parent_id=:parent_id, root_parent_id=:root_parent_id, ' . 
			'module=:module, trigger_id=:trigger_id, settings=:settings, ' . 
			'item_ids=:item_ids, user_id=:user_id, priority=:priority, ' . 
			'flags=:flags, created=NOW()';

		$query = $this->wire('database')->prepare($sql); 

		$query->bindValue(':title', $action->title); 
		$query->bindValue(':parent_id', (int) $action->parentID); 
		$query->bindValue(':root_parent_id', (int) $action->rootParentID); 
		$query->bindValue(':module', (string) $action->moduleName); 
		$query->bindValue(':trigger_id', (int) $action->triggerID);
		$query->bindValue(':item_ids', implode('|', $action->itemIDs));
		$query->bindValue(':user_id', $action->userID ? $action->userID : $this->wire('user')->id); 
		$query->bindValue(':settings', json_encode($action->settings)); 
		$query->bindValue(':priority', (int) $action->priority); 
		$query->bindValue(':flags', (int) $action->flags); 

		if($query->execute()) {
			if(!$action->id) $action->id = $this->wire('database')->lastInsertId();
			return $action->id; 
		}

		return 0;
	}

	/**
	 * Delete a queued IftAction from the database
	 *
	 * @param IftAction
	 * @return bool
	 *
	 */
	public function delete(IftAction $action) {
		$sql = 'DELETE FROM ift_actions WHERE id=:id';
		$query = $this->wire('database')->prepare($sql); 
		$query->bindValue(':id', $action->id);
		return $query->execute();
	}

	/**
	 * Get all Ift Actions queued in the database
	 *
	 * @param int $id Optionally retrieve just one (specify the ID) 
	 * @return array of IftAction items
	 *
	 */
	public function getAll($id = 0) {

		$sql = "SELECT * FROM ift_actions "; 
		if($id) $sql .= "WHERE id=:id ";
		$sql .= "ORDER BY priority DESC, created ASC";
		$query = $this->wire('database')->prepare($sql); 
		if($id) $query->bindValue(':id', $id); 
		$query->execute();

		$actions = array();

		while($row = $query->fetch(PDO::FETCH_ASSOC)) {
			$action = new IftAction();	
			$action->id = (int) $row['id']; 
			$action->title = $row['title'];
			$action->parentID = (int) $row['parent_id'];
			$action->rootParentID = (int) $row['root_parent_id'];
			$action->moduleName = $row['module'];
			$action->triggerID = (int) $row['trigger_id']; 
			$action->itemIDs = explode('|', $row['item_ids']); 
			$action->userID = (int) $row['user_id']; 
			$action->settings = json_decode($row['settings'], true); 
			$action->priority = (int) $row['priority'];
			$action->created = strtotime($row['created']); 
			$action->flags = (int) $row['flags'];
			$actions[] = $action;
		}

		$query->closeCursor();

		return $actions; 
	}

	/**
	 * Get a single IftAction
	 *
	 * @param int $id ID of IftAction to retrieve
	 * @return IftAction|null
	 *
	 */
	public function get($id) {
		$actions = $this->getAll((int) $id); 
		return reset($actions); 
	}

	/**
	 * Create the tables for the Ift Action Queue
	 *
	 */
	public function install() { 

		$this->database->exec("CREATE TABLE ift_actions (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, 
			`title` VARCHAR(255),
			`parent_id` INT UNSIGNED NOT NULL DEFAULT 0, 
			`root_parent_id` INT UNSIGNED NOT NULL DEFAULT 0,
			`trigger_id` INT UNSIGNED NOT NULL, 
			`user_id` INT UNSIGNED NOT NULL,
			`module` VARCHAR(128), 
			`item_ids` TEXT,
			`settings` TEXT, 
			`priority` INT UNSIGNED NOT NULL DEFAULT 0,
			`created` TIMESTAMP NOT NULL,
			`flags` INT UNSIGNED NOT NULL DEFAULT 0
			)"); 

		// @todo indexes for parent_id, root_parent_id, priority+created?
	}

	/**
	 * Drop the tables for the Ift Action Queue
	 *
	 */
	public function uninstall() {
		$this->database->exec("DROP TABLE ift_actions"); 
	}

	/**
	 * Check the current DB schema and update if needed
	 *
	 * To modify the schema, bump up the version number in IftRunner::SCHEMA_VERSION
	 *
	 */
	public function checkSchema() {

		$query = $this->database->prepare("SHOW TABLES LIKE 'ift_actions'"); 
		$result = $query->execute();

		// if there is no ift_actions table, create it
		if(!$query->rowCount()) return $this->install();

		$query = $this->database->prepare("SHOW COLUMNS FROM ift_actions LIKE 'flags'"); 
		$result = $query->execute();
		if(!$query->rowCount()) $this->database->exec("ALTER TABLE ift_actions ADD `flags` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `created`"); 

		$query = $this->database->prepare("SHOW COLUMNS FROM ift_actions LIKE 'title'"); 
		$result = $query->execute();
		if(!$query->rowCount()) $this->database->exec("ALTER TABLE ift_actions ADD `title` VARCHAR(255) AFTER `id`"); 

		$query = $this->database->prepare("SHOW COLUMNS FROM ift_actions LIKE 'parent_id'"); 		
		$result = $query->execute();
		if(!$query->rowCount()) {
			$this->database->exec("ALTER TABLE ift_actions ADD `root_parent_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id`"); 
			$this->database->exec("ALTER TABLE ift_actions ADD `parent_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id`"); 
		}
	}

	public function setRunner(Wire $runner) {
		$this->runner = $runner;
	}

		
}
