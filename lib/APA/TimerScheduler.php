<?php

/* runs timers and returns time till next timers has to be run */
class APA_TimerScheduler {
	private static $timerhandle = 10;
	private static $timers = array();
	private static $instance;

	public static function &get() {
		if (!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function restartTimer($key, $runtime) {
		if ( $this->timers[$key]) {
			$this->timers[$key]->setRunTime($runtime);
			return true;
		}
		return false;
	}

	public function startTimer($runtime, $obj, $function, $args) {
		$this->timerhandle++;
		$this->timers[$this->timerhandle] = new APA_Timer($runtime, 
				$obj, $function, $args);
		return $this->timerhandle;
	}

	public function stopTimer($key) {
		if (isset($this->timers[$key])) {
			//echo("unset timer=$this->timers[$key] key=$key\n");
			unset($this->timers[$key]);
			return true;
		}
		return false;
	}

	public function timediff($time1, $time2, &$return = null) {
		if ($time1 < $time2) 
			/* don't output negative time */
			$diff = 0;
		else
			$diff = $time1 - $time2;

		if (isset($return)) $return = $diff;
		//echo("t1=$time1 t2=$time2 diff=$diff\n");
		return $diff;
	}

	/* run timers on time and return time till next timer has to start */
	public function runTimers() {
		$cur = time();
		$diff = $prevdiff = 0;

		if (!empty($this->timers)) {
			foreach ($this->timers as $key => $timer) {
				if ($this->timediff($timer->getRunTime(), $cur, $diff) <= 0) {
					$timer->run();
					$this->stopTimer($key);
				}
				if (!isset($prevdiff) || $diff < $prevdiff && $diff) 
					$prevdiff = $diff;
			}
		}
		$ret = $prevdiff ? $prevdiff : 1 ;
		return $ret;
	}

}

?>
