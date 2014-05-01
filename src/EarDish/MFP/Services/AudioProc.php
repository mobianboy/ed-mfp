<?php

namespace EarDish\MFP\Services;

class AudioProc {
    private $api;
    
    public $sourceFile;
    public $formats = array();
    public $halt = false;
    public $success = false;
    
    const DETAIL = 5;
    const DEFAULT_WIDTH = 1000;
    const DEFAULT_HEIGHT = 101;
    const DEFAULT_FOREGROUND = "#FF0000";
    const DEFAULT_BACKGROUND = "#000000";
    const FFT_DETAIL = 2048; // Default = 2048
    
    const WAVE_FORMAT = "py";
    
    function __construct() {
        /*global $kernel;

        if ('AppCache' == get_class($kernel)) {
            $kernel = $kernel->getKernel();
        }

        $this->container = $kernel->getContainer();
        
        $this->api = $this->container->get("apibuilder");*/
    }
    
    public function apiInject(&$api) {
        $this->api = &$api;
    }
    
    public function openAudio($source, $type) {
        
        switch ($type) {
            
            case "url":
                
                break;
            case "upload":
                if (!is_uploaded_file($source)) {
                    $this->api->setStatus(30);
                    
                    return $this;
                }
                
                if (function_exists("finfo_open")) {
                    $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $source);
                } else {
                    $mime = mime_content_type($source);
                }
                
                $source_type = $this->mimeToType($mime);
                break;
            case "cloud":
                $wav = file_get_contents($source);
                
                $this->sourceFile = $this->saveToTemp($wav, "wav", "binary");
                
                $source_type = "wav";
                break;
            
        }
        
        if (!$source_type) {
            if (!$mime) {
                $mime = "unknown/unknown";
            }
            
            $this->api->setStatus(93)->setDataBlock('text', 'eardish', $mime);
            
            $this->halt = true;
        } else {
            $this->sourceType = $source_type;
            
            if ($type == "upload") {
                $this->sourceFile = $this->saveToTemp($source, $source_type);
            }
            
            if (!$this->sourceFile) {
                $this->halt = true;
            }
            
            if ($source_type == "mp4" || $source_type == "m4a") {
                $this->formatMpeg();
            }
            
            if ($source_type == "wav") {
                $this->formats['wav'] = $this->sourceFile;
            }
            
            if ($source_type == "mp3") {
                $this->formats['mp3'] = $this->sourceFile;
            }
            
            if ($source_type == "ogg") {
                $this->formats['ogg'] = $this->sourceFile;
            }
        }
        
