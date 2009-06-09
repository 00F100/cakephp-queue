<?php

/**
 * @author MGriesbach@gmail.com
 * @package QueuePlugin
 * @subpackage QueuePlugin.Shells
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link http://github.com/MSeven/cakephp_queue
 */
class queueShell extends Shell {
	public $uses = array(
		'Queue.QueuedTask'
	);
	/**
	 * Codecomplete Hint
	 *
	 * @var QueuedTask
	 */
	public $QueuedTask;

	/**
	 * Overwrite shell initialize to dynamically load all Queue Related Tasks.
	 */
	public function initialize() {
		App::import('Folder');
		$this->_loadModels();

		foreach ($this->Dispatch->shellPaths as $path) {
			$folder = new Folder($path . DS . 'tasks');
			$this->tasks = array_merge($this->tasks, $folder->find('queue_.*\.php'));
		}
		// strip the extension fom the found task(file)s
		foreach ($this->tasks as &$task) {
			$task = basename($task, '.php');
		}
		// default configuration vars.
		Configure::write('queue', array(
			'sleeptime' => 10,
			'gcprop' => 10
		));
		//Config can be overwritten via local app config.
		Configure::load('queue');
		debug('hello');
	}

	/**
	 * Output some basic usage Info.
	 */
	public function help() {
		$this->out('CakePHP Queue Plugin:');
		$this->hr();
		$this->out('Usage:');
		$this->out('	cake queue help');
		$this->out('		-> Display this Help message');
		$this->out('	cake queue add <taskname>');
		$this->out('		-> Try to call the cli add() function on a task');
		$this->out('		-> tasks may or may not provide this functionality.');
		$this->out('	cake queue runworker');
		$this->out('		-> run a queue worker, which will look for a pending task it can execute.');
		$this->out('		-> the worker will always try to find jobs matching its installed Tasks');
		$this->out('		-> see "Available Tasks" below.');
		$this->out('Notes:');
		$this->out('	<taskname> may either be the complete classname (eg. queue_example)');
		$this->out('	or the shorthand without the leading "queue_" (eg. example)');
		$this->out('Available Tasks:');
		foreach ($this->taskNames as $loadedTask) {
			$this->out('	->' . $loadedTask);
		}
	}

	/**
	 * Look for a Queue Task of hte passed name and try to call add() on it.
	 * A QueueTask may provide an add function to enable the user to create new jobs via commandline.
	 *
	 */
	public function add() {
		if (count($this->args) != 1) {
			$this->out('Please call like this:');
			$this->out('       cake queue add <taskname>');
		} else {

			if (in_array($this->args[0], $this->taskNames)) {
				$this->{$this->args[0]}->add();
			} elseif (in_array('queue_' . $this->args[0], $this->taskNames)) {
				$this->{'queue_' . $this->args[0]}->add();
			} else {
				$this->out('Error: Task not Found: ' . $this->args[0]);
				$this->out('Available Tasks:');
				foreach ($this->taskNames as $loadedTask) {
					$this->out(' * ' . $loadedTask);
				}
			}
		}
	}

	/**
	 * Run a QueueWorker loop.
	 * Runs a Queue Worker process which will try to find unassigned jobs in the queue
	 * which it may run and try to fetch and execute them.
	 */
	public function runworker() {
		while (true) {
			$this->out('Looking for Job....');
			$data = $this->QueuedTask->requestJob($this->tasks);
			if ($data != false) {
				$this->out('Running Job of type "' . $data['jobtype'] . '"');
				$taskname = 'queue_' . strtolower($data['jobtype']);
				$return = $this->{$taskname}->run(unserialize($data['data']));
				if ($return == true) {
					$this->QueuedTask->markJobDone($data['id']);
					$this->out('Job Finished.');
				} else {
					$this->QueuedTask->markJobFailed($data['id']);
					$this->out('Job did not finish, requeued.');
				}
			} else {
				$this->out('nothing to do, sleeping.');
				sleep(Configure::read('queue.sleeptime'));
			}

			if (rand(0, 100) > (100 - Configure::read('queue.gcprop'))) {
				$this->out('Performing Old job cleanup.');
				$this->QueuedTask->cleanOldJobs();
			}
			$this->hr();
		}
	}
}
?>