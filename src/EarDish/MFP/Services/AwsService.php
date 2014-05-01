<?php

namespace EarDish\MFP\Services;

use Aws\Common\Aws;
use Aws\Common\Enum\Region;
use EarDish\MFP\APIHandlerBundle\Entity;
use EarDish\MFP\Services;


class AwsService {
    public $aws;
    public $s3;
    public $cf;
    public $em;
    public $env;
    public $logger;
    
    const IMAGE_BUCKET = "images.eardish.net";
    const IMAGE_BUCKET_DEV = "devimages.eardish.net";
    const AUDIO_BUCKET = "audio.eardish.net";
    const AUDIO_BUCKET_DEV = "devaudio.eardish.net";
    
    const CF_IMAGES_BASE = "http://cdni.eardish.net/";
    const S3_IMAGES_BASE = "http://images.eardish.net/";
    const S3_IMGDIR_BASE = "http://images.eardish.net.s3.amazonaws.com/";
    
    const CF_IMAGES_BASE_DEV    = "http://devcdni.eardish.net/";
    const S3_IMAGES_BASE_DEV    = "http://devimages.eardish.net/";
    const S3_IMGDIR_BASE_DEV    = "http://devimages.eardish.net.s3.amazonaws.com/";
    
    const CF_AUDIO_BASE = "http://cdna.eardish.net/";
    const S3_AUDIO_BASE = "http://audio.eardish.net/";
    const S3_AUDDIR_BASE = "http://audio.eardish.net.s3.amazonaws.com/";
    
    const CF_AUDIO_BASE_DEV     = "http://devcdna.eardish.net/";
    const S3_AUDIO_BASE_DEV     = "http://devaudio.eardish.net/";
    const S3_AUDDIR_BASE_DEV    = "http://devaudio.eardish.net.s3.amazonaws.com/";
    
    function __construct(\Symfony\Bridge\Monolog\Logger $logger = null) {
        $aws = Aws::factory(array(
            'key' => 'AKIAJ524YL33KEOM3EHA',
            'secret' => '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',
            'region' => Region::US_EAST_1
        ));
        
        $this->logger = $logger;
        
        $this->aws = $aws;
    }
    
    public function setEM(&$em) {
        $this->em = &$em;
    }
    
    public function setEnv($env) {
        $this->env = $env;
    }
    
    public function startS3Service() {
        $this->s3 = $this->aws->get('s3');
        $options = $this->s3->getConfig()->get('curl.options');
        $options['body_as_string'] = TRUE;
        $this->s3->getConfig()->set("curl.options", $options);
    }
    
    public function startCFService() {
        $this->cf = $this->aws->get('cloudfront');
    }
    
