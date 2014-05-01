<?php

namespace EarDish\Common\Resources;

class ApiRequest {
    
    private $state;
    private $posted = array();
    private $states = array();
    private $joins = array();
    
    function __construct() {
        global $kernel;

        if ('AppCache' == get_class($kernel)) {
            $kernel = $kernel->getKernel();
        }

        $this->container = $kernel->getContainer();
        
        if (isset($_REQUEST['data'])) {
            $data = json_decode($_REQUEST['data'], true);
            
            if (!is_null($data)) {
                $this->posted = $data;
            }
        }
        
    }
    
    public function getVar($var, $default = null) {
        
        if (isset($this->posted[$var])) {
            return $this->posted[$var];
        } else {
            if (!is_null($default)) {
                return $default;
            } else {
                return false;
            }
        }
        
    }
    
    public function groupBool($var = array(), $mode = true, $type = "all") {
        
        if ($type == "all") {
            $result = true;
        } else {
            $result = false;
        }
        
        foreach ($var as $val) {
            if (is_bool($val)) {
                $bool = $val;
            } else {
                $bool = $this->testVar($val);
            }
            switch ($mode) {
                case true:
                    if (!$bool && $type == "all") {
                        $this->states[] = false;
                        $this->state = false;
                        return $this;
                    }
                    
                    if ($bool && $type == "any") {
                        $this->states[] = true;
                        $this->state = true;
                        return $this;
                    }
                    break;
                    
                case false:
                    if ($bool && $type == "all") {
                        $this->states[] = false;
                        $this->state = false;
                        return $this;
                    }
                    
                    if (!$bool && $type == "any") {
                        $this->states[] = true;
                        $this->state = true;
                        return $this;
                    }
                    break;
            }
        }
        
        $this->states[] = $result;
        $this->state = $result;
        
        return $this;
        
    }
    
    public function strComp($var, $val, $mode = "lax") {
        
        $logger = $this->container->get("logger");
        
        if (!isset($this->posted[$var])) {
            $result = false;
        } else {

            if ($mode == "strict") {
                $result = ($this->posted[$var] === $val);
            } elseif ($mode == "regx") {
                $match = preg_match($val, $this->posted[$var]);

                if ($match === false) {
                    $logger->err("Invalid Regular Expression in API Request service: ".$val);
                    
                    $result = true;
                }

                if ($match === 1) {
                    $result = true;
                } elseif ($match === 0) {
                    $result = false;
                }
            } else {
                $result = ($this->posted[$var] == $val);
            }
            
        }
        
        $this->states[] = $result;
        $this->state = $result;
        
        return $this;
        
    }
    
    public function joinAnd() {
        $this->joins[] = "AND";
        
        return $this;
    }
    
    public function joinOr() {
        $this->joins[] = "OR";
        
        return $this;
    }
    
    public function toBool() {
        
        $curMode = "none";
        $statement = "";
        
        if (count($this->states) > 1) {
            
            for ($key = 0;$key < count($this->states);$key++) {
                if (isset($this->joins[$key])) {
                    if ($curMode != "OR" && $this->joins[$key] == "OR") {
                        $statement .= "(";
                        $curMode = "OR";
                    }
                }

                $statement .= " ".var_export($this->states[$key], true)." ";

                $next = $key+1;

                if (isset($this->joins[$key]) && isset($this->states[$next])) {
                    if ($curMode == "OR" && $this->joins[$key] == "AND") {
                        $statement .= ") ";
                        $curMode = "AND";
                    }
                } else {
                    if ($curMode == "OR") {
                        $statement .= ")";
                    }
                }

                if (isset($this->states[$next]) && $this->joins[$key] == "OR") {
                    $statement .= "||";
                } elseif (isset($this->states[$next]) && $this->joins[$key] == "AND") {
                    $statement .= "&&";
                }
            }

        } elseif (count($this->states) == 1) {
            
            $statement = var_export($this->states[0], true);
            
        } else {
            
            $statement = true;
            
        }
        
        $this->states = array();
        $this->joins = array();
        $this->state = null;
        
        return eval("return (".$statement.");");
        
    }
    
    public function testVar($var, $invert = false) {
        
        if (isset($this->posted[$var]) && !empty($this->posted[$var]) && !is_null($this->posted[$var]) && $this->posted[$var]) {
            return ($invert) ? false : true;
        } else {
            return ($invert) ? true : false;
        }
        
    }
    
}
