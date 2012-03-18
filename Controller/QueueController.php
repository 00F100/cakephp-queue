<?php
App::uses('QueueAppController', 'Queue.Controller');

class QueueController extends QueueAppController {

	public $uses = array('Queue.QueuedTask');

	public function admin_index() {
		$status = $this->_status();
		
		$current = $this->QueuedTask->getLength();
		$data = $this->QueuedTask->getStats();
		
		$this->set(compact('current', 'data', 'status'));
	} 
	
	public function admin_reset() {
		if (!$this->Common->isPosted()) {
			throw new MethodNotAllowedException();
		}
		$res = $this->Queue->truncate();
		die(returns($res));
	}
	
	protected function _status() {
		$file = TMP.'queue'.DS.'queue.pid';
		if (!file_exists($file)) {
			return null;
		}
		return filemtime($file);
		//return filectime($file);
	}
	
}

