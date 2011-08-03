<?php

class Io_MogileFs_UserDomain extends Io_MogileFs {
	public function __construct($client) {
		parent::__construct($client);
		$this->currentDomain = $this->client->getUser();
	}
}

?>