    public function pushGdToS3(Services\ImageProc &$imgproc, Entity\Image $image, $type, $typeid) {
        if (!$this->s3 || empty($this->s3) || !($this->s3 instanceof \Aws\S3\S3Client)) {
            $this->startS3Service();
        }
        
        if ($imgproc instanceof ImageProc) {
            //
        } else {
            return false;
        }
        
        $namebase = $image->getId()."_";
        
        $this->logger->addCritical("==ENV DEBUG== Environment: ".$this->env);
        
        if ($this->env == "dev" || $this->env == "qa") {
            $IMGBUCKET = self::IMAGE_BUCKET_DEV;
            $S3IMGBASE = self::CF_IMAGES_BASE_DEV;
            $CFIBASE = self::CF_IMAGES_BASE_DEV;
            $this->logger->addDebug("In dev/qa switch.");
        } elseif ($this->env == "prod") {
            $IMGBUCKET = self::IMAGE_BUCKET;
            $S3IMGBASE = self::CF_IMAGES_BASE;
            $CFIBASE = self::CF_IMAGES_BASE;
            $this->logger->addDebug("In prod switch.");
        }
        
        foreach ($imgproc->resized as $key => $val) {
            $info = $this->saveGDToTemp($val, $imgproc->source['type'], $namebase.$key);
            
            imagedestroy($imgproc->resized[$key]);
            
            unset($val);
            unset($imgproc->resized[$key]);
            
            $file = fopen($info['path'], "r");
            
            $s3key = $type."/".$typeid."/".$info['file'];
            
            $response = $this->createS3Object($IMGBUCKET, $s3key, $file);
            
            $objectURL = $CFIBASE."/".$s3key;
            
            $imageFormat = new Entity\ImageFormat();
            $imageFormat->setFormat($key);
            $imageFormat->setS3url($objectURL);
            $imageFormat->setS3key($s3key);
            
            $image->getFormats()->add($imageFormat);
            $imageFormat->setImage($image);
            
            $this->em->persist($imageFormat);
            $this->em->persist($image);
            $this->em->flush();
            
            fclose($file);
            
            $this->unlink($info['path']);
        }
        
        $info = $this->saveGDToTemp($imgproc->workingResource, $imgproc->source['type'], $namebase."original");
        
        imagedestroy($imgproc->workingResource);
        
        unset($imgproc->workingResource);
        
        $file = fopen($info['path'], "r");
        
        $s3key = $type."/".$typeid."/".$info['file'];
        
        $response = $this->createS3Object($IMGBUCKET, $s3key, $file);
        
        $this->S3ObjectURL = $CFIBASE.$s3key;
        
        $image->setS3url($this->S3ObjectURL);
        $image->setS3key($s3key);
        
        $this->em->persist($image);
        $this->em->flush();
        
        fclose($file);
        $this->unlink($info['path']);
        
        return true;
    }
    
    public function pushSingleGdToS3(Services\ImageProc &$imgproc, Entity\Image $image, $type, $typeid) {
        if (!$this->s3 || empty($this->s3) || !($this->s3 instanceof \Aws\S3\S3Client)) {
            $this->startS3Service();
        }
        
        if ($imgproc instanceof ImageProc) {
            //
        } else {
            return false;
        }
        
        $namebase = $image->getId()."_";
        
        $this->logger->addCritical("==ENV DEBUG== Environment: ".$this->env);

        if ($this->env == "dev" || $this->env == "qa") {
            $IMGBUCKET = self::IMAGE_BUCKET_DEV;
            $S3IMGBASE = self::CF_IMAGES_BASE_DEV;
            $CFIBASE = self::CF_IMAGES_BASE_DEV;
        } elseif ($this->env == "prod") {
            $IMGBUCKET = self::IMAGE_BUCKET;
            $S3IMGBASE = self::CF_IMAGES_BASE;
            $CFIBASE = self::CF_IMAGES_BASE;
        }
        
        foreach ($imgproc->resized as $key => $val) {
            $info = $this->saveGDToTemp($val, $imgproc->source['type'], $namebase.$key);
            
            imagedestroy($imgproc->resized[$key]);
            
            unset($val);
            unset($imgproc->resized[$key]);
            
            $file = fopen($info['path'], "r");
            
            $s3key = $type."/".$typeid."/".$info['file'];
            
            $response = $this->createS3Object($IMGBUCKET, $s3key, $file);
            
            $objectURL = $CFIBASE."/".$s3key;
                
            $imageFormat = new Entity\ImageFormat();
            $imageFormat->setFormat($key);
            $imageFormat->setS3url($objectURL);
            $imageFormat->setS3key($s3key);
            
            $image->getFormats()->add($imageFormat);
            $imageFormat->setImage($image);
            
            $this->em->persist($imageFormat);
            $this->em->persist($image);
            $this->em->flush();
            
            fclose($file);
            
            $this->unlink($info['path']);
        }
        
         $this->S3ObjectURL = $objectURL;
        
        unset($imgproc->workingResource);
        
        return true;
    }
    
