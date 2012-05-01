MyStreamRecorder
================

MyStreamRecorder is a simple LINUX commandline-based stream recorder
and scheduler with e-mail-notification.

The basic usage is
```
php rec.php <streamname>
``` 
or
```
php rec.php --playonly <streamname>
```

See
```
php rec.php
```
for detailed help.

Requirements
============
see also file rec.php

* PHP 5.3.0+ (for getopt --long-options)
* mplayer for recording a stream

* exact server time synchronised via "ntp"
	$rcntp start
	or have ntp started in Runlevel 5
	make sure to have a working ntp server defined in ntp configuration file

* requires "at" for starting and stopping stream recording
	$ rcatd start
	or have at started in Runlevel 5
	http://www.simplehelp.net/2009/05/04/how-to-schedule-tasks-on-linux-using-the-at-command/

required system commands:
	atq list the queued "at" commands
	atrm remove queued "at" command
	fuser -k <fn> kill record and playback jobs which own streamfile <fn>
