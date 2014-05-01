<?php

namespace EarDish\MFP\APIHandlerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use EarDish\MFP\Services;
use Symfony\Component\HttpFoundation\Response;
use EarDish\MFP\APIHandlerBundle\Entity;

class DefaultController extends Controller
{
    
    /*
     * This action handles all 404'd requests to MFP
     * 
     * NOTE: For some reason it appears this isn't being called/user correctly. Need to investigate.
     */
    public function unknownAction(GetResponseForExceptionEvent $event) {
        $exception = $event->getException();
        
        // Get the API Builder
        $api = $this->get("apibuilder");
        
        if ($exception->getMessage()) {
            // If there's a message, return it.
            $api->setStatus(91)->setDataBlock('text', 'eardish', $exception->getMessage());
        } else {
            // Otherwise return a generic status of 50
            $api->setStatus(50);
        }
        
        $response = new Response($api);
        
        return $response;
    }
    
    /*
     * This action handles the /upload URL and processes media files
     */
    public function uploadAction() {
        // Get the API Builder
        $api = $this->get("apibuilder");
        // Get the API Request service. This service interprets JSON sent in the 
        // $_POST['data'] field.
        $req = $this->get("apirequest");
        
        // Get the 'format' option from the JSON
        $format = $req->getVar("format");
        // Get the 'type' option from the JSON with the default value 'profile'
        $type   = $req->getVar("type", "profile");
        // Get the 'url' option from the JSON
        $url    = $req->getVar("url");
        // Get the 'id' option from the JSON
        $id     = $req->getVar("id");
        // If there are any file uploads, grab those.
        $files  = (isset($_FILES['mfp_upload'])) ? true : false;
        
        // Get the Doctrine entity manager
        $em = $this->getDoctrine()->getManager();
        // Get the name of the current environment
        $env = $this->container->get('kernel')->getEnvironment();
        
        if ($files || $url) {
            // If there is an uploaded file *or* a URL then I have a source to use
            // for storing a file
            $source = true;
        } else {
            // Otherwise there is no source sent
            $source = false;
        }
        
        // This should be false unless the request was for an image format and the
        // required variables are emtpy
        $imageBadVars = $req->strComp("format", "image")->joinAnd()->groupBool(array("type", "id", $source), false, "any")->toBool();
        
        // This should be false unless the request was for an audio file and the
        // required variables are empty
        $audioBadVars = $req->strComp("format", "audio")->joinAnd()->groupBool(array("id", $source), false, "any")->toBool();
        
        // The format variable has to be set
        $formatBadVar = $req->testVar("format", true);

        if ($req->groupBool(array($imageBadVars, $audioBadVars, $formatBadVar), true, "any")->toBool()) {
            // If any of those options were true, then the request wasn't valid because
            // the right combo of options weren't chosen
            
            // Determine if there was a file uploaded
            $file_exp = ($files) ? "FILE" : "NO_FILE";
            // Set the status to 32
            $api->setStatus(32);

            // Describe the required varibles for this kind of request
            $api->addReqVar("format", "enum", "The format of the upload. [image|audio]", $format);
            $api->addReqVar("type", "string", "The type of resource that the file is associated with (e.g. profile, artist, etc.)", $type);
            $api->addReqVar("id", "integer", "The id of the resource that the file is associated with (e.g. profile, artist, etc.)", $id);
            $api->addReqVar("mfp_upload", "multipart_upload", "The file which is being uploaded", $file_exp);


        } else {
            
            // Do thing differently depending on whether or not the upload is an image or a song
            switch ($format) {
                case "image":
                    // Image upload, so we need to Image Processor Service
                    $imageproc = $this->get("imgproc");
                    // Also get the logger so that we can log things later
                    $logger = $this->get('logger');
                    // Inject the logger into the Image Processor
                    $imageproc->setLogger($logger);

                    if ($url) {
                        // If a URL was sent, call ImageProc with the URL
                        $imageproc->doImageConv($url, "url");
                    } else {
                        // Otherwise call it with the uploaded file
                        $imageproc->doImageConv($_FILES['mfp_upload']['tmp_name'], "upload");
                    }

                    if ($imageproc->conversionSuccess()) {
                        // If the image processed without errors...
                        
                        // Create the Doctrine entity for an image
                        $image = new Entity\Image();
                        // Set the type
                        $image->setType($type);
                        // Set the typeid
                        $image->setTypeId($id);
                        // Set a blank S3key
                        $image->setS3key("");
                        // Set a blank S3url
                        $image->setS3url("");
                        // The S3 options will be set correctly by the AWS service
                        
                        // Tell Doctrine that it needs to persist this entity
                        $em->persist($image);
                        // Tell Doctrine to execute all the necessary queries so far
                        $em->flush();

                        // Get the AWS service
                        $aws = $this->get("aws");
                        // Inject the entity manager
                        $aws->setEM($em);
                        // Injet the current environment variable
                        $aws->setEnv($env);

                        if ($aws->pushGdToS3($imageproc, $image, $type, $id)) {
                            // If the push is successful, return the object URL
                            $api->setDataBlock('image', 'amazon', array("url" => $aws->S3ObjectURL, "resourceId" => $image->getId()));
                        } else {
                            // If the push is unsuccessful, set the status to 71
                            $api->setStatus(71);
                        }

                    } else {
                        // If the image didn't process, return a 91
                        $api->setStatus(91);
                    }
                    break;
                    
                case "audio":
                    // Audio upload so we need the Audio Processor Service
                    $audioproc = $this->get("audioproc");
                    
                    // Inject the API service so that the AudioProc can set the
                    // status code itself
                    $audioproc->apiInject($api);
                    
                    if ($audioproc->doSoundConv($_FILES['mfp_upload']['tmp_name'], "upload")) {
                        // If the AudioProc completes without errors...
                        
                        // Create the Doctrine entity for an audio file
                        $audio = new Entity\Audio();
                        // Set the type
                        $audio->setType($type);
                        // Set a typeid of 0
                        $audio->setTypeId(0);
                        // Set a blank S3key
                        $audio->setS3key("");
                        // Set a blank S3url
                        $audio->setS3url("");
                        // The S3key and S3url will be filled in by the AWS Service
                        // While the typeid will be filled in by Steve's integration point
                        // In word press after step 2 is completed
                        
                        // Tell Doctrine that it needs to persist this entity
                        $em->persist($audio);
                        // Tell Doctrine to execute all the necessary queries so far
                        $em->flush();
                        
                        // Get the audio_id which was generated by SQL for this file
                        $songid = $audio->getId();
                        
                        // Get the AWS service
                        $aws = $this->get("aws");
                        // Inject the Doctrine entity manager
                        $aws->setEM($em);
                        // Inject the environment variable
                        $aws->setEnv($env);
                        
                        if ($audioproc->songlength < 90) {
                            // If the song is less than 90 seconds, we need to discard it
                            $em->remove($audio);
                            // Tell Doctrine to delete the row we just created
                            $em->flush();
                            
                            // Set a helpful status/message combination
                            $api->setStatus(92)->statusMessage("All songs must be greater than 90 seconds long.");
                        } else {
                            // The song is 90 seconds or longer, so go ahead with upload
                            if ($aws->pushAudioToS3($audioproc, $audio, $type, $id)) {
                                // If it pushes up to S3 correctly, return the necessary information to the front end
                                $api->setDataBlock('data', 'mixed', array("duration" => (string)$audioproc->songlength, "png" => $aws->S3ObjectURL['png'], "song" => str_replace(".mp3", "", $aws->S3ObjectURL['mp3']), "songid" => $songid, "wfid" => $aws->wf_id));
                            } else {
                                // If it doesn't push to S3 correctly, set a status of 71
                                $api->setStatus(71);
                            }
                        }
                    } else {
                        // If the Audio Processor doesn't complete successfully...
                        
                        // Get the current status code
                        $currentStatus = $api->getStatus();
                        
                        if ($currentStatus['code'] == 20) {
                            // If the AudioProc didn't change the code, change it now
                            $api->setStatus(92);
                        }
                    }
                    
                    break;
                    
                default:
                    // It's a format that I can't handle, so return a helpful message
                    $api->setStatus(31)->statusMessage("The format option was invalid.");
                    break;
            }
            

        }
        
        // Inject the API into the Response object
        $response = new Response($api);
	
        // Return the response
        return $response;
    }
    
