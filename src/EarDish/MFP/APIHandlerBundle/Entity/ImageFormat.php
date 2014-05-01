<?php

namespace EarDish\MFP\APIHandlerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="EarDish\MFP\APIHandlerBundle\Entity\ImageFormatRepository")
 * @ORM\Table(name="mfp_image_formats")
 * @ORM\HasLifecycleCallbacks()
 */
class ImageFormat
{
    /**
     * @ORM\ManyToOne(targetEntity="Image", inversedBy="formats")
     * @ORM\JoinColumn(name="image_id", referencedColumnName="id")
     */
    protected $image;
    
    /**
     * @ORM\Column(type="string", name="format")
     */
    protected $format;
    
    /**
     * @ORM\Column(type="string", name="s3_url")
     */
    protected $s3url;
    
    /**
     * @ORM\Column(type="string", name="s3_key")
     */
    protected $s3key;
    
    /**
     * @ORM\Column(type="datetime", name="date_created")
     */
    protected $dateCreated;
    
    /**
     * @ORM\Column(type="datetime", name="date_modified")
     */
    protected $dateModified;
    
    /**
     * @ORM\Id
     * @ORM\Column(type="bigint", name="format_id")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    
    
    
    public function __construct() 
    {
         
    }
    
    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue()
    {
        $this->dateCreated = new \DateTime();
        $this->dateModified = new \DateTime();
    }
    
    /**
     * @ORM\PreUpdate
     */
    public function setChangedOnValue()
    {
        $this->dateModified = new \DateTime();
    }

    /**
     * Set format
     *
     * @param string $format
     * @return ImageFormat
     */
    public function setFormat($format)
    {
        $this->format = $format;
    
        return $this;
    }

    /**
     * Get format
     *
     * @return string 
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * Set s3url
     *
     * @param string $s3url
     * @return ImageFormat
     */
    public function setS3url($s3url)
    {
        $this->s3url = $s3url;
    
        return $this;
    }

    /**
     * Get s3url
     *
     * @return string 
     */
    public function getS3url()
    {
        return $this->s3url;
    }

    /**
     * Set s3key
     *
     * @param string $s3key
     * @return ImageFormat
     */
    public function setS3key($s3key)
    {
        $this->s3key = $s3key;
    
        return $this;
    }

    /**
     * Get s3key
     *
     * @return string 
     */
    public function getS3key()
    {
        return $this->s3key;
    }

    /**
     * Set dateCreated
     *
     * @param \DateTime $dateCreated
     * @return ImageFormat
     */
    public function setDateCreated($dateCreated)
    {
        $this->dateCreated = $dateCreated;
    
        return $this;
    }

    /**
     * Get dateCreated
     *
     * @return \DateTime 
     */
    public function getDateCreated()
    {
        return $this->dateCreated;
    }

    /**
     * Set dateModified
     *
     * @param \DateTime $dateModified
     * @return ImageFormat
     */
    public function setDateModified($dateModified)
    {
        $this->dateModified = $dateModified;
    
        return $this;
    }

    /**
     * Get dateModified
     *
     * @return \DateTime 
     */
    public function getDateModified()
    {
        return $this->dateModified;
    }

    /**
     * Set image
     *
     * @param \EarDish\MFP\APIHandlerBundle\Entity\Image $image
     * @return ImageFormat
     */
    public function setImage(\EarDish\MFP\APIHandlerBundle\Entity\Image $image = null)
    {
        $this->image = $image;
    
        return $this;
    }

    /**
     * Get image
     *
     * @return \EarDish\MFP\APIHandlerBundle\Entity\Image 
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }
}