        return $this;
        
    }
    
    private function getLength() {
        
        $len = shell_exec("/usr/bin/sox ".$this->formats['wav']['full']." -n stat 2>&1");
        
        $stats = explode("\n", $len);
        
        foreach ($stats as $val) {
            if (strpos($val, "Length") !== false) {
                $length = str_replace("Length (seconds): ", "", $val);
            }
        }
        
        $length = intval($length);
        
        $this->SoxStats = $stats;
        
        return $length;
    }
    
    private function clipSong($start, $length) {
        
        $this->formats["wav"] = array("full" => "/dev/shm/".$this->sourceFile['name']."_demo.wav", "name" => $this->sourceFile['name']."_demo", "file" => $this->sourceFile['name']."_file.wav");
        
        @exec("/usr/bin/sox ".$this->sourceFile['full']." ".$this->formats['wav']['full']." trim ".intval($start)." ".intval($length));
        
        unlink($this->sourceFile['full']);
        
        $this->sourceFile = array("full" => "/dev/shm/".$this->sourceFile['name']."_demo.wav", "name" => $this->sourceFile['name']."_demo", "file" => $this->sourceFile['name']."_file.wav");
        
        return $this;
        
    }
    
    private function formatMpeg() {
        
        $ext = "wav";
        
        $this->formats[$ext] = array("full" => "/dev/shm/".$this->sourceFile['name'].".".$ext, "name" => $this->sourceFile['name'], "file" => $this->sourceFile['name'].".".$ext);
        
        $conv = @exec("/usr/bin/ffmpeg -i ".$this->sourceFile['full']." -f wav ".$this->formats[$ext]['full']." > /dev/null");
        
        unlink($this->sourceFile['full']);
        
        $this->sourceFile = $this->formats[$ext];
        
        return $this;
        
    }
    
    private function convToExt($ext) {
        
        $this->formats[$ext] = array("full" => "/dev/shm/".$this->sourceFile['name'].".".$ext, "name" => $this->sourceFile['name'], "file" => $this->sourceFile['name'].".".$ext);

        $conv = @exec("/usr/bin/sox ".$this->sourceFile['full']." ".$this->formats[$ext]['full']);

        return $this;
        
    }
    
    private function saveToTemp($source, $ext, $type = "upload") {
        
        $dir = "/dev/shm/";
        
        $name = str_replace(".", "", microtime(true))."_".rand(0, 100);
        
        $file = $name.".".$ext;
        
        if ($type == "upload") {
            if (move_uploaded_file($source, $dir.$file)) {
                
                
                
                return array("full" => $dir.$file, "name" => $name, "file" => $file);
            } else {
                return false;
            }
        } elseif ($type == "binary") {
            $fp = fopen($dir.$file, "w");
            
            if ($fp) {
                fwrite($fp, $source);

                fclose($fp);
                
                return array("full" => $dir.$file, "name" => $name, "file" => $file);
            } else {
                return false;
            }
        }
        
    }
    
    public function doDemoClip($source, $type, $start, $length) {
        $this->openAudio($source, $type);
        
        if ($this->halt) {
            return false;
        }
        
        $this->clipSong($start, $length);
        
        foreach (array("ogg", "mp3", "wav") as $val) {
            if (isset($this->formats[$val])) {
                continue;
            }
            
            $this->convToExt($val);
        }
        
        if (count($this->formats) < 3) {
            return false;
        }
        
        switch (self::WAVE_FORMAT) {
            case "py":
                $svg = $this->makeWaveFormPY();
                break;
            
            case "png":
                $svg = $this->makeWaveFormPNG();
                break;
            
            case "svg":
                $svg = $this->makeWaveForm();
                if (is_string($svg)) {
                    $fp = fopen("/dev/shm/".$this->formats['wav']['name'].".svg", "w");

                    if (fwrite($fp, $svg)) {
                        $this->waveform = "/dev/shm/".$this->formats['wav']['name'].".svg";
                    } else {
                        return false;
                    }

                    fclose($fp);
                }
                break;
        }
        
        if (!$svg) {
            return false;
        }
        
        return true;
        
    }
    
    public function doSoundConv($source, $type) {
        
        $this->openAudio($source, $type);
        
        if ($this->halt) {
            return false;
        }
        
        foreach (array("ogg", "mp3", "wav") as $val) {
            if (isset($this->formats[$val])) {
                continue;
            }
            
            $this->convToExt($val);
        }
        
        $this->songlength = $this->getLength();
        
        if (count($this->formats) < 3) {
            return false;
        }
        
        switch (self::WAVE_FORMAT) {
            case "py":
                $svg = $this->makeWaveFormPY();
                break;
            
            case "png":
                $svg = $this->makeWaveFormPNG();
                break;
            
            case "svg":
                $svg = $this->makeWaveForm();
                if (is_string($svg)) {
                    $fp = fopen("/dev/shm/".$this->formats['wav']['name'].".svg", "w");

                    if (fwrite($fp, $svg)) {
                        $this->waveform = "/dev/shm/".$this->formats['wav']['name'].".svg";
                    } else {
                        return false;
                    }

                    fclose($fp);
                }
                break;
        }
        
        if (!$svg) {
            return false;
        }
        
        return true;
        
    }
    
    private function findValues($byte1, $byte2){
        $byte1 = hexdec(bin2hex($byte1));                        
        $byte2 = hexdec(bin2hex($byte2));                        
        return ($byte1 + ($byte2*256));
    }
    
    private function html2rgb($input) {
        $input=($input[0]=="#")?substr($input, 1,6):substr($input, 0,6);
        return array(
         hexdec(substr($input, 0, 2)),
         hexdec(substr($input, 2, 2)),
         hexdec(substr($input, 4, 2))
        );
   }
    
    private function makeWaveForm() {
        if (!isset($this->formats['wav']['full'])) {
            $this->convToWav();
        }
        
        $wavs_to_process[] = $this->formats['wav']['full'];
        
        $svg  = "<?xml version=\"1.0\"?>\n";
        $svg .= "<?xml-stylesheet href=\"waveform.css\" type=\"text/css\"?>\n";
        $svg .= "<!DOCTYPE svg PUBLIC \"-//W3C//DTD SVG 1.1//EN\" \"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd\">\n";
        $svg .= "<svg width=\"100%\" height=\"100%\" version=\"1.1\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\">\n";
        // rect for background color
        $svg .= "<rect width=\"100%\" height=\"100%\" />\n";

        $y_offset = floor(1 / sizeof($wavs_to_process) * 100);

        // process each wav individually
        for($wav = 1; $wav <= sizeof($wavs_to_process); $wav++) {

          $svg .= "<svg y=\"" . ($y_offset * ($wav - 1)) . "%\" width=\"100%\" height=\"{$y_offset}%\">";

          $filename = $wavs_to_process[$wav - 1];

          /**
           * Below as posted by "zvoneM" on
           * http://forums.devshed.com/php-development-5/reading-16-bit-wav-file-318740.html
           * as findValues() defined above
           * Translated from Croation to English - July 11, 2011
           */
          $handle = fopen($filename, "r");
          // wav file header retrieval
          $heading[] = fread($handle, 4);
          $heading[] = bin2hex(fread($handle, 4));
          $heading[] = fread($handle, 4);
          $heading[] = fread($handle, 4);
          $heading[] = bin2hex(fread($handle, 4));
          $heading[] = bin2hex(fread($handle, 2));
          $heading[] = bin2hex(fread($handle, 2));
          $heading[] = bin2hex(fread($handle, 4));
          $heading[] = bin2hex(fread($handle, 4));
          $heading[] = bin2hex(fread($handle, 2));
          $heading[] = bin2hex(fread($handle, 2));
          $heading[] = fread($handle, 4);
          $heading[] = bin2hex(fread($handle, 4));

          // wav bitrate 
          $peek = hexdec(substr($heading[10], 0, 2));
          $byte = $peek / 8;

          // checking whether a mono or stereo wav
          $channel = hexdec(substr($heading[6], 0, 2));

          $ratio = ($channel == 2 ? 40 : 80);

          // start putting together the initial canvas
          // $data_size = (size_of_file - header_bytes_read) / skipped_bytes + 1
          $data_size = floor((filesize($filename) - 44) / ($ratio + $byte) + 1);
          $data_point = 0;

          while(!feof($handle) && $data_point < $data_size){
            if ($data_point++ % self::DETAIL == 0) {
              $bytes = array();

              // get number of bytes depending on bitrate
              for ($i = 0; $i < $byte; $i++)
                $bytes[$i] = fgetc($handle);

              switch($byte){
                // get value for 8-bit wav
                case 1:
                  $data = $this->findValues($bytes[0], $bytes[1]);
                  break;
                // get value for 16-bit wav
                case 2:
                  if(ord($bytes[1]) & 128)
                    $temp = 0;
                  else
                    $temp = 128;
                  $temp = chr((ord($bytes[1]) & 127) + $temp);
                  $data = floor($this->findValues($bytes[0], $temp) / 256);
                  break;
              }

              // skip bytes for memory optimization
              fseek($handle, $ratio, SEEK_CUR);

              // draw this data point
              // data values can range between 0 and 255        
              $x1 = $x2 = number_format($data_point / $data_size * 100, 2);
              $y1 = number_format($data / 255 * 100, 2);
              $y2 = 100 - $y1;
              // don't bother plotting if it is a zero point
              if ($y1 != $y2)
                $svg .= "<line x1=\"{$x1}%\" y1=\"{$y1}%\" x2=\"{$x2}%\" y2=\"{$y2}%\" />";   

            } else {
              // skip this one due to lack of detail
              fseek($handle, $ratio + $byte, SEEK_CUR);
            }
          }

          $svg .= "</svg>\n";

          // close and cleanup
          fclose($handle);

        }

        $svg .= "\n</svg>";
        
        return $svg;
    }
    
    private function makeWaveFormPNG() {
        if (!isset($this->formats['wav']['full'])) {
            $this->convToWav();
        }
        
        $wavs_to_process[] = $this->formats['wav']['full'];
        
        $width = self::DEFAULT_WIDTH;
        $height = self::DEFAULT_HEIGHT;
        $foreground = self::DEFAULT_FOREGROUND;
        $background = self::DEFAULT_BACKGROUND;
        $draw_flat = false;

        $img = false;

        // generate foreground color
        list($r, $g, $b) = $this->html2rgb($foreground);

        // process each wav individually
        for($wav = 1; $wav <= sizeof($wavs_to_process); $wav++) {

          $filename = $wavs_to_process[$wav - 1];
          
          $size = filesize($filename);
          
          $detail = 60*round($size/1048576);

          /**
           * Below as posted by "zvoneM" on
           * http://forums.devshed.com/php-development-5/reading-16-bit-wav-file-318740.html
           * as findValues() defined above
           * Translated from Croation to English - July 11, 2011
           */
          $handle = fopen($filename, "r");
          // wav file header retrieval
          $heading[] = fread($handle, 4);
          $heading[] = bin2hex(fread($handle, 4));
          $heading[] = fread($handle, 4);
          $heading[] = fread($handle, 4);
          $heading[] = bin2hex(fread($handle, 4));
          $heading[] = bin2hex(fread($handle, 2));
          $heading[] = bin2hex(fread($handle, 2));
          $heading[] = bin2hex(fread($handle, 4));
          $heading[] = bin2hex(fread($handle, 4));
          $heading[] = bin2hex(fread($handle, 2));
          $heading[] = bin2hex(fread($handle, 2));
          $heading[] = fread($handle, 4);
          $heading[] = bin2hex(fread($handle, 4));

          // wav bitrate 
          $peek = hexdec(substr($heading[10], 0, 2));
          $byte = $peek / 8;

          // checking whether a mono or stereo wav
          $channel = hexdec(substr($heading[6], 0, 2));

          $ratio = ($channel == 2 ? 40 : 80);

          // start putting together the initial canvas
          // $data_size = (size_of_file - header_bytes_read) / skipped_bytes + 1
          $data_size = floor((filesize($filename) - 44) / ($ratio + $byte) + 1);
          $data_point = 0;

          // now that we have the data_size for a single channel (they both will be the same)
          // we can initialize our image canvas
          if (!$img) {
            // create original image width based on amount of detail
                                    // each waveform to be processed with be $height high, but will be condensed
                                    // and resized later (if specified)
            $img = imagecreatetruecolor($data_size / $detail, $height * sizeof($wavs_to_process));

            // fill background of image
            if ($background == "") {
              // transparent background specified
              imagesavealpha($img, true);
              $transparentColor = imagecolorallocatealpha($img, 0, 0, 0, 127);
              imagefill($img, 0, 0, $transparentColor);
            } else {
              list($br, $bg, $bb) = $this->html2rgb($background);
              imagefilledrectangle($img, 0, 0, (int) ($data_size / $detail), $height * sizeof($wavs_to_process), imagecolorallocate($img, $br, $bg, $bb));
            }
          }
          
          $prevmult = 0;
          
          while(!feof($handle) && $data_point < $data_size){
            if ($data_point++ % $detail == 0) {
              $bytes = array();

              // get number of bytes depending on bitrate
              for ($i = 0; $i < $byte; $i++)
                $bytes[$i] = fgetc($handle);

              switch($byte){
                // get value for 8-bit wav
                case 1:
                  $data = $this->findValues($bytes[0], $bytes[1]);
                  break;
                // get value for 16-bit wav
                case 2:
                  if(ord($bytes[1]) & 128)
                    $temp = 0;
                  else
                    $temp = 128;
                  $temp = chr((ord($bytes[1]) & 127) + $temp);
                  $data = floor($this->findValues($bytes[0], $temp) / 256);
                  break;
              }
              
              $mult = hexdec(bin2hex($bytes[0])) / hexdec(bin2hex($bytes[1]));
              
              $red = hexdec("FF");
              $green = 0;
              $blue = 0;
              
              if ($prevmult != 0) {
                $newmult = $prevmult/$mult;
              } else {
                $newmult = $mult;
              }
              
              if ($newmult > 1) {
                  $bit = 1 / $newmult;
                  
                  if ($bit < 0.05) {
                      $bit = $bit*10;
                  }
                  
                  $green = round($red*$bit);
                  $blue = $red - round($green*$bit);
                  $red = $red - $blue;
              } else {
                  $bit = $newmult;
                  
                  if ($bit < 0.05) {
                      $bit = $bit*10;
                  }
                  
                  $blue = round($red*$bit);
                  $green = $red - round($blue*$bit);
                  $red = $red - $green;
              }
              
              $prevmult = $mult;

              // skip bytes for memory optimization
              fseek($handle, $ratio, SEEK_CUR);

              // draw this data point
              // relative value based on height of image being generated
              // data values can range between 0 and 255
              $v = (int) ($data / 255 * $height);

              // don't print flat values on the canvas if not necessary
              if (!($v / $height == 0.5 && !$draw_flat))
                // draw the line on the image using the $v value and centering it vertically on the canvas
                imageline(
                  $img,
                  // x1
                  (int) ($data_point / $detail),
                  // y1: height of the image minus $v as a percentage of the height for the wave amplitude
                  $height * $wav - $v,
                  // x2
                  (int) ($data_point / $detail),
                  // y2: same as y1, but from the bottom of the image
                  $height * $wav - ($height - $v),
                  imagecolorallocate($img, $red, $green, $blue)
                );         

            } else {
              // skip this one due to lack of detail
              fseek($handle, $ratio + $byte, SEEK_CUR);
            }
          }

          // close and cleanup
          fclose($handle);

        }
        
        $this->waveGD = $img;
        
        if (get_resource_type($img) == "gd") {
            return true;
        } else {
            return false;
        }
    }
    
    private function makeWaveFormPY() {
        @exec("python /home/eardish/alpha-mfp/wav2png.py -a /dev/shm/".$this->formats['wav']['name'].".png -w ".self::DEFAULT_WIDTH." -h ".self::DEFAULT_HEIGHT." -f ".self::FFT_DETAIL." ".$this->formats['wav']['full']." > /dev/null");
        
        $img = imagecreatefrompng("/dev/shm/".$this->formats['wav']['name'].".png");
        
        $this->waveGD = $img;
        
        if (get_resource_type($img) == "gd") {
            unlink("/dev/shm/".$this->formats['wav']['name'].".png");
            
            return true;
        } else {
            return false;
        }
    }
    
    private function mimeToType($mime) {
        
        $mimeParts = explode("/", $mime);
        
        if ($mimeParts[0] != "audio") {
            return false;
        }
        
        switch ($mimeParts[1]) {
            
            case "mpeg":
                return "mp3";
                break;
            case "mp4":
                return "mp4";
                break;
            case "m4a":
            case "x-m4a":
                return "m4a";
                break;
            case "ogg":
            case "vorbis":
                return "ogg";
                break;
            case "wav":
            case "x-wav":
            case "wave":
                return "wav";
                break;
            case "x-aiff":
                return "aiff";
                break;
            case "raw":
            case "x-raw":
                return "raw";
                break;
            default:
                return false;
                break;
            
        }
        
    }
    
}
