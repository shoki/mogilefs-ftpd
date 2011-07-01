/usr/local/nanoftpd/log/nanoftpd.log {
  rotate 60
  daily
  compress
  missingok
  notifempty
  postrotate
	  invoke-rc.d nanoftpd restart > /dev/null
  endscript
}

