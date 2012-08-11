#!/bin/sh
echo "Converting ogg to mp3"

# variable bitrate
# encoding="-V 2"

# constant bitrate 128 kbps
encoding="-b 128"

# requires: libvorbis
# requires: vorbis-tools

if [ $# -eq 2 ] ; then
	# everything before last '.' see http://goo.gl/uSNYf
	ogg123 -d wav $1 -f - | lame $encoding -q0 -ms - $2
	exit 1
fi

if [ $1 ] ; then
	# everything before last '.' see http://goo.gl/uSNYf
	ogg123 -d wav $1 -f - | lame $encoding -q0 -ms - ${1%\.*}.mp3
	exit 1
else
	echo "Usage: ogg2mp3 infilename.ogg [ outfilename.mp3 ]"
fi
