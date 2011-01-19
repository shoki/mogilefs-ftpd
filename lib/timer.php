<?php
/* timer structure */
class Timer {
	private $runtime;
	private $function;
	private $obj;
	private $args;

	public function __construct($runtime, $obj, $function, $args) {
		$this->function = $function;
		$this->obj = $obj;
		$this->args = $args;
		$this->setRunTime($runtime);
	}

	public function setRunTime($runtime) {
		$this->runtime = time() + $runtime;
	}

	public function getRunTime() {
		return $this->runtime;
	}

	public function run() {
		$func = $this->function;
		$this->obj->$func($this->args);
	}

	public function __destruct() {
	}
}


?>
