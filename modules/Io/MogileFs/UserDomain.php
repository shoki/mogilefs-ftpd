<?php

class Io_MogileFs_UserDomain extends Io_MogileFs {
	public function connectMogile($tracker, $port, $domain, $timeout) {
		$domain = $this->client->getUser();
		return parent::connectMogile($tracker, $port, $domain, $timeout);
	}
}

?>
