<?php

namespace EarDish\Common\Resources;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpFoundation\Response;

class ExceptionHandler extends Controller {

    public function unknownException(GetResponseForExceptionEvent $event) {
        
        $exception = $event->getException();
        
        $api = $this->get("apibuilder");
        
        $api->setStatus(91)->statusMessage();
        
        $response = new Response($api);
        
        return $response;
        
    }

}