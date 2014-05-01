<?php

namespace EarDish\MFP\APIHandlerBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * ImageFormatRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ImageFormatRepository extends EntityRepository
{
    public function findOneByIdAndFormat($id, $format) {
        return $this->getEntityManager()
                ->createQuery(
                        'SELECT i 
                            FROM EarDishMFPAPIHandlerBundle:ImageFormat i 
                            WHERE i.format = :format AND i.image = :id')
                ->setParameter("format", $format)
                ->setParameter("id", $id)
                ->getSingleResult();
    }
}
