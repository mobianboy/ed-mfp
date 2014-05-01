<?php

namespace EarDish\Common\Resources;

class ApiBuilder {
    
    public $status = 20;
    public $message;
    public $response = array();
    public $response_struct = array();
    public $req_vars = array();
    public $req_vars_desc = array();
    public $data = array();
    public $flash;
    
    const STATUS_NODATA     = 01;
    const STATUS_NORMAL     = 20;
    const STATUS_REQBADVAR  = 30;
    const STATUS_REQVARHINT = 32;
    const STATUS_AWSFAIL    = 71;
    const STATUS_PROCERR    = 91;
    
    function __construct($code = 20, $message = null) {
        $this->status = $code;
        
        $this->statusMessage($message);
        
        $this->response_struct = $this->arrayStruct();
        
        return $this;
    }
    
    public function addResponse($response) {
        
        if (is_array($response)) {
            foreach ($response as $key => $val) {
                $this->response[$key] = $val;
            }
        }
        
        return $this;
        
    }
    
    public function addReqVar($varName, $varType, $varDesc, $varVal) {
        
        $this->req_vars[$varName] = $varVal;
        
        $this->req_vars_desc[$varName] = array("var_type" => $varType, "var_disposition" => $varDesc);
        
        return $this;
        
    }
    
    public function setDataBlock($type, $origin, $data, $lastUpdated = null, $respType = "uga") {
        
        if ($lastUpdated instanceof \DateTime) {
            $date = $lastUpdated;
        } elseif (is_string($lastUpdated)) {
            $date = new \DateTime($lastUpdated);
        } else {
            $date = new \DateTime();
        }
        
        $curDate = new \DateTime();
        
        $interval = $date->diff($curDate);
        $seconds = ($interval->format("%a")*86400) + ($interval->format("%h")*3600) + ($interval->format("%i")*60) + $interval->format("%s");
        
        $this->data['resource'] = array('type' => $type, 'origin' => $origin, 'source' => $data);
        $this->data['meta'] = array('date_generated' => date('Y/m/d H:i:s'), 'generation_type' => $respType, 'data_age' => $seconds);
        
        return $this;
        
    }
    
    public function statusMessage($message = null) {
        
        if (is_null($message)) {
            switch ($this->status) {
                case 1:
                    $message = "The operation completed with no data to return.";
                    break;
                
                case 2:
                    $message = "No action was performed.";
                    break;
                
                case 20:
                    $message = "The operation completed and data has been returned.";
                    break;
                
                case 21:
                    $message = "The operation completed successfully and an automatic substitution of data has been performed, either due to availability or to the request being forwarded.";
                    break;
                
                case 30:
                    $message = "The request was invalid and there is no additional information.";
                    break;
                
                case 32:
                    $message = "The request was invalid and the required variables have been returned.";
                    break;
                
                case 35:
                    $message = "The binary user input was of an unsupported type.";
                    break;
                
                case 50:
                    $message = "No route for the requested resource could be found.";
                    break;
                
                case 51:
                    $message = "The requested resource was routed correctly but contained no data.";
                    break;
                
                case 52:
                    $message = "The requested resource was routed correctly and contained no data, but a default data set was returned.";
                    break;
                
                case 71:
                    $message = "Amazon Web Services failed or was unavailable.";
                    break;
                
                case 91:
                    $message = "The application encountered an unspecified error while processing the request.";
                    break;
                
                case 92:
                    $message = "The Audio Processing tool was unable to successfully complete.";
                    break;
                
                case 93:
                    $message = "The file uploaded was of an unsupported type.";
                    break;
                
                default:
                    $message = "There is no additional information about this status.";
                    break;
            }
        }
        
        $this->message = $message;
        
        return $this;
        
    }
    
    public function setStatus($code = 20) {
        
        $this->status = $code;
        
        $this->statusMessage();
        
        return $this;
        
    }
    
    public function getStatus() {
        return array("code" => $this->status, "message" => $this->message);
    }
    
    private function arrayStruct() {
        
        $struct = array(
            "status" => array(
                "code" => "int",
                "message" => "string"
            ),
            "data" => array(
                "resource" => array(
                    "type" => "enum (image|audio|text|html|css|js|data)",
                    "origin" => "enum (eardish|amazon|mixed)",
                    "source" => "string (data response)"
                ),
                "meta" => array(
                    "date_generated" => "YYYY/MM/DD HH:MM:SS",
                    "generation_type" => "enum (uga|sga)"
                )
            ),
            "flash" => array(
                "resource" => array(
                    "type" => "enum (error|alert|notice|social)",
                    "origin" => "enum (eardish|amazon)",
                    "source" => "string"
                ),
                "meta" => array(
                    "date_generated" => "YYYY/MM/DD HH:MM:SS"
                )
            )
        );
        
        return $struct;
        
    }
    
    private function specialBuckets() {
        
        switch ($this->status) {
            case 20:
            case 21:
            case 52:
                $this->response['data'] = $this->data;
                break;
            
            case 91:
            case 92:
            case 93:
                if ($this->data) {
                    $this->response['data'] = $this->data;
                }
                break;
            
            case 32:
                foreach ($this->req_vars as $name => $value) {
                    $this->response['req_vars'][$name]['value'] = $value;
                    $this->response['req_vars'][$name]['var_type'] = $this->req_vars_desc[$name]['var_type'];
                    $this->response['req_vars'][$name]['var_disposition'] = $this->req_vars_desc[$name]['var_disposition'];
                }
                break;
        }
        
        if (count($this->flash)) {
            
        }
        
    }
    
    private function buildStatusBlock() {
        
        $status = array("code" => $this->status, "message" => $this->message);
        
        $this->response['status'] = $status;
        
    }
    
    function __toString() {
        $this->buildStatusBlock();
        
        $this->specialBuckets();
        
        return json_encode($this->response, JSON_FORCE_OBJECT);
    }
    
}