    /*
     * This action handles the /get URL and returns the image
     * in the requested format for a given resource ID.
     */
    public function getAction() {
        // Get the API Builder
        $api = $this->get("apibuilder");
        // Get the API Request service. This service interprets JSON sent in the 
        // $_POST['data'] field.
        $req = $this->get("apirequest");
        
        // Get the 'w' option from the JSON
        $width  = $req->getVar("w");
        // Get the 'h' option from the JSON
        $height = $req->getVar("h");
        // Get the 'id' option from the JSON
        $id     = $req->getVar("id");
        // Get the 'type' option from the JSON
        $type   = $req->getVar("type");
        // Get the 'batch' option from the JSON
        $batch  = $req->getVar("batch");
        // Get the 'format' option from the JSON
        $format = $req->getVar("format");
        
        // Get the Doctrine entity manager
        $em = $this->getDoctrine()->getManager();
        // Get the current environment variable
        $env = $this->container->get('kernel')->getEnvironment();
        
        // For images
        if ($batch) {
            // If batch is set, check for batch required variables
            $imageCheck = $req->strComp("format", "image")->joinAnd()->groupBool(array("h", "w", "batch"), false, "any")->toBool();
        } else {
            // Otherwise check for normal required variables
            $imageCheck = $req->strComp("format", "image")->joinAnd()->groupBool(array("h", "w", "id", "type"), false, "any")->toBool();
        }
        
        // For songs
        $audioCheck = $req->strComp("format", "audio")->joinAnd()->groupBool(array("id", "type"), false, "any")->toBool();

        if ($req->groupBool(array($imageCheck, $audioCheck), true, "any")->toBool()) {
            // Required options not present for the request
            $api->setStatus(32);

            $api->addReqVar("format", "enum", "The format of the upload. [image|audio]", $format);
            $api->addReqVar("id", "integer", "The id of the resource that the file is associated with (e.g. profile, band, etc.)", $id);
            $api->addReqVar("type", "string", "The type of resource that the file is associated with (e.g. profile, artist, etc.)", $type);
            $api->addReqVar("w", "integer", "The desire width of the image", $width);
            $api->addReqVar("h", "integer", "The desire height of the image", $height);
        } else {

            switch ($format) {
                // Asking for an image
                case "image":
                    if ($batch) {
                        // The request contained many image requests, so use a loop
                        foreach ($batch as $val) {
                            
                            try {
                                // Try to grab the entity for the request
                                $image = $this->getDoctrine()->getRepository("EarDishMFPAPIHandlerBundle:Image")->findOneByTypeAndTypeId($val['type'], $val['id']);
                            } catch (\Doctrine\ORM\NoResultException $e) {
                                // If it fails, return status 51
                                // NOTE: this should probably be changed. One bad image in a batch will result
                                // in the whole request being 51'd
                                $api->setStatus(51);

                                $response = new Response($api);

                                return $response;
                            }

                            if ($env == "dev" || $env == "qa") {
                                // It's the dev environment, so set the DEV AWS info
                                $CFIBASE = Services\AwsService::CF_IMAGES_BASE_DEV;
                                $S3BASE = Services\AwsService::CF_IMAGES_BASE_DEV;
                            } elseif ($env == "prod") {
                                // It's the prof environment, so set the normal AWS info
                                $CFIBASE = Services\AwsService::CF_IMAGES_BASE;
                                $S3BASE = Services\AwsService::CF_IMAGES_BASE;
                            }
                            
                            if ($type != "song_waveform" && $type != "demo_waveform") {
                                // We're looking for a normal image, not a waveform
                                try {
                                    // Try to grab the entity for the image based on the width and height requested
                                    $imageFormat = $this->getDoctrine()->getRepository("EarDishMFPAPIHandlerBundle:ImageFormat")->findOneByIdAndFormat($image->getId(), "thumb".$width."x".$height);
                                    
                                    // Build the URL
                                    $resource = $CFIBASE.$imageFormat->getS3key();

                                    // Create the entry for this image
                                    $images[$val['id']] = array('image', 'amazon', $resource);
                                } catch (\Doctrine\ORM\NoResultException $e) {
                                    // Couldn't find a match for the width and height in the DB...
                                    
                                    // ...so grab the Image Processor
                                    $imgproc = $this->get('imgproc');
                                    // And grab the AWS service
                                    $aws = $this->get('aws');

                                    // Inject the Doctrine entity manager
                                    $aws->setEM($em);
                                    // Inject the current environment
                                    $aws->setEnv($env);

                                    // Open the image based on its "base" URL
                                    $imgproc->openImage($S3BASE.$image->getS3key(), "url")->createResource();
                                    // Create a version that is the requested dimensions
                                    $imgproc->ratioCrop($width, $height)->resizeImage(array($width,$height), "stretch")->storeResized("thumb".$width."x".$height);
                                    
                                    // Push the new format to S3
                                    $aws->pushSingleGdToS3($imgproc, $image, $type, $id);

                                    // And create the entry for this image
                                    $images[$val['id']] = array('image', 'amazon', $aws->S3ObjectURL);
                                }
                            } else {
                                // We are dealing with a waveform, so there is no sizing necessary
                                $images[$val['id']] = array('image', 'amazon', $CFIBASE.$image->getS3key());
                            }

                        }
                        
                        // Put the entire batch image result into the data block
                        $api->setDataBlock('data', 'amazon', $images);
                    } else {
                        // This is not a batch request but a single request
                        try {
                            // Try to find the image in the DB
                            $image = $this->getDoctrine()->getRepository("EarDishMFPAPIHandlerBundle:Image")->findOneByTypeAndTypeId($type, $id);
                        } catch (\Doctrine\ORM\NoResultException $e) {
                            // Can't find the base image, so set a status 51
                            $api->setStatus(51);
                            
                            $response = new Response($api);

                            return $response;
                        }

                        if ($env == "dev" || $env == "qa") {
                            // It's the dev environment, so set the DEV AWS info
                            $CFIBASE = Services\AwsService::CF_IMAGES_BASE_DEV;
                            $S3BASE = Services\AwsService::CF_IMAGES_BASE_DEV;
                        } elseif ($env == "prod") {
                            // It's the prof environment, so set the normal AWS info
                            $CFIBASE = Services\AwsService::CF_IMAGES_BASE;
                            $S3BASE = Services\AwsService::CF_IMAGES_BASE;
                        }
                        
                        if ($type != "song_waveform" && $type != "demo_waveform") {
                            // We're looking for a normal image, not a waveform
                            try {
                                // Try to grab the entity for the image based on the width and height requested
                                $imageFormat = $this->getDoctrine()->getRepository("EarDishMFPAPIHandlerBundle:ImageFormat")->findOneByIdAndFormat($image->getId(), "thumb".$width."x".$height);

                                // Build the URL
                                $resource = $CFIBASE.$imageFormat->getS3key();

                                // Create the entry for this image
                                $api->setDataBlock('image', 'amazon', $resource, $image->getDateModified());
                            } catch (\Doctrine\ORM\NoResultException $e) {
                                // Couldn't find a match for the width and height in the DB...
                                    
                                // ...so grab the Image Processor
                                $imgproc = $this->get('imgproc');
                                // And grab the AWS service
                                $aws = $this->get('aws');

                                // Inject the Doctrine entity manager
                                $aws->setEM($em);
                                // Inject the current environment
                                $aws->setEnv($env);

                                // Open the image based on its "base" URL
                                $imgproc->openImage($S3BASE.$image->getS3key(), "url")->createResource();
                                // Create a version that is the requested dimensions
                                $imgproc->ratioCrop($width, $height)->resizeImage(array($width,$height), "stretch")->storeResized("thumb".$width."x".$height);

                                // Push the new format to S3
                                $aws->pushSingleGdToS3($imgproc, $image, $type, $id);

                                // And create the entry for this image
                                $api->setDataBlock('image', 'amazon', $aws->S3ObjectURL);
                            }
                        } else {
                            // We are dealing with a waveform, so there is no sizing necessary
                            $api->setDataBlock('image', 'amazon', $CFIBASE.$image->getS3key(), $image->getDateModified());
                        }
                    }
                    break;
                
                // The request is for audio files
                case "audio":
                    try {
                        // Try to find the base entry for the requested resource
                        $audio = $this->getDoctrine()->getRepository("EarDishMFPAPIHandlerBundle:Audio")->findOneByTypeAndTypeId($type, $id);
                    } catch (\Doctrine\ORM\NoResultException $e) {
                        // If it can't find the entry, set status 51
                        $api->setStatus(51);

                        $response = new Response($api);

                        return $response;
                    }
                    
                    if ($env == "dev" || $env == "qa") {
                        // It's the dev environment, so set the DEV AWS info
                        $CFABASE = Services\AwsService::CF_AUDIO_BASE_DEV;
                    } elseif ($env == "prod") {
                        // It's the prof environment, so set the normal AWS info
                        $CFABASE = Services\AwsService::CF_AUDIO_BASE;
                    }
                    
                    try {
                        // Grab the MP3 format entry
                        $audioFormat = $this->getDoctrine()->getRepository("EarDishMFPAPIHandlerBundle:AudioFormat")->findOneByIdAndFormat($audio->getId(), "mp3");

                        // Remove the extension, the front end will place the appropriate extension
                        $resource = $CFABASE.str_replace(".mp3", "", $audio->getS3key());

                        // Set the data block with the data to return
                        $api->setDataBlock('audio', 'amazon', $resource, $audioFormat->getDateModified());
                    } catch (\Doctrine\ORM\NoResultException $e) {
                        // Otherwise something went wrong, set a status 51
                        $api->setStatus(51);
                    }
                    break;
            }
            
        }
        
        // Inject the API into the Response object
        $response = new Response($api);
        
        // Return the response
        return $response;
    }
    
