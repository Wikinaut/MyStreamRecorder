MyStreamRecorder
================

MyStreamRecorder is a free simple LINUX commandline-based stream recorder
and a scheduler with optional e-mail-notification when recording has finished.

It uses "mplayer" for recording and playing internet streams and has been developed and tested on OpenSuse 12.1.

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

# Requirements

see also file rec.php

* PHP 5.3.0+ (for getopt --long-options)

* "mplayer" for recording a stream

* server time synchronised via "ntp"
```
$rcntp start
```
or have "ntp" started in Runlevel 5; make sure to have a working ntp server defined in ntp configuration file

* "at" for starting and stopping stream recording
```
$ rcatd start
```
or have "at" started in Runlevel 5 (see http://www.simplehelp.net/2009/05/04/how-to-schedule-tasks-on-linux-using-the-at-command/)

* required system commands:
```
"atq" to list the queued "at" commands
"atrm" to remove queued "at" command
"fuser -k <fn>" to kill record and playback jobs which own streamfile <fn>
"postfix" (started) if you want to use the --mailto option for e-mail notifications after recording
```

# Usage
(output of rec.php --help)
<pre>
MyStreamRecorder -- usage:

   php rec.php

       [-n|--noplayback]
       [-p|--playonly]
       [-b|--beep]
       [-q|--quiet]
       [-h|--help]
       [-k|--killall] [-s|--stop]
       [-m<addr>|--mailto=<addr>]
       [-o<fn>|--output=<fn>]
       [-d<dir>|--directory=<dir>]
       [-v|--version]
       [-V|--verbose]

       <streamurl>|<stationname> [<starttime>|now] [<stoptime>|###min|###m|#.#hours|#.#h]

   Without times, stream recording or playing starts immediately and stops after 2.0 hours of recording.
   If only the starttime is present, stream recording or playing for 2.0 hours will start at startime.
   The default recording filename under /tmp comprises the station name or stream url and the start and stop times.

   Recording starts about 60 seconds before the start time and stops about 60 seconds after the stop time.
   Playback starts about 60 seconds after the recording (--noplayback disables playback while recording).
   --beep enables beep tones when recording starts or stops.
   --playonly disables recording and plays the stream now or at scheduled times
   --mailto=<addr> sends a mail when recording has finished to mailaddress <addr> (default: root@localhost)
   --quiet fully disables screen output
   --silent fully disables sounds while recording
   --output=<fn> user defined recording filename
   --directory=<dir> user defined working directory

   examples:

   php rec.php nova
   php rec.php --playonly dradio 10min
   php rec.php --noplayback kulturradio 20:00 21:30
   php rec.php -b dradio 201112312000 201112312200
   php rec.php http://radioeins.de/livemp3 01:00 2h
   php rec.php --directory=/tmp --output=Dradio-Wissen_News.ogg drwissen 30m
   php rec.php --mailto=mail@example.com dradio

   You may like to define convenience aliases such as

   # alias rec='php rec.php'
   # alias play='php rec.php -p'
   # alias stop='php rec.php -s'
   # alias playnova='php rec.php -p nova'

   (add the definitions to your /etc/bash.bashrc, /etc/bash.bashrc.local,
   /etc/profile.d/*.sh, /.bashrc, or ~/.alias file to make them permanent)

   for shortening the program calls to

   rec  dradio 1h
   play dradio 1h
   stop
   playnova

   pre-programmed stations:

   P##: station name and alternative names - station homepage

   P1 : dradiokultur deutschlandradiokultur dradio deutschlandradio dkultur drk dr - http://www.dradio.de/dkultur/
   P2 : kulturradio rbbkulturradio kulturradiorbb kradio kr rbb - http://www.kulturradio.de/
   P3 : deutschlandfunk dlf df - http://www.dradio.de/dlf/
   P4 : funkhauseuropa fhe wdrfunkhauseuropa - http://www.funkhaus-europa.de/
   P5 : radioeins radio1 rbbradioeins re r1 - http://www.radioeins.de/
   P6 : dradiowissen drwissen drw - http://wissen.dradio.de/
   P7 : deutschewelle dwelle dw - http://www.dwelle.de/
   P8 : multicult multicultfm multicult2.0 mc - http://www.multicult.fm/
   P9 : radionova novaradio novaplanet nova - http://www.novaplanet.com/
   P10: 90elf - http://www.90elf.de/
   P11: radio-b2-berlin-brandenburg b2 - http://www.radiob2.de/

   administrative information:

   sources to look up stream urls: http://tinyurl.com/de-internetradio http://www.radiosure.com/stations/
   using /usr/bin/mplayer for playing and stream recording
   /usr/bin/at task scheduler found and atd task scheduler daemon running with pid 1040
   recorded streams will be stored in the working directory /tmp
   MyStreamRecorder will generate a script /tmp/killall.sh which can be used for killing all scheduled actions
   (or type 'php rec.php -s').
</pre>
