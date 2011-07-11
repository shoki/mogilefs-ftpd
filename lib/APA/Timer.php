<?php
/* timer structure */
class APA_Timer {
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
		return call_user_func_array(array($this->obj, $func), $this->args);
	}

	public function __destruct() {
	}
}


?>