    /*
     * This action handles the audio upload confirmation step
     */
    public function demosliceAction() {
        // Get the API Builder
        $api = $this->get("apibuilder");
        // Get the API Request service. This service interprets JSON sent in the 
        // $_POST['data'] field.
        $req = $this->get("apirequest");
        
        // Get the 'id' option from the JSON
        $id     = $req->getVar("id");
        // Get the 'wfid' option from the JSON (waveform id)
        $wfid   = $req->getVar("wfid");
        // Get the 'typeid' option from the JSON
        $typeid = $req->getVar("typeid");
        // Get the 'start' option from the JSON
        $start  = $req->getVar("start");
        // Get the 'length' option from the JSON
        $length = $req->getVar("length");
        
        // Get the Doctrine entity manager
        $em = $this->getDoctrine()->getManager();
        // Get the environment variable
        $env = $this->container->get('kernel')->getEnvironment();
        
        // Check to make sure that the start option is set to a valid value
        if (intval($start) == 0) {
            $startB = true;
        } elseif ($start) {
            $startB = true;
        } else {
            $startB = false;
        }
        
        if ($req->groupBool(array("id", "length", "typeid", "wfid", $startB), false, "any")->toBool()) {
            // The required variables are not set, so return status 32
            $api->setStatus(32);

            $api->addReqVar("start", "float", "The starting timecode (in seconds) to begin clipping the demo.", $start);
            $api->addReqVar("length", "integer", "The length of demo in seconds.", $length);
            $api->addReqVar("id", "integer", "The MFP song ID.", $id);
            $api->addReqVar("typeid", "integer", "The id of the resource that the file is associated with (e.g. profile, artist, etc.)", $typeid);
        } else {
            // Grab the requested audio
            $audio = $this->getDoctrine()->getRepository('EarDishMFPAPIHandlerBundle:Audio')->findOneById($id);
            // And update the typeid from Steve
            $audio->setTypeId($typeid);
            
            // Tell Doctrine to persist the entity
            $em->persist($audio);
            // Tell Doctrine to update the database
            $em->flush();
            
            // Grab the requested waveform
            $waveform = $this->getDoctrine()->getRepository('EarDishMFPAPIHandlerBundle:Image')->findOneById($wfid);
            // And update the typeid from Steve
            $waveform->setTypeId($typeid);
            
            // Tell Doctrine to persist the entity
            $em->persist($waveform);
            // Tell Doctrine to update the database
            $em->flush();
            
            // Create the base S3key for the demo
            $wavKey = str_replace(".mp3", ".wav", $audio->getS3key());
            
            // Get the Audio Processor service
            $audioproc = $this->get("audioproc");

            // Inject the API service in the Audio Processor
            $audioproc->apiInject($api);

            if ($env == "dev" || $env == "qa") {
                // It's the dev environment, so set the DEV AWS info
                $AUDDIRBASE = Services\AwsService::CF_AUDIO_BASE_DEV;
            } elseif ($env == "prod") {
                // It's the prof environment, so set the normal AWS info
                $AUDDIRBASE = Services\AwsService::CF_AUDIO_BASE;
            }

            if ($audioproc->doDemoClip($AUDDIRBASE.$wavKey, "cloud", $start, $length)) {
                // If the Audio Processor creates the demo clip successfully
                
                // Create a new entity for the demo
                $demoAudio = new Entity\Audio();
                // Set the type to demo
                $demoAudio->setType("demo");
                // Set the typeid to the one provided by Steve
                $demoAudio->setTypeId($typeid);
                // Set a blank S3key
                $demoAudio->setS3key("");
                // Set a blank S3url
                $demoAudio->setS3url("");
                // The S3key and S3url will be filled in by the AWS Service
                
                // Tell Doctrine to persist the entity
                $em->persist($demoAudio);
                // Tell Doctrine to update the database
                $em->flush();

                // Get the AWS Service
                $aws = $this->get("aws");
                // Inject the Doctrine entity manager
                $aws->setEM($em);
                // Inject the current environment variable
                $aws->setEnv($env);

                if ($aws->pushAudioToS3($audioproc, $demoAudio, "demo", $typeid)) {
                    // If it pushed to S3, fill the data block with information for the front end
                    $api->setDataBlock('data', 'mixed', array("png" => $aws->S3ObjectURL['png'], "song" => str_replace(".mp3", "", $aws->S3ObjectURL['mp3']), "songid" => $demoAudio->getId()));
                } else {
                    // If it couldn't push to S3, set the status to 71
                    $api->setStatus(71);
                }
            } else {
                // The audio proc failed, so get the current status
                $currentStatus = $api->getStatus();

                if ($currentStatus['code'] == 20) {
                    // If the Audio Processor didn't change the status, change it now
                    $api->setStatus(92);
                }
            }
        }
            
        // Inject the API into the Response object
        $response = new Response($api);
        
        // Return the response
        return $response;
    }
    
