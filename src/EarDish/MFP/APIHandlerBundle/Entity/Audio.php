<?php

namespace EarDish\MFP\APIHandlerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity(repositoryClass="EarDish\MFP\APIHandlerBundle\Entity\AudioRepository")
 * @ORM\Table(name="mfp_audio")
 * @ORM\HasLifecycleCallbacks()
 */
class Audio
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", name="id")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    
    /**
     * @ORM\Column(type="string", name="type", columnDefinition="ENUM('song','demo')")
     */
    protected $type;
    
    /**
     * @ORM\Column(type="integer", name="type_id", nullable=true)
     */
    protected $typeId = null;
    
    /**
     * @ORM\Column(type="string", name="s3_url", nullable=true)
     */
    protected $s3url = null;
    
    /**
     * @ORM\Column(type="string", name="s3_key", nullable=true)
     */
    protected $s3key = null;
    
    /**
     * @ORM\Column(type="datetime", name="date_created")
     */
    protected $dateCreated;
    
    /**
     * @ORM\Column(type="datetime", name="date_modified")
     */
    protected $dateModified;
    
    // One-to-Many Foreign Keys
    
    /**
     * @ORM\OneToMany(targetEntity="AudioFormat", mappedBy="audio")
     */
    protected $formats;
    
    
    
    public function __construct() 
    {
         $this->formats =  new ArrayCollection();
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
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set type
     *
     * @param string $type
     * @return Audio
     */
    public function setType($type)
    {
        $this->type = $type;
    
        return $this;
    }

    /**
     * Get type
     *
     * @return string 
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set dateCreated
     *
     * @param \DateTime $dateCreated
     * @return Audio
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
     * @return Audio
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
     * Add formats
     *
     * @param \EarDish\MFP\APIHandlerBundle\Entity\AudioFormat $formats
     * @return Audio
     */
    public function addFormat(\EarDish\MFP\APIHandlerBundle\Entity\AudioFormat $formats)
    {
        $this->formats[] = $formats;
    
        return $this;
    }

    /**
     * Remove formats
     *
     * @param \EarDish\MFP\APIHandlerBundle\Entity\AudioFormat $formats
     */
    public function removeFormat(\EarDish\MFP\APIHandlerBundle\Entity\AudioFormat $formats)
    {
        $this->formats->removeElement($formats);
    }

    /**
     * Get formats
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getFormats()
    {
        return $this->formats;
    }

    /**
     * Set typeId
     *
     * @param integer $typeId
     * @return Audio
     */
    public function setTypeId($typeId)
    {
        $this->typeId = $typeId;
    
        return $this;
    }

    /**
     * Get typeId
     *
     * @return integer 
     */
    public function getTypeId()
    {
        return $this->typeId;
    }

    /**
     * Set s3url
     *
     * @param string $s3url
     * @return Audio
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
     * @return Audio
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
}