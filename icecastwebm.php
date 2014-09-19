<?php

class icecastwebm {
   #Internal
   var $this;
   
   # Open socket connection to Icecast.
   function initIcecastComm($serv='127.0.0.1', $port=8000, $mount="/setme", $pass="hackmake") {
      global $iceGenre, $iceName, $iceDesc, $iceUrl, $icePublic, $icePrivate, $iceAudioInfoSamplerate, $iceAudioInfoBitrate, $iceAudioInfoChannels;
      // We're going to create a tcp socket.
      $sock = socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));
      socket_set_block($sock) or die("Blocking error: ".socket_strerror(socket_last_error($sock))." \n");
      if (!$sock) {
         die("Unable to create socket! ".socket_strerror(socket_last_error($sock))." \n");
      } else {
         // Connect to the server.
         echo "Connecting to $serv on port $port\n";
         if(socket_connect($sock,$serv,$port)) {
            echo "Connection established! Sending login....\n";
            $this->sendData($sock,"SOURCE $mount HTTP/1.0\r\n");
            $this->sendLogin($sock, $pass);
            $this->sendData($sock, "User-Agent: icecastwebm-php\r\n");
            $this->sendData($sock, "Content-Type: video/webm\r\n");
            $this->sendData($sock, "ice-name: $iceName\r\n");
            $this->sendData($sock, "ice-url: $iceUrl\r\n");
            $this->sendData($sock, "ice-genre: $iceGenre\r\n");
            $this->sendData($sock, "ice-public: $icePublic\r\n");
            $this->sendData($sock, "ice-private: $icePrivate\r\n");
            $this->sendData($sock, "ice-description: $iceDesc\r\n");
            $this->sendData($sock, "ice-audio-info: ice-samplerate=$iceAudioInfoSamplerate;ice-bitrate=$iceAudioInfoBitrate;ice-channels=$iceAudioInfoChannels\r\n");
            $this->sendData($sock, "\r\n");
            // Go into loop mode.
            $this->parseData($sock);
            $this->initStream($sock); // Start the stream.
            //die;
         } else {
            die("Unable to connect to server! ".socket_strerror(socket_last_error($sock))." \n");
         }
      }
   }
   
   # Send connection info.
   function sendLogin($sock, $pass) {
      // Authentication is basic, which requires the format to be 'user:pass' encoded in base64.
      $b64enc = base64_encode("source:$pass");
      $this->sendData($sock,"Authorization: Basic $b64enc\r\n");
   }   

   function sendData($sock,$buf) {
     global $debug;
     if ($debug == 1) {
        echo "-> $buf";
     }
     socket_write($sock, $buf, strlen($buf)) or die("Socket Write Error: ".socket_strerror(socket_last_error($sock))." \n");
   }

   function parseData($sock) {
      global $debug;
      $buf = '';
      $buf = socket_read($sock, 1024, PHP_NORMAL_READ) or die("Socket Read Error: ".socket_strerror(socket_last_error($sock))." \n");
      // Don't even bother if theres no information this go through.
      if (strlen($buf) > 1) {
         if ($debug == 1) {
            echo "<- $buf strlen(".strlen($buf).")\n";
         }
         $parsebuf = explode(" ", $buf); // Needed to get the HTTP response code.
         switch ($parsebuf[1]) {
            case 200:
               echo "Received OK from Icecast server!\n";
               $this->initStream($sock);
               break;
            case 400:
               echo "Recieved Bad Request!\n";
               socket_close($sock);
               die;
               break;
            case 403:
               echo "Received Forbidden!\n";
               socket_close($sock);
               die;
               break;
            case 404:
               echo "Received Not Found!\n";
               socket_close($sock);
               die;
               break;
            default:
               echo "Unknown response received!\n";
               die;
               break;
          }
      }
   }

   # Core part of this script, it creates the processes that grab the stream, convert it, then send it off to Icecast.
   function initStream($sock) {
      global $rtmpurl, $appname, $swfVfy , $pageurl, $playpath, $rtmpdumpbin, $vcodec, $vcodecopts, $acodec, $acodecopts, $ffmpegbin, $coreopts;
      # Build the command.
      $cmd = $rtmpdumpbin." -r ".$rtmpurl." -a ".$appname." -W ".$swfVfy." -p ".$pageurl." -y ".$playpath." -q -o - | ".$ffmpegbin." -i - ".$coreopts." -c:v ".$vcodec." ".$vcodecopts." -f webm -c:a ".$acodec." ".$acodecopts." pipe:1";
      //echo $cmd."\n\n";
      //die;

      // Sourced from http://stackoverflow.com/questions/16351302/reading-from-stdin-pipe-when-using-proc-open/16351484#16351484
      // Modified to fit this script's purpose.
      # Create our pipes.
      $pipespec = array (
         0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
         1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
         2 => array("pipe", "w") // stderr is a file to write to
      );

      $proc = proc_open($cmd, $pipespec, $pipes); // Create the process.

      stream_set_blocking($pipes[1], 1); // Blocking
      stream_set_blocking($pipes[2], 0); // Non-blocking
      stream_set_blocking($pipes[0], 0); // Non-blocking

      // check if opening has succeed
      if($proc === FALSE){
         throw new Exception('Cannot execute child process');
      }

      // get PID via get_status call
      $status = proc_get_status($proc);
      if($status === FALSE) {
         throw new Exception (sprintf(
           'Failed to obtain status information '
         ));
      }
      $pid = $status['pid'];

      echo "RTMP to WEBM process started, streaming to Icecast server.\n";

      // now, poll for childs termination
      while(true) {
         $this->parseData($sock);
         // detect if the child has terminated - the php way
         $status = proc_get_status($proc);
         // check retval
         if($status === FALSE) {
            die("Failed to obtain status information for $pid");
         }
         if($status['running'] === FALSE) {
            $exitcode = $status['exitcode'];
            $pid = -1;
            echo "child exited with code: $exitcode\n";
            exit($exitcode);
         }

         // read from childs stdout
         // check stdout for data
            do {
                $data = fread($pipes[1], 8092);
                $this->sendData($sock, $data); // Write to Icecast.
            } while (strlen($data) > 0);
      }
  
      echo "RTMP stream ended, cleaning up and exiting...\n";

      // Clean up.
      fclose($pipes[2]);
      fclose($pipes[1]);
      fclose($pipes[0]);
      socket_close($sock);
      proc_terminate($proc);
      die;
   }
}
?>
