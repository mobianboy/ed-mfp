<?php

namespace EarDish\MFP\Services;

class ImageProc {
    /**
     * The injected API service
     * 
     * @var /EarDish/Common/Resources/ApiBuilder 
     */
    private $api;
    /**
     * The GD stream currently being operated on
     * 
     * @var stream 
     */
    public $workingResource;
    /**
     * A variable to save and restore resources
     * 
     * @var array 
     */
    public $params = array();
    /**
     * This contains the binary contents of an image
     * 
     * @var binary 
     */
    public $imageString;
    /**
     * A variable to save and restore resources
     * 
     * @var array  
     */
    public $source = array();
    /**
     * An array of resized images
     * 
     * @var array 
     */
    public $resized = array();
    /**
     * The injected logger service
     * 
     * @var type 
     */
    public $logger;
    /**
     * Tracks whether to source failed to load
     * 
     * @var boolean 
     */
    public $sourceFail = false;
    
    /**
     * The Symfony container
     * 
     * @var 
     */
    private $container;
    
    function __construct() {
        global $kernel;

        if ('AppCache' == get_class($kernel)) {
            $kernel = $kernel->getKernel();
        }

        $this->container = $kernel->getContainer();
        
        $this->api = $this->container->get("apibuilder");
    }
    
    /**
     * Does a standard image conversion to the default thumb sizes
     * 
     * @param string $source
     * @param string $type
     * @return \EarDish\MFP\Services\ImageProc
     */
    public function doImageConv($source, $type) {
        $this->imageString = "";
        $this->workingResource = "";
        $this->openImage($source, $type)->createResource()->generateThumbs();
        return $this;
    }
    
    /**
     * The injector method for the logger
     * 
     * @param type $logger
     */
    public function setLogger($logger) {
        $this->logger = $logger;
        
        $this->logger->info("Logger initialized in ImageProc");
    }
    
    /**
     * Determines whether the conversion succeeded
     * 
     * @return boolean
     */
    public function conversionSuccess() {
        if (count($this->resized)) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Generates a default set of thumbs in the $this->resized variable
     * 
     * @return \EarDish\MFP\Services\ImageProc
     */
    public function generateThumbs() {
        
        if ($this->sourceFail || !$this->workingResource) {
            return $this;
        }
        
        $sizes = array("188","144","45","42","40","38","28","26","20","18");
        
        $state = $this->storeState();
        
        $this->ratioCrop(1, 1);
        
        
        foreach ($sizes as $val) {
            $this->resizeImage(array($val,$val), "stretch")->storeResized("thumb".$val."x".$val);
        }
        
        $this->restoreState($state);
        $w = 100;
        $h = 44;
        $this->ratioCrop($w, $h)->resizeImage(array($w,$h), "stretch")->storeResized("thumb".$w."x".$h);
        
        $this->restoreState($state);
        $w = 159;
        $h = 41;
        $this->ratioCrop($w, $h)->resizeImage(array($w,$h), "stretch")->storeResized("thumb".$w."x".$h);
        
        $this->restoreState($state);
        $w = 131;
        $h = 90;
        $this->ratioCrop($w, $h)->resizeImage(array($w,$h), "stretch")->storeResized("thumb".$w."x".$h);
        
        $this->restoreState($state);
        $w = 200;
        $h = 124;
        $this->ratioCrop($w, $h)->resizeImage(array($w,$h), "stretch")->storeResized("thumb".$w."x".$h);
        
        $this->restoreState($state);
        $w = 200;
        $h = 166;
        $this->ratioCrop($w, $h)->resizeImage(array($w,$h), "stretch")->storeResized("thumb".$w."x".$h);
        
        $this->restoreState($state);
        $w = 374;
        $h = 250;
        $this->ratioCrop($w, $h)->resizeImage(array($w,$h), "stretch")->storeResized("thumb".$w."x".$h);
        
        $this->restoreState($state);
        $w = 414;
        $h = 166;
        $this->ratioCrop($w, $h)->resizeImage(array($w,$h), "stretch")->storeResized("thumb".$w."x".$h);
        
        $this->restoreState($state);
        $w = 441;
        $h = 166;
        $this->ratioCrop($w, $h)->resizeImage(array($w,$h), "stretch")->storeResized("thumb".$w."x".$h);
        
        $this->restoreState($state);
        $w = 504;
        $h = 306;
        $this->ratioCrop($w, $h)->resizeImage(array($w,$h), "stretch")->storeResized("thumb".$w."x".$h);
        
        $this->restoreState($state);
        $w = 551;
        $h = 306;
        $this->ratioCrop($w, $h)->resizeImage(array($w,$h), "stretch")->storeResized("thumb".$w."x".$h);
        
        $this->restoreState($state);
        $w = 570;
        $h = 378;
        $this->ratioCrop($w, $h)->resizeImage(array($w,$h), "stretch")->storeResized("thumb".$w."x".$h);
        
        $this->restoreState($state);
        
        return $this;
        
    }
    
    /**
     * Returns the $this->resized array of thumb resources
     * 
     * @return array
     */
    public function getResizedResources() {
        return $this->resized;
    }

    /**
     * Sets all the information for the source image
     * 
     * @param string $source
     * @param string $type
     * @return \EarDish\MFP\Services\ImageProc
     */
    public function openImage($source, $type) {
        
        switch ($type) {
            
            case "url":
                $parts = explode(".", $source);
                $ext = array_pop($parts);
            case "upload":
                $contents = file_get_contents($source);
                $contents = trim($contents);
                if ($contents === false || empty($contents)) {
                    $this->sourceFail = true;
                    return $this;
                } else {
                    $this->sourceFail = false;
                }
                $mime = "";
                if ($type == "upload") {
                    if (function_exists("finfo_open")) {
                        $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $source);
                    } else {
                        $mime = mime_content_type($source);
                    }
                }
                
                $source_type = ($this->mimeToType($mime)) ? $this->mimeToType($mime) : $ext;
                break;
            case "cloud":
                
                break;
            
        }
        
        if (!$source_type) {
            $this->api->setStatus(35);
        }
        
        $this->imageString = $contents;
        $this->source['mime'] = $mime;
        if ($type == "url") {
            $this->source['type'] = $ext;
        } else {
            $this->source['type'] = $source_type;
        }
        
        return $this; 
        
    }
    
