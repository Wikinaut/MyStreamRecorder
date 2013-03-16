#!/bin/sh
echo "Decoding mp3 to wav"

if [ $# -eq 2 ] ; then
	# everything before last '.' see http://goo.gl/uSNYf
	mplayer -vc null -vo null -af resample=44100 -ao pcm:fast -ao pcm:waveheader -ao pcm:file=$2 $1
	exit 1
fi

if [ $1 ] ; then
	# everything before last '.' see http://goo.gl/uSNYf
	mplayer -vc null -vo null -af resample=44100 -ao pcm:fast -ao pcm:waveheader -ao pcm:file=${1%\.*}.wav $1
	exit 1
else
	echo "Usage: mp32wav infilename.mp3 [ outfilename.wav ]"
fi