    public function pushAudioToS3(Services\AudioProc $audioproc, Entity\Audio $audio, $type, $typeid) {
        if (!$this->s3 || empty($this->s3) || !($this->s3 instanceof \Aws\S3\S3Client)) {
            $this->startS3Service();
        }
        
        if ($audioproc instanceof AudioProc) {
            //
        } else {
            return false;
        }
        
        if ($this->env == "dev" || $this->env == "qa") {
            $IMGBUCKET = self::IMAGE_BUCKET_DEV;
            $S3IMGBASE = self::CF_IMAGES_BASE_DEV;
            $CFIBASE = self::CF_IMAGES_BASE_DEV;
            $AUDBUCKET = self::AUDIO_BUCKET_DEV;
            $CFABASE = self::CF_AUDIO_BASE_DEV;
        } elseif ($this->env == "prod") {
            $IMGBUCKET = self::IMAGE_BUCKET;
            $S3IMGBASE = self::CF_IMAGES_BASE;
            $CFIBASE = self::CF_IMAGES_BASE;
            $AUDBUCKET = self::AUDIO_BUCKET;
            $CFABASE = self::CF_AUDIO_BASE;
        }
        
        $s3key = $type."/".$typeid."/";
        
        foreach ($audioproc->formats as $ext => $val) {
            $s3key_ext = $s3key.$audio->getId().".".$ext;
            
            $file = fopen($val['full'], "r");
            
            $response = $this->createS3Object($AUDBUCKET, $s3key_ext, $file);
            
            $objectURL = $CFABASE.$s3key_ext;
            
            $this->S3ObjectURL[$ext] = $objectURL;
            
            $audioFormat = new Entity\AudioFormat();
            $audioFormat->setFormat($ext);
            $audioFormat->setS3url($objectURL);
            $audioFormat->setS3key($s3key_ext);
            
            $audio->getFormats()->add($audioFormat);
            $audioFormat->setAudio($audio);
            
            if ($ext == "mp3") {
                $audio->setS3key($s3key_ext);
                $audio->setS3url($objectURL);
            }
            
            $this->em->persist($audioFormat);
            $this->em->persist($audio);
            $this->em->flush();
            
            $this->unlink($val['full']);
        }
        
        $s3key_png = $s3key.$audio->getId().".png";
        
        $info = $this->saveGDToTemp($audioproc->waveGD, "png", $audioproc->formats['wav']['name']);
        
        $file = fopen($info['path'], "r");
        
        $response = $this->createS3Object($IMGBUCKET, $s3key_png, $file);
        
        $this->unlink($info['path']);
        
        $objectURL = $CFIBASE.$s3key_png;
        
        $this->S3ObjectURL['png'] = $objectURL;
        
        $image = new Entity\Image();
        $image->setType($type."_waveform");
        $image->setTypeId($typeid);
        $image->setS3url($objectURL);
        $image->setS3key($s3key_png);
        
        $this->em->persist($image);
        $this->em->flush();
        
        $this->wf_id = $image->getId();
        
        //unlink($audioproc->waveform);
        
        return true;
    }
    
    private function unlink($path) {
        
        exec("rm -f ".$path);
        
    }
    
    private function createS3Object($bucket, $key, $body, $args = array()) {
        
        $args['ACL'] = 'public-read';
        $args['Bucket'] = $bucket;
        $args['Key'] = $key;
        $args['Body'] = $body;
        
        if (!$this->s3 || empty($this->s3) || !($this->s3 instanceof \Aws\S3\S3Client)) {
            $this->startS3Service();
        }
        
        $response = $this->s3->putObject($args);
        
        return $response;
        
    }
    
    private function saveGDToTemp($gd, $type, $name) {
        
        $tmp = "/dev/shm/";
        
        switch ($type) {
            case "gif":
                $path = $tmp.$name.".gif";
                $file = $name.".gif";
                imagegif($gd, $path);
                break;
            
            case "jpg":
                $path = $tmp.$name.".jpg";
                $file = $name.".jpg";
                imagejpeg($gd, $path);
                break;
            
            case "png":
            default:
                $path = $tmp.$name.".png";
                $file = $name.".png";
                imagepng($gd, $path);
                break;
        }
        
        return array("path" => $path, "file" => $file);
        
    }
    
}