    /**
     * Creates a GD resource in $this->workingResource out of $this->imageString
     * 
     * @return \EarDish\MFP\Services\ImageProc
     */
    public function createResource() {

        if ($this->sourceFail || !$this->imageString || empty($this->imageString)) {
            return $this;
        }
        
        $resource = imagecreatefromstring($this->imageString);
        
        if ($resource) {
            $this->imageString = "";
            $this->workingResource = $resource;
        } else {
            //$this->logger->err("The image resource couldn't be created");
	          $this->sourceFail = true;
		        return $this;
        }
        
        $this->setParams();
        
        return $this;
        
    }
    
    /**
     * Generates a save state of the current working resource
     * 
     * @return type
     */
    public function storeState() {
        
        $state['workingResource'] = $this->workingResource;
        $state['params'] = $this->params;
        $state['source'] = $this->source;
        
        return $state;
        
    }
    
    /**
     * Restores from a state array generated by $this->storeState()
     * 
     * @param array $state
     * @return \EarDish\MFP\Services\ImageProc
     */
    public function restoreState($state) {
        
        $this->workingResource = $state['workingResource'];
        $this->params = $state['params'];
        $this->source = $state['source'];
        
        return $this;
        
    }
    
    /**
     * Stores the current working resource in $this->resized[$name]
     * 
     * @param string $name
     * @return \EarDish\MFP\Services\ImageProc
     */
    public function storeResized($name) {
        $this->resized[$name] = $this->workingResource;
        
        return $this;
    }
    
    private function mimeToType($mime) {
        
        switch ($mime) {
            
            case "image/gif":
                return "gif";
                break;
            case "image/jpeg":
                return "jpg";
                break;
            case "image/png":
                return "png";
                break;
            default:
                return false;
                break;
            
        }
        
    }
    
    /*
     * Sets the image parameters for the given resource
     * 
     * @param A GD image resource; if none is provided assumes $this->workingResource
     */
    private function setParams($resource = null) {
        
        if (is_null($resource)) {
            $this->params = $this->generateParams($this->workingResource);
        } else {
            $this->params = $this->generateParams($resource);
        }
        
        return $this;
        
    }
    
    /*
     * Generates image paramters from a resource
     * 
     * @param $resource A GD image resource
     * @return Array
     */
    private function generateParams($resource) {
        
        $params['x'] = imagesx($resource);
        $params['y'] = imagesy($resource);
        $params['truecolor'] = imageistruecolor($resource);
        
        return $params;
        
    }
    
