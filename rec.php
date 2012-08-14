<?PHP
define( "VERSION", "v2.32 20120814" );
define( "PROGRAM_NAME", "MyStreamRecorder" );

/***
 *
 *	My Stream Recorder
 *
 *	developed and tested with OpenSuse 12.1
 *
 *	records and/or plays a stream from starttime to stoptime
 *	playback while recording is default (disable with --noplayback)
 *
 *	starting now or at a given starttime
 *	for a given recording time or until a given stoptime
 *
 *	pre-programmed stationnames or streamurls
 *	creates a batch file for killing all scheduled jobs
 *
 *	licensed under the MIT and GPL and CC-BY licenses:
 *	http://www.opensource.org/licenses/mit-license.php
 *	http://www.gnu.org/licenses/gpl.html
 *
 *	The software and documentation is licensed under:
 *
 *	Creative Commons Attribution 3.0 Unported License
 *	CC-BY http://creativecommons.org/licenses/by/3.0/
 *	MIT/X11 http://www.opensource.org/licenses/mit-license.php
 *	GPL http://www.gnu.org/licenses/gpl.html
 *
 *	License for the sound file included in this distribution:
 *
 *	https://commons.wikimedia.org/wiki/File:Quindar_tones.ogg
 *      (c) by Benscripps (Own work)
 *	CC-BY-SA-3.0 (www.creativecommons.org/licenses/by-sa/3.0) or
 *	GFDL (www.gnu.org/copyleft/fdl.html)], via Wikimedia Commons
 *
 *	Copyright 2011-2012 (c) Thomas Gries
 *
 * 	20110927	initial version
 * 	20110929	added option switch --noplayback
 * 	20111001	added --record option (default: play)
 * 	20111009	refactored the playback and using an "immediate" flag
 * 	20111010 1.63	added test for the pre-requisites
 * 	20111012 1.68	added time adjustments re. the next 24 hours
 * 	20111013 1.69	killSingleJob
 * 	20111015 2.00	integrated mailer for sending a mail when recording has finished
 * 	20111027 2.09	-q quiet = no sounds; -V = verbose
 * 	20111120 2.10	audiodriver alsa ( -ao alsa); before it was -ao oss
 *      20120501 2.11   added license information
 * 	20120504 2.12	added info to start postfix
 *      20120716 2.13   added Radio B2
 *	20120729 2.14	let sendmail use the actual send date
 *	20120730 2.15	fix to remove some spurious cron output
 *	20120731 2.16	adding label option to add a short description
 *			sanitize filenames to prevent path traversal
 *			baseurl option for web link to recorded file in notification e-mails
 *	20120801 2.18	added server info to mails
 * 	20120805 2.30	redesigned the handling of settings: there are now ini files (JSON)
 *			default settings rec.ini
 *			personal settings .rec.ini
 *	20120805 2.31	search path for global and personal ini files (see $iniFilePathnames)
 *	20120814 2.32	show search path
 *
 *	requires	PHP 5.3.0+ (for getopt --long-options)
 * 	requires	mplayer for recording a stream
 * 	requires	exact server time synchronised via "ntp"
 * 			$rcntp start
 * 			or have ntp started in Runlevel 5
 * 			make sure to have a working ntp server defined in ntp configuration file
 *  	requires	"at" for starting and stopping stream recording
 *			$ rcatd start
 *                      or have at started in Runlevel 5
 * 			http://www.simplehelp.net/2009/05/04/how-to-schedule-tasks-on-linux-using-the-at-command/
 *	requires	postfix must be running (check with "postfix status") if you want to use the e-mail notification
 *
 * 	required system commands:
 *
 * 	atq		list the queued "at" commands
 * 	atrm		remove queued "at" command
 * 	fuser -k <fn>	kill record and playback jobs which own streamfile <fn>
 *
 * 	TODO / FIXME
 *
 * 	- playback while recording: check if stream exists then play it immediately
 * 	- when --directory was given for scheduling a recording, the killall file cannot be accessed with php rec.php -s (because the directory is missing there!
 * 	- check working directory access and execution permissions for files
 *	- check if postfix is running
 *	- check available disk space before scheduling and show or send a warning
 *	- transcode ogg to mp3 (or vice versa) after recording
 *	+ parameter files stationlist and program defaults
 *
***/

$error = false;
function error( $text, $type = false ) {
	global $error;
	$type = ( !$type ) ? "Error" : "Info";
	$newLine = ( $error ) ? "\n" : "";
	$error .= $newLine . PROGRAM_NAME . " --$type: $text\n";
}

