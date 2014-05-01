<?php

namespace EarDish\MFP\APIHandlerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="EarDish\MFP\APIHandlerBundle\Entity\AudioFormatRepository")
 * @ORM\Table(name="mfp_audio_formats")
 * @ORM\HasLifecycleCallbacks()
 */
class AudioFormat
{
    /**
     * @ORM\ManyToOne(targetEntity="Audio", inversedBy="formats")
     * @ORM\JoinColumn(name="audio_id", referencedColumnName="id")
     */
    protected $audio;
    
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
     * @return AudioFormat
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
     * @return AudioFormat
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
     * @return AudioFormat
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
     * @return AudioFormat
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
     * @return AudioFormat
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
     * Set audio
     *
     * @param \EarDish\MFP\APIHandlerBundle\Entity\Audio $audio
     * @return AudioFormat
     */
    public function setAudio(\EarDish\MFP\APIHandlerBundle\Entity\Audio $audio = null)
    {
        $this->audio = $audio;
    
        return $this;
    }

    /**
     * Get audio
     *
     * @return \EarDish\MFP\APIHandlerBundle\Entity\Audio 
     */
    public function getAudio()
    {
        return $this->audio;
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