    /**
     * Performs a crop where the image is first resized the largest available size
     * that will still crop to the correct dimensions without any whitespace
     * 
     * @param int $x
     * @param int $y
     * @return \EarDish\MFP\Services\ImageProc
     */
    public function ratioCrop($x, $y) {
        
        // Determine the direction of the crop... 
        // dir will be larger than x if the y was the larger change
        // dir will be smaller than x if the y was the smaller change
        $dir = ($this->params['x']*$y)/$this->params['y'];
        
        if ($dir == $x) {
            // The size hasn't changed, so take no action
            return $this;
        } elseif ($dir > $x) {
            // The y was the larger change, so grab the new x dimension
            $newx = round(($this->params['y']*$x)/$y);
            
            // Take half the new x to determine where the clipping offset should be
            $half_diff = round(($this->params['x']-$newx)/2);
            
            // Crop the image
            return $this->cropImage(array($newx, $this->params['y']), array($half_diff, 0));
        } elseif ($dir < $x) {
            // The y was the smaller change, so grab the new y
            $newy = round(($this->params['x']*$y)/$x);
            
            $half_diff = round(($this->params['y']-$newy)/2);
            
            return $this->cropImage(array($this->params['x'], $newy), array(0, $half_diff));
        }
        
    }
    
    /*
     * Crops an image to the provided dimensions in $size, using $offset as the (x,y) offset
     * and sets the new GD resource to $this->workingResource
     * 
     * @param array $size An array containing the desired crop size (x, y)
     * @param array $offset Offset for image crop
     * @return ImageProc
     */
    private function cropImage($size, $offset = array(0, 0)) {
        
        // Create new GD resource
        $canvas = imagecreatetruecolor($size[0], $size[1]);
        
        // Copy working resource into the new resource
        imagecopy($canvas, $this->workingResource, 0, 0, $offset[0], $offset[1], $this->params['x'], $this->params['y']);
        
        // Set the working resource to the new resource
        $this->workingResource = $canvas;
        
        // Set parameters for the new working resource
        $this->setParams();
        
        return $this;
        
    }
    
    /*
     * Resizes a GD resource to the specified dimensions using the specified resizing strategy
     * and sets the new GD resource to $this->workingResource
     * 
     * @param $size Desired resampled size
     * @param $mode aspect|stretch In aspect mode will scale to maintain aspect ratio; In stretch mode will force image into $size
     * @return ImageProc
     */
    public function resizeImage($size, $mode = "aspect") {
        
        switch ($mode) {
            case "stretch":
                // Simply stretch the image to the given dimensions
                $newx = $size[0];
                $newy = $size[1];
                break;
            case "max":
                // Determine which side will hit the new size last and use that ratio
                // to scale the second side with white for the extra margins
                $ratiox = $this->params['x']/$size[0];
                $ratioy = $this->params['y']/$size[1];
                
                $ratio = $ratiox;
                
                if ($ratioy < $ratiox) {
                    $ratio = $ratioy;
                }
                
                list($newx, $newy) = $this->applyRatio(array($this->params['x'], $this->params['y']), array($ratio, $ratio));
                break;
            case "aspect":
            default:
                // Determine which side will hit the new size first and use that ratio
                // to scale the second side
                $ratiox = $this->params['x']/$size[0];
                $ratioy = $this->params['y']/$size[1];
                
                $ratio = $ratiox;
                
                if ($ratioy > $ratiox) {
                    $ratio = $ratioy;
                }
                
                list($newx, $newy) = $this->applyRatio(array($this->params['x'], $this->params['y']), array($ratio, $ratio));
                break;
        }
        
        $canvas = imagecreatetruecolor($newx, $newy);
        
        imagecopyresampled($canvas, $this->workingResource, 0, 0, 0, 0, $newx, $newy, $this->params['x'], $this->params['y']);
        
        $this->workingResource = $canvas;
        
        $this->setParams();
        
        return $this;
        
    }
    
    /*
     * Applies a ratio to the given dimensions
     * 
     * @param $dimensions The source dimensions array (x, y)
     * @param $ratios The ratios to divide each dimension by (ratio x, ratio y)
     * @return Array
     */
    private function applyRatio($dimensions, $ratios) {
        
        $new['x'] = round($dimensions[0]/$ratios[0]);
        $new['y'] = round($dimensions[1]/$ratios[1]);
        
        return $new;
        
    }
    
}