# check the pre-requisites
#
$out = array();
exec( "which mplayer 2>/dev/null", $out );
if ( !isset( $out[0] ) ) {
	error ("\"mplayer\" is required for playing and recording streams but cannot be found on this system.
On OpenSuse use YaST to install this program." );
	$mplayer = false;
} else {
	$mplayer = $out[0];
}

$out = array();
exec( "which at 2>/dev/null", $out );
if ( !isset( $out[0] ) ) {
	error( "\"at\" task manager is required to start and stop scheduled jobs but cannot be found on this system.
On OpenSuse use YaST to install this program." );
	$at = false;
} else {
	$at = $out[0];
}

$out = array();
exec( "pidof atd 2>/dev/null", $out );
if ( !isset( $out[0] ) ) {
	error( "\"atd\" task manager daemon is required to be running but is not yet started on this system.
On OpenSuse run \"# rcatd start\" and/or use YaST to activate atd for runlevel 2, 3, 5." );
	$atdPid = false;
} else {
	$atdPid = $out[0];
}

# mail recipient default address for finished recording mails
$defaultMailto = "root@localhost";

# search path for ini files. Content of last files take precedence.
$iniFilePathnames = array(
		"/etc/mystreamrecorder/rec.ini",
		"/etc/rec.ini",
		getenv( "HOME" ) . "/mystreamrecorder/rec.ini",
		getenv( "HOME" ) . "/rec.ini",
		dirname( __FILE__ ) . "/rec.ini",
		"/etc/mystreamrecorder/.rec.ini",
		"/etc/.rec.ini",
		getenv( "HOME" ) . "/mystreamrecorder/.rec.ini",
		getenv( "HOME" ) . "/.rec.ini",
		dirname( __FILE__ ) . "/.rec.ini",
	);

# sounds can be played when recording starts or stops
# this can be enabled with -b or --beep option
#
# License for the sound file
# https://commons.wikimedia.org/wiki/File:Quindar_tones.ogg by Benscripps (Own work)
# CC-BY-SA-3.0 (www.creativecommons.org/licenses/by-sa/3.0) or GFDL (www.gnu.org/copyleft/fdl.html)], via Wikimedia Commons
$startMessageSound = "Quindar_tones.ogg";

$stopMessageSound = $startMessageSound;

$atDateformat = "H:i Y-m-d";
$standardFormat = "YmdHi";

# seconds of recording before StartTime
$preRecordingTime = 60;

# seconds of recording after stopTime "Nachlauf", "outro"
$postRecordingTime = 60;

# default recording time when no stop time is given
$defaultRecordingTime = 2*3600;

# playback of the stream file can only start after the recording has started
#
# set to 0 if you want to use a second internet stream for playing instead of playing from file
# set to negative value like -60 if you want to use a second internet stream for playing instead of playing from file
# and start this time earlier than the start time. Remember that "at" scheduler's schedules at full minute, not seconds
# $playbackStartDelay = -$preRecordingTime;
$playbackStartDelay = $preRecordingTime;
# $playbackStartDelay = 0;

# a grace period for what is regarded as "now"
# everything in the period now +/- nowGracePeriod is regarded as "start now=immediately"
$nowGracePeriod = 2*60;

$now = time();
$immediateStart = false;
$startTime = $now;

function wfQuotedPrintable( $string ) {
	# Probably incomplete; see RFC 2045
	// $charset = "ISO-8859-1";
	$charset = "UTF-8";
	$charset = strtoupper( $charset );
	$charset = str_replace( 'ISO-8859', 'ISO8859', $charset ); // ?

	$illegal = '\x00-\x08\x0b\x0c\x0e-\x1f\x7f-\xff=';
	$replace = $illegal . '\t ?_';
	if( !preg_match( "/[$illegal]/", $string ) ) return $string;
	$out = "=?$charset?Q?";
	$out .= preg_replace( "/([$replace])/e", 'sprintf("=%02X",ord("$1"))', $string );
	$out .= '?=';
	return $out;
}

function prepareMail( $dest = "root@localhost", $text = "" , $from = "<no-reply@possible>" ) {
	global $shortName,$fileName,$baseUrl;

	$serverInfo = $_SERVER['USER'] . "@" . gethostname() .":" . $_SERVER['PHP_SELF'] . " " . VERSION;
	$webAccess = ( $baseUrl === "" ) ? "" : "
web access:
{$baseUrl}/{$shortName}
";
	$subject = "$shortName successfully recorded";
	$body = "Recording of $fileName has finished.

$text

Your friendly ".PROGRAM_NAME."
$serverInfo

local path:
$fileName
$webAccess
filesize in bytes:";

//	$headers = "Date: " . date("r") . "\n" .
	$headers = "MIME-Version: 1.0\n" .
		"Content-type: text/plain; charset=UTF-8\n" .
		"Content-Transfer-Encoding: 8bit\n" .
		"X-Mailer: " . PROGRAM_NAME . " " . VERSION . "\n" .
		"To: $dest\n" .
		"From: $from\n" .
		"Subject: " . wfQuotedPrintable( $subject ) . "\n";

	return $headers . $body;
}

function adjustTime( $time ) {
	global $nowGracePeriod,$now;
	$oneDay = 24 * 3600;
	if ( ( $time < ( $now - $nowGracePeriod ) ) && ( $time >= ( $now - $oneDay ) ) ) {
		$ret = $time + $oneDay;
	} else {
		$ret = $time;
	}
return $ret;
}

function changeTimeToDuration( $time ) {
	switch ( true ) {
	case ( preg_match( "!(\d+.?\d*)\s*(h|hour|hours)!", $time, $res ) ):
		$ret = 3600 * $res[1];
		break;
	case ( preg_match( "!(\d{1,3})\s*(m|min|minutes?)!", $time, $res ) ):
		$ret = 60 * $res[1];
		break;
	default: // treat as regular time
		$ret = false;
	};
	return $ret;
}

function isPreprogrammedStation( $name, $haystack ) {
	foreach( $haystack as $k ) {
		if ( in_array( strtolower($name), $k["station"] ) ) {
			return $k;
		}
	}
	return false;
}

# proper sanitizing filenames is a hard job.
# http://stackoverflow.com/questions/2668854/sanitizing-strings-to-make-them-url-and-filename-safe
# we use a whitelist for the moment and remove anything else
function sanitize_filename( $fn ) {
	return preg_replace( "![^a-z0-9 _-]!i", "", $fn );
}

$settings = array();
$iniFileFound = false;

$iniFiles = "";
foreach ( $iniFilePathnames as $iniFile ) {
	if ( file_exists( $iniFile ) ) {
		$iniFileFound = true;
		$iniFiles .= "   (+)";
		$settings = array_merge_recursive( $settings, json_decode( file_get_contents( $iniFile ), true ) );
	} else {
		$iniFiles .= "   (-)";
	};
	$iniFiles .= " $iniFile\n";
}

if ( !$iniFileFound ) {
	error( "No inifile found." );
}

// print_r( $settings );
// die();

$stations = $settings["stations"];

# dynamically assign additional programm numbers such as "P1" to the preprogrammed station names
# the p numbers are stored in lowercase, printed in uppercase
$i = 1;
foreach( $stations as $k => $v ) {
	array_unshift( $stations[$k]["station"], "p" . $i++ );
}

$streamUrl = "";
$defaultRecordingTimeHours = sprintf( "%1.1f", $defaultRecordingTime/3600 );

# save the commandline for printing it in the delete job file
$cmdLine = "";
foreach( $argv as $i => $v ) {
	$cmdLine .= "$v ";
}
$cmdLine = trim( $cmdLine );

# process options
#
# http://en.php.net/manual/de/function.getopt.php#100573
# Here's another way of removing options found by getopt() from the argv[] array.
# It handles the different kind of parameters without eating chunks that do not belong to an --option. (-nr foo param1 param2 foo)

$parameters = array(
	"p" => "playonly",
	"n" => "noplayback",
	"b" => "beep",
	"h" => "help",
	"q" => "quiet",
	"k" => "killall",
	"l:" => "label:",
	"s" => "stop",
	"m::" => "mailto::",
	"o:" => "output:",
	"d:" => "directory:",
	"u:" => "baseurl:",
	"v" => "version",
	"V" => "verbose",
);

$options = getopt( implode( '', array_keys( $parameters ) ), $parameters );

$pruneargv = array();
foreach ( $options as $option => $value ) {
	foreach ( $argv as $key => $chunk ) {
		$regex = '/^'. ( isset($option[1]) ? '--' : '-' ) . $option . '/';
		if ( $chunk == $value && $argv[$key-1][0] == '-' || preg_match( $regex, $chunk ) ) {
			array_push( $pruneargv, $key );
		}
	}
}
while ( $key = array_pop( $pruneargv ) ) unset( $argv[$key] );
# use array_merge function to reindex the array's keys
$argv = array_merge( $argv );
$argc = sizeof( $argv );

switch ( true ) {
case ( !empty( $options["o"] ) ):
	$fn = $options["o"];
	break;
case ( !empty( $options["output"] ) ):
	$fn = $options["output"];
	break;
default:
	$fn = false;
}

# baseUrl (server base address) for webaccess of recorded files in mails
switch ( true ) {
case ( !empty( $options["u"] ) ):
	$baseUrl = $options["u"];
	break;
case ( !empty( $options["baseurl"] ) ):
	$baseUrl = $options["baseurl"];
	break;
default:
	$baseUrl = "";
}
if ( $baseUrl !== "" ) {
	$baseUrl = trim( $baseUrl, " /." );
	if ( !preg_match( "!(https?|ftp)://!i", $baseUrl ) ) {
		$baseUrl = "http://" . $baseUrl;
	}
}

switch ( true ) {
case ( !empty( $options["l"] ) ):
	$label = $options["l"];
	break;
case ( !empty( $options["label"] ) ):
	$label = $options["label"];
	break;
default:
	$label = "";
}

$label = str_replace( " ", "_", trim( $label, " _-." ) );
if ( strlen( $label ) > 0 ) {
	$label = "_" . $label;
}

# check the working directory. Files in it need to be executable,
# because the program creates helper scripts for allowing the user to kill unneeded schedules
#
switch ( true ) {
case ( !empty( $options["d"] ) ):
	$workingDirectory = $options["d"];
	break;
case ( !empty( $options["directory"] ) ):
	$workingDirectory = $options["directory"];
	break;
default:
	$user = $_SERVER["USER"];
	if ( $user == "root") {
		$workingDirectory = "/tmp";
	} else {
		$workingDirectory = "/home/$user/Musik";
	}
}

if ( !file_exists( $workingDirectory ) ) {
	error( "working directory '$workingDirectory' does not exist." ) ;
	$workingDirectory = false;
}

if ( isset( $settings["mailto"] ) ) {

	$defaultMailto = $settings["mailto"];
	$mailto = $defaultMailto;

} else {

	switch ( true ) {
		case ( !isset( $options["m"] ) && !isset( $options["mailto"] ) ):
			$mailto = false;
			break;
		case ( !empty( $options["m"] ) ):
			$mailto = $options["m"];
			break;
		case ( !empty( $options["mailto"] ) ):
			$mailto = $options["mailto"];
			break;
		default:
			$mailto = $defaultMailto;
	}

}


# $killAllJobsFilename = "$workingDirectory/kill-all-streamrecorder-jobs.sh";
$killAllJobsFilename = "$workingDirectory/killall.sh";

$noPlayback = ( isset( $options["n"] )
	|| isset( $options["noplayback"] )
	|| ( isset( $settings["noplayback"] ) && ( $settings["noplayback"] === true ) ) );

$record = !( isset( $options["p"] )
	|| isset( $options["playonly"] )
	|| ( isset( $settings["playonly"] ) && ( $settings["playonly"] === true ) ) );

$beep = ( isset( $options["b"] )
	|| isset( $options["beep"] )
	|| ( isset( $settings["beep"] ) && ( $settings["beep"] === true ) ) );

$quiet = ( isset( $options["q"] )
	|| isset( $options["quiet"] )
	|| ( isset( $settings["quiet"] ) && ( $settings["quiet"] === true ) ) );

$verbose = ( isset( $options["V"] )
	|| isset( $options["verbose"] )
	|| ( isset( $settings["verbose"] ) && ( $settings["verbose"] === true ) ) );

$help = ( isset( $options["h"] )
	|| isset( $options["help"] ) );

$kill = ( isset( $options["k"] )
	|| isset( $options["killall"] )
	|| isset( $options["s"] ) || isset( $options["stop"] ) );

$showVersion = ( isset( $options["v"] )
	|| isset( $options["version"] ) );

if ( $kill ) {
	if ( file_exists( $killAllJobsFilename ) ) {
		system( escapeshellcmd( $killAllJobsFilename ) );
		die();
	} else {
		error( "No jobs appear to be scheduled which can be stopped, because $killAllJobsFilename does not exist.", "Info" );
	}
}

if ( $showVersion ) {
	echo PROGRAM_NAME . " " . VERSION . "\n";
	die();
}

if ( $quiet ) {
	$noPlayback = true;
	$beep = false;
}

switch ( true ) {
case ( !empty( $argv[2] ) && !empty( $argv[3] ) ):
	$startTime = strtotime ( $argv[2] );
	if ( $recordingTime = changeTimeToDuration( $stopTime = $argv[3] ) ) {
		$stopTime = $startTime + $recordingTime;
	} else {
		$stopTime = strtotime( $stopTime );
	}
	break;

case ( !empty( $argv[2] ) && empty( $argv[3] ) ):
	# only one time is given. Is it a recording time in minutes or hours ?
	if ( $recordingTime = changeTimeToDuration( $startTime = $argv[2] ) ) {
		$immediateStart = true;
		$stopTime = $now + $recordingTime;
	} else {
		$immediateStart = false;
		$startTime = strtotime( $startTime );
		$stopTime = $startTime + $defaultRecordingTime;
	}
	break;

default:
	$immediateStart = true;
	$startTime = $now;
	$stopTime = $now + $defaultRecordingTime;
}

if ( empty( $startTime ) ) {
	error( "Options must be presented as the first parameters in the command line, immediately following the program name." );
}

$playbackInfo = ( $playbackStartDelay <= 0 ) ? "Playback uses an extra stream and starts about " . -$playbackStartDelay . " seconds before starttime" : "Playback starts about $playbackStartDelay seconds after the recording";
$playbackInfo .= " (--noplayback disables playback while recording).";

if ( $error && !$help ) {
	echo $error . "\nType -h or --help for detailed help\n";
	die();
}

if ( !empty( $argv[1] ) ) {
	$station = $argv[1];
	$stationStripped = preg_replace( "![\W]!i", "", $argv[1] ) ;
	if ( $preprogrammed = isPreprogrammedStation( $stationStripped, $stations ) ) {
		$streamUrl = $preprogrammed["streams"][0]["url"];
		$stationName = $preprogrammed["station"][1]; // use the first ["station"][1], not the program P number ["station"][0]
		if ( !$fn ) {
			$fn = sanitize_filename( $stationName . $label ) . "." . $preprogrammed["streams"][0]["encodingtype"];
		}
	} else if ( preg_match( "!^http://!", $station ) ) {
		// FIXME
		// check whether streamUrl exists
		$streamUrl = $station;
		$stationName = sanitize_filename( str_replace( array( "http://", basename( $streamUrl ), "/", "." ), array( "", "", "-", "-" ), $station ) . $label ) . basename( $streamUrl );
		if ( !$fn ) {
			$fn = $stationName;
		}
	} else {
		error( "Your input " . escapeshellarg( $station ) . " is neither a pre-programmed station name nor a valid url." );
	}
} else {
	# error( "No station name or valid stream url given." );
	$help = true;
}

if ( ( $help || $error ) && ( PHP_SAPI === 'cli' ) ) {
	if ( $error ) $error = "\n" . $error;
	$usage = PROGRAM_NAME . "  " . VERSION ." -- usage:

   php rec.php

       [-n|--noplayback]
       [-p|--playonly]
       [-b|--beep]
       [-q|--quiet]
       [-h|--help]
       [-k|--killall] [-s|--stop]
       [-l<label>|--label=<label>]
       [-m<addr>|--mailto=<addr>]
       [-o<fn>|--output=<fn>]
       [-d<dir>|--directory=<dir>]
       [-u<url>|--baseurl=<url>]
       [-v|--version]
       [-V|--verbose]

       <streamurl>|<stationname> [<starttime>|now] [<stoptime>|###min|###m|#.#hours|#.#h]

   Without times, stream recording or playing starts immediately and stops after $defaultRecordingTimeHours hours of recording.
   If only the starttime is present, stream recording or playing for $defaultRecordingTimeHours hours will start at startime.
   The default recording filename under $workingDirectory comprises the station name or stream url and the start and stop times.

   Recording starts about $preRecordingTime seconds before the start time and stops about $postRecordingTime seconds after the stop time.
   $playbackInfo

   --beep            enables beep tones when recording starts or stops.
   --playonly        disables recording and plays the stream now or at scheduled times
   --mailto=<addr>   send notification e-mail when recording has finished to <addr> (default: $defaultMailto)
   --quiet           fully disables screen output
   --noplayback      fully disables sounds while recording
   --label=<label>   additional text which is added to the filename
   --output=<fn>     user defined recording filename
   --baseurl=<url>   baseurl for web link to recorded file in notification e-mail
   --directory=<dir> user defined working directory

   examples:

   php rec.php nova
   php rec.php --playonly dradio 10min
   php rec.php --noplayback kulturradio 20:00 21:30
   php rec.php -b --label=\"classical concert\" dradio 201112312000 201112312200
   php rec.php http://radioeins.de/livemp3 01:00 2h
   php rec.php --directory=/tmp --output=Dradio-Wissen_News.ogg drwissen 30m
   php rec.php --mailto=mail@example.com --baseurl=http://www.example.com dradio

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
";
	foreach( $stations as $s ) {
		$usage .= "\n   ";
		foreach ( $s["station"] as $key => $value ) {
			$value = preg_replace( "!^p([0-9])$!i", "P$1 :", $value );
			$value = preg_replace( "!^p([0-9]{2})$!i", "P$1:", $value );
			$usage .= $value . " ";
		}
		$usage .= "- " . $s["homepage"];
	}
	echo $usage."

   administrative information:

   sources to look up stream urls: http://tinyurl.com/de-internetradio http://www.radiosure.com/stations/
   using $mplayer for playing and stream recording
   $at task scheduler found and atd task scheduler daemon running with pid $atdPid
   recorded streams will be stored in the working directory $workingDirectory
   " . PROGRAM_NAME . " will generate a script $killAllJobsFilename which can be used for killing all scheduled actions
   (or type 'php rec.php -s').

   Inifiles:

$iniFiles

$error";

/***
 * How to kill scheduled jobs manually:
 * 1. kill any job owning the stream file/s:             # fuser -k <streamfile>*
 * 2. kill scheduled at jobs:                            # atq; atrm <at-pid>
 * 3. kill any pending mplayer recording or playing job: # killall mplayer
 ***/
	die();
}

# adjust startTime only if startTime is not zero (zero means immediate start)
if ( $immediateStart ) {
	$startTime = $now;
}

if ( $startTime > ( $now + $preRecordingTime + $nowGracePeriod ) ) {
	$startTime -= $preRecordingTime;
}

# check if start time equals within a certain grace period "now"
# then assume that the user wanted an immediate start
# adjust the start time to the later of the two for practical reasons
if ( ( abs( $startTime - $now ) <= $nowGracePeriod )
	|| ( ( $startTime < $now ) && ( $stopTime > $now ) ) ) {
	$immediateStart = true;
	$startTime = max( $startTime, $now );
}

$startTime = adjustTime( $startTime );
$stopTime = adjustTime( $stopTime );

$stopTime += $postRecordingTime;
$stopTime_TS = date( $standardFormat, $stopTime );
$startTime_TS = date( $standardFormat, $startTime );

if ( $startTime >= $stopTime ) {
	error( "Error: start time $startTime_TS > stop time $stopTime_TS" );
}

$shortName = date( "YmdHi", $startTime) . "-" . date( "Hi", $stopTime ) . "_" . $fn;
$nul = "1>/dev/null 2>/dev/null";

$fileName = $workingDirectory . "/" . $shortName;
$killSingleJobFilename = $workingDirectory . "/kill-$shortName.sh";
$mailFilename = $workingDirectory . "/mail-$shortName.txt";

# start at demon as root
#    $ rcatd start
#
# or start at in Runlevel 5
#
# or, if you run this program as root, then uncomment the following line
# system( "rcatd start 1>/dev/null");

if ( $beep && file_exists( $startMessageSound ) ) {
	$startRecordingHook = "; nohup mplayer -ao alsa $startMessageSound $nul & ";
} else {
	$startRecordingHook = "";
}

if ( $beep && file_exists( $stopMessageSound ) ) {
	$stopRecordingHook = "; nohup mplayer -ao alsa $stopMessageSound $nul & ";
} else {
	$stopRecordingHook = "";
}

$escFilename = escapeshellarg( $fileName );

$out = array();

# Step 1: schedule recording start
#
# if startTime is about now then start recording directly
#
$killRecordingStartJob = "";
$killPlaybackStartJob = "";
$recordingStartJobid = "";
$playbackJobid = "";
$playbackATStartJobid = "";
$recordingATStartJobid = "";

if ( $record ) {
	if ( $immediateStart ) {
		exec( "nohup mplayer -dumpstream -dumpfile $escFilename ". escapeshellarg( $streamUrl ) ." $nul & echo $! $startRecordingHook",
			$out
		);
		$recordingStartJobid = $out[0];

		# the following is not needed because the fuser command -k escFilename kills also the recordingStartJob
		# $killRecordingStartJob = "kill $recordingStartJobid;";

	} else {
		exec( "echo \"nohup mplayer -dumpstream -dumpfile $escFilename ". escapeshellarg( $streamUrl ) . " & 1>&2 $startRecordingHook\" \
			| at " . date( $atDateformat, $startTime ) . " 2>&1",
			$out
		);
		preg_match( "!^job (\d+)!u", $out[1], $res );
		$recordingATStartJobid = $res[1];
		$killRecordingStartJob = "atrm $recordingATStartJobid;";
	}
}

# Step 2: schedule the playback start
#
# after a grace period so that the streamfile is present and filled with some blocks
#
# playback must not start in the same minute as the recorder, because "at" offers with minute resolution,
# but not seconds resolution, the plackback job may otherwise be started before the record job.
# we add a safe guard of 60 seconds.

if ( !$noPlayback ) switch ( true ) {

	case  ( !$record && !$immediateStart && ( $playbackStartDelay > 0 ) ):
		exec( "echo \"nohup mplayer -ao alsa " . escapeshellarg( $streamUrl ) ." &\" \
			| at " . date( $atDateformat, $startTime+$playbackStartDelay ) . " 2>&1;\n",
			$out2
		);
		preg_match( "!^job (\d+)!u", $out2[1], $res2 );
		$playbackATStartJobid = $res2[1];
		$killPlaybackStartJob =  "atrm $playbackATStartJobid;";
		break;

	case ( $record && !$immediateStart && ( $playbackStartDelay > 0 ) ):
		exec( "echo \"nohup mplayer -ao alsa $escFilename &\" \
			| at " . date( $atDateformat, $startTime+$playbackStartDelay ) . " 2>&1;\n",
			$out2
		);
		preg_match( "!^job (\d+)!u", $out2[1], $res2 );
		$playbackATStartJobid = $res2[1];
		$killPlaybackStartJob =  "atrm $playbackATStartJobid;";
		break;

	case  ( !$immediateStart && ( $playbackStartDelay <= 0 ) ):
		exec( "echo \"nohup mplayer -ao alsa " . escapeshellarg( $streamUrl ) ." &\" \
			| at " . date( $atDateformat, $startTime+$playbackStartDelay ) . " 2>&1;\n",
			$out2
		);
		preg_match( "!^job (\d+)!u", $out2[1], $res2 );
		$playbackATStartJobid = $res2[1];
		$killPlaybackStartJob =  "atrm $playbackATStartJobid;";
		break;

	default:
		# play the stream directly - this uses a second stream while recording a first stream
		#
		# a different solution would be to wait until the recorded stream is available as a file
		# and then playing the file
		exec( "nohup mplayer -ao alsa " . escapeshellarg( $streamUrl ) ." $nul&echo $!",
			$out2
		);
		$playbackJobid = $out2[0];
		$killPlaybackStartJob =  "kill $playbackJobid $nul;";
		break;
}


# Step 3: schedule recording and/or playback stop
#
# actually this kills all (recording and playback) processes which own the streamfile
# or kills all mplayer jobs when only playing (we do not know the jobid of the specific player when scheduling the kill job)

$killRecordingAndPlaybackStopJob = "";

$mailJob = ( $mailto ) ? "(cat $mailFilename;stat -c %s $escFilename) | /usr/sbin/sendmail -t $nul; rm -f $mailFilename " : "";

$rmSingleKillJobFile = ";rm -f $killSingleJobFilename $nul";

switch ( true ) {

	case ( $record && !$immediateStart && ( $playbackStartDelay > 0) ):
		exec( "echo \"fuser -k {$escFilename} $nul {$stopRecordingHook}{$mailJob} $nul {$rmSingleKillJobFile}\" \
			| at " . date( $atDateformat, $stopTime ) . " 2>&1",
			$out3
		);
		preg_match( "!^job (\d+)!u", $out3[1], $res3 );
		$recording_and_playbackATStopJobid = $res3[1];
		$killRecordingAndPlaybackStopJob = "atrm $recording_and_playbackATStopJobid $nul";
		break;

	case ( !$record && !$noPlayback ):
		# this one kills all mplayer task which is not so nice
		# but we do not know the jobid number
		# because the mplayer playback job which we want to kill is started by "at" job
		exec( "echo \"killall mplayer $nul$stopRecordingHook $nul\" \
			| at " . date( $atDateformat, $stopTime ) . " 2>&1",
			$out3
		);
		preg_match( "!^job (\d+)!u", $out3[1], $res3 );
		$recording_and_playbackATStopJobid = $res3[1];
		$killRecordingAndPlaybackStopJob = "atrm $recording_and_playbackATStopJobid $nul";
		break;

	case ( $record && ( $immediateStart || ( $playbackStartDelay <= 0) ) ):
		exec( "echo \"fuser -k $escFilename $nul;killall mplayer {$nul}{$rmSingleKillJobFile}{$stopRecordingHook}{$mailJob}\" \
			| at " . date( $atDateformat, $stopTime ) . " 2>&1",
			$out3
		);
		preg_match( "!^job (\d+)!u", $out3[1], $res3 );
		$recording_and_playbackATStopJobid = $res3[1];
		$killRecordingAndPlaybackStopJob = "atrm $recording_and_playbackATStopJobid $nul";
		break;

	default:
		# nothing to kill (no record, no playback )
}

// echo "If you want to delete all related scheduled recording and playback jobs:
// $infoLine = "### MyStreamRecording scheduled recording\n# $stationName => $fileName from $startTime_TS to $stopTime_TS";

$now_TS = Date( "Y-m-d H:i:s", $now );

$immediateStartString = ( $immediateStart ) ? "\n### immediately starting ...\n###" : "";

$programLine = "###  " . PROGRAM_NAME . " - scheduled recording  ###";
$programLineLen = strlen( $programLine );
$lineA = str_repeat( "#", $programLineLen );
$lineB = "###" . str_repeat( " ", $programLineLen - 6 ) . "###";
$lineC = "###  ". VERSION . str_repeat( " ", $programLineLen - 8 - strlen( VERSION ) ) . "###";
$killJob = "$lineA
$lineB
$programLine
$lineC
$lineB
$lineA
### processed commandline: $cmdLine ($now_TS)
### $immediateStartString
### recording start jobid            : $recordingStartJobid
###                 \"at\" start jobid : $recordingATStartJobid
### play or playback jobid           : $playbackJobid
###                 \"at\" start jobid : $playbackATStartJobid
### stop            \"at\" stop jobid  : $recording_and_playbackATStopJobid
###
### type \"atq\" to view all scheduled \"at\" jobs
### type \"atrm <at-jobid>\" to delete a scheduled \"at\" job <at-jobid>
### type \"ps -f -C mplayer\" to view all mplayer jobs
###
";

if ( $record ) {
	$killJob .= "### >>> scheduled recording $stationName => $fileName from $startTime_TS to $stopTime_TS\n###\n";
}

$killJob .= "### >>> execute '$killAllJobsFilename' or 'php {$_SERVER['PHP_SELF']} --stop' if you wish to stop active and delete scheduled jobs;
###             fully and/or partially recorded streams are preserved: what has already been recorded will not be deleted.
### >>> execute '$killSingleJobFilename' if you wish to delete the single scheduled job
###\n";

if ( $record ) {
	$killJob .= "fuser -k $escFilename $nul;$killRecordingStartJob$killPlaybackStartJob";
} else {
	$killJob .= $killPlaybackStartJob;
}

### $killJob .= "$killRecordingAndPlaybackStopJob\nrm $killAllJobsFilename";

$killJob .= ( $killRecordingAndPlaybackStopJob ) ? "$killRecordingAndPlaybackStopJob\nrm -f $killSingleJobFilename" : "rm -f $killSingleJobFilename";
if ( $mailto ) {
	$killJob .= "\nrm -f $mailFilename";
}

$killSingleJob = $killJob;
$killAllJobs = $killJob . "\nrm -f $killAllJobsFilename";

if ( !file_exists( $killAllJobsFilename ) ) {
	exec( "echo " . escapeshellarg( $killAllJobs ) . " > $killAllJobsFilename;chmod a+x $killAllJobsFilename" );
} else {
	$djf = explode( "\n", file_get_contents( $killAllJobsFilename ) );
	if ( preg_match( "!^rm !", $djf[ count( $djf ) - 2 ] ) ) {
		$x = array_pop( $djf );
		$x = array_pop( $djf );
	}
	exec( "echo " . escapeshellarg( implode( "\n", $djf ) ."\n" . $killAllJobs ) . " > $killAllJobsFilename;chmod a+x $killAllJobsFilename" );
}
exec( "echo " . escapeshellarg( $killSingleJob ) . " > $killSingleJobFilename;chmod a+x $killSingleJobFilename" );

# prepare a file for sendmail
if ( $mailto ) {
	$mailText = prepareMail( $mailto, $killSingleJob, PROGRAM_NAME . "@" . gethostname() . " <no-reply@possible>" );
	exec( "echo " . escapeshellarg( $mailText ) . " > $mailFilename" );
}

# show the final settings of the job programmed by this call
if ( $verbose ) {
	system( "cat $killSingleJobFilename" );
}