    public function deleteAction() {
        $api = $this->get("apibuilder");
        $req = $this->get("apirequest");
        
        $id = $req->getVar("id");
        $type = $req->getVar("type");
        $format = $req->getVar("format");
        $secret = $req->getVar("secret");
        
        $em = $this->getDoctrine()->getManager();
        
        $setting = $this->getDoctrine()->getRepository("EarDishMFPAPIHandlerBundle:Settings")->findOneByKey("secret");
        
        $secretCorrect = $req->strComp("secret", $setting->getValue());
        
        $req->strComp("format", "image")->joinOr()->strComp("format", "audio")->joinAnd()->groupBool(array("id", "type", "format", "secret", $secretCorrect), false, "any");
        
        if ($req->toBool()) {
            $api->setStatus(32);

            $api->addReqVar("id", "integer", "The id of the resource that the file is associated with (e.g. profile, band, etc.)", $id);
            $api->addReqVar("format", "enum", "The format of the upload. [image|audio]", $format);
            $api->addReqVar("type", "string", "The type of resource that the file is associated with (e.g. profile, artist, etc.)", $type);
        } else {
            if ($req->strComp("format", "image")->toBool()) {
                $image = $this->getDoctrine()->getRepository("EarDishMFPAPIHandlerBundle:Image")->findOneByTypeAndTypeId($type, $id);
                
                foreach ($image->getFormats() as $format) {
                    $em->remove($format);
                }
                
                $em->remove($image);
                $em->flush();
                
                $api->setStatus(1);
            } else {
                $api->setStatus(2);
            }
        }
        
        $response = new Response($api);
        
        return $response;
    }
} // end class DefaultController

