<?php

class io_mogilefs_userdomain extends io_mogilefs {
	public function connectMogile($tracker, $port, $domain, $timeout) {
		$domain = $this->client->getUser();
		return parent::connectMogile($tracker, $port, $domain, $timeout);
	}
}

?>
