<?php

/**
 * Class that manages Ift Triggers in the database
 *
 */
class IftTriggers extends Wire {

	/**
	 * @property IftRunner
	 *
	 */
	protected $runner = null;

	/**
	 * Other trigger properties that use DB settings property as storage engine only
	 *
	 * These properties don't exist in the runtime $trigger->settings
	 *
	 * @var array
	 *
	 */
	protected $storeInSettings = array(
		'fieldChanges',
		);

	/**
	 * Construct IftTriggers
	 *
	 * @param IftRunner $runner
	 *
	 */
	public function __construct(IftRunner $runner) {
		$this->runner = $runner; 
	}

	/**
	 * Get all triggers from the database
	 *
	 * @param int $id Optionally specify an ID to retrieve just one
	 * @return array
	 *
	 */
	public function getAll($id = 0) {

		$triggers = array();
		$sql = 	"SELECT * FROM ift_triggers ";
		if($id) $sql .= "WHERE id=:id ";
		$sql .= "ORDER BY sort";

		try { 
			$query = $this->wire('database')->prepare($sql); 
			if($id) $query->bindValue(':id', (int) $id); 
			$query->execute();

		} catch(Exception $e) {
			$this->error($e->getMessage()); 
			return $triggers;
		}

		while($row = $query->fetch(PDO::FETCH_ASSOC)) {
			$trigger = new IftTrigger(); 
			foreach($row as $key => $value) {
				if($key == 'actions') $value = explode(',', $value); 
				if($key == 'settings') {
					$value = strlen($value) ? json_decode($value, true) : array();
					// convert some settings properties to their own trigger properties and remove from settings
					foreach($this->storeInSettings as $property) {
						if(!isset($value[$property])) continue; 
						$trigger->set($property, $value[$property]); 
						unset($value[$property]); 
					}
				}
				$trigger->set($key, $value); 
			}
			$triggers[] = $trigger; 
		}

		return $triggers; 
	}

	/**
	 * Get a single IftTrigger from the database
	 *
	 * @param int $id ID of the IftTrigger to return
	 * @return array|null
	 *
	 */
	public function get($id) {
		$triggers = $this->getAll($id); 
		return count($triggers) ? $triggers[0] : null;
	}

	/**
	 * Create a new IftTrigger instance
	 *
	 * @return IftTrigger
	 *
	 */
	public function getNew() {
		return new IftTrigger(); 
	}

	/**
	 * Return all IftAction instances for the given trigger
	 *
	 * @param IftTrigger $trigger
	 * @return array of IftAction instances
	 *
	 */
	public function getActions(IftTrigger $trigger) {

		$actions = array();

		foreach($trigger->actions as $moduleName) {

			$action = $this->runner->actions->getNew();
			$action->moduleName = $moduleName; 
			$action->trigger = $trigger; 

			// populate any extra config data to module, if specified
			if(isset($trigger->settings[$moduleName])) {
				$action->settings = $trigger->settings[$moduleName]; 
			}

			$actions[] = $action; 
		}

		return $actions;
	}

	/**
	 * Save the given IftTrigger to database
	 *
	 * @param IftTrigger $trigger
	 * @return int Returns the trigger ID on success or 0 on failure
	 *
	 */
	public function save(IftTrigger $trigger) {
		$sql = ($trigger->id ? "UPDATE" : "INSERT INTO") . " ift_triggers SET ";
		$sql .= "`title`=:title, `flags`=:flags, `sort`=:sort, `hook`=:hook, " . 
			"`condition`=:condition, `actions`=:actions, `settings`=:settings ";
		if($trigger->id) $sql .= "WHERE id=:id";
		$query = $this->wire('database')->prepare($sql); 
		$query->bindValue(':title', $trigger->title); 
		$query->bindValue(':flags', $trigger->flags); 
		$query->bindValue(':sort', $trigger->sort); 
		$query->bindValue(':hook', $trigger->hook); 
		$query->bindValue(':condition', $trigger->condition); 
		$query->bindValue(':actions', implode(',', $trigger->actions)); 

		$settings = $trigger->settings; 
		// some trigger properties also use settings for their storage only
		foreach($this->storeInSettings as $property) {
			$value = $trigger->$property; 
			if(empty($value)) continue; 
			$settings[$property] = $value; // bundle into settings
		}
		$query->bindValue(':settings', count($settings) ? json_encode($settings) : ''); 

		if($trigger->id) $query->bindValue(':id', $trigger->id); 
		$result = $query->execute();
		if(!$trigger->id) $trigger->id = $this->wire('database')->lastInsertId();

		return $trigger->id;
	}

	/**
	 * Delete the given IftTrigger from the database
	 *
	 * @param IftTrigger $trigger
	 * @return bool
	 *
	 */
	public function delete(IftTrigger $trigger) {
		$sql = "DELETE FROM ift_triggers WHERE id=:id";
		$query = $this->wire('database')->prepare($sql); 
		$query->bindValue(':id', $trigger->id); 
		$trigger->flags = 0;
		return $query->execute();
	}

	/**
	 * Install tables used by triggers
	 *
	 */
	public function install() {

		$query = $this->database->prepare("SHOW TABLES LIKE 'ift_triggers'"); 
		$result = $query->execute();
		if($query->rowCount()) return;

		// create required database tables
		$this->database->exec("CREATE TABLE ift_triggers (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, 
			`title` varchar(255) NOT NULL default '', 
			`flags` TINYINT NOT NULL DEFAULT 0,
			`sort` TINYINT NOT NULL DEFAULT 0,
			`hook` VARCHAR(128) NOT NULL,
			`condition` VARCHAR(256) NOT NULL,
			`actions` VARCHAR(256) NOT NULL,
			`settings` TEXT
			)");
	}

	/**
	 * Check the current DB schema and update if needed
	 *
	 */
	public function checkSchema() {
	}

	/**
	 * Drop tables used by triggers
	 *
	 */
	public function uninstall() {
		$this->wire('database')->exec("DROP TABLE ift_triggers"); 
	}

}
