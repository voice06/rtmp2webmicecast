<?php

include "icecastwebm.php";

#####################
### CONFIGURATION ###
#####################
$debug = 0; # Print everything or not.
# Icecast settings.
$icecastserv = "127.0.0.1"; # Server address
$icecastport = 8000; # Server port
$icecastmount = "/mount"; # Mountpoint
$icecastpass = "hackme"; # Source password
$iceGenre = "video"; # Genre for ICE info.
$iceName = "some name"; # Name/Title for ICE info.
$iceDesc = "some information about the stream"; # Description for ICE info.
$iceUrl = "http://your.website.here"; # Url for ICE info.
$icePublic = 0; # List in YP
$icePrivate = 0; # # Hide from mountpoint list.
$iceAudioInfoSamplerate = 44100; # AudioInfo - Samplerate in Hz, doesn't seem to affect anything.
$iceAudioInfoBitrate = 56; # AudioInfo - Bitrate in kbps, doesn't seem to affect anything.
$iceAudioInfoChannels = 2; # AudioInfo - Number of audio channels, doesn't seem to affect anything.
# These are for RTMPDUMP. - Use man pages for further explanation.
$rtmpurl = "rtmp stream url"; # -r option
$appname = "app"; # -a option
$swfVfy = "flash player url"; # -W option
$pageurl = "page where flash player url is found"; # -p option
$playpath = "stream file"; # -y option
$rtmpdumpbin = "/path/to/rtmpdump"; # Path to RTMPDUMP
# Variables relating to FFMPEG for transcoding.
$coreopts = "-v 0"; # Extra core settings. At least use -v 0, it keeps stats info from being put into the stream.
$vcodec = "libvpx"; # Use VP8
$vcodecopts = "-quality good -cpu-used 0 -b:v 150k -qmin 10 -qmax 42 -threads 6"; # Options to throw to the video codec.
$acodec = "libvorbis"; # Use Vorbis
$acodecopts = "-b:a 56k -ar 44100"; # Options to throw to the audio codec.
$ffmpegbin = "/path/to/ffmpeg"; # Path to FFMPEG

# Create instance.
$icewebm = new icecastwebm();

# Open connection
$icewebm->initIcecastComm($icecastserv,$icecastport,$icecastmount,$icecastpass);
?>
