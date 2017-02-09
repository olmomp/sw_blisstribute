<?php

namespace Shopware\CustomModels\Blisstribute;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping AS ORM;
use Shopware\Models\Order\Order;
use Shopware\Components\Model\ModelEntity;

/**
 * database entity for blisstribute shipping request
 *
 * @author    Julian Engler
 * @package   Shopware\CustomModels\Blisstribute
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @ORM\Entity(repositoryClass="Shopware\CustomModels\Blisstribute\BlisstributeShippingRequestRepository")
 * @ORM\Table(name="s_plugin_blisstribute_shipping_request")
 */
class BlisstributeShippingRequest extends ModelEntity
{
    /**
     * Primary Key - autoincrement value
     *
     * @var integer $id
     *
     * @ORM\Id
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * create date
     *
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=false)
     */
    private $createdAt;

    /**
     * last change date
     *
     * @var \DateTime
     *
     * @ORM\Column(name="modified_at", type="datetime", nullable=false)
     */
    private $modifiedAt;

    /**
     * shopware order
     *
     * @var Order
     *
     * @ORM\ManyToOne(targetEntity="\Shopware\Models\Order\Order")
     * @ORM\JoinColumn(name="s_order_id", referencedColumnName="id")
     */
    private $order;

    /**
     * blisstribute shipping request number
     *
     * @var string
     *
     * @ORM\Column(name="blisstribute_shipping_request_number", type="string")
     */
    private $number;

    /**
     * blisstribute carrier code (eg. dhl)
     *
     * @var string
     *
     * @ORM\Column(name="blisstribute_carrier_code", type="string", nullable=false)
     */
    private $carrierCode;

    /**
     * carrier tracking code
     *
     * @var string
     *
     * @ORM\Column(name="blisstribute_tracking_code", type="string", nullable=true)
     */
    private $trackingCode;

    /**
     * collection of all items in shipping request
     *
     * @var ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="Shopware\CustomModels\Blisstribute\BlisstributeShippingRequestItems", mappedBy="shippingRequest")
     */
    private $shippingRequestItemCollection;

    /**
     * set shipping request item collection as array collection
     */
    public function __construct()
    {
        $this->shippingRequestItemCollection = new ArrayCollection();
    }

    /**
     * @param int $id
     *
     * @return BlisstributeShippingRequest
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param \DateTime $createdAt
     *
     * @return BlisstributeShippingRequest
     */
    public function setCreatedAt(\DateTime $createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $modifiedAt
     *
     * @return BlisstributeShippingRequest
     */
    public function setModifiedAt(\DateTime $modifiedAt)
    {
        $this->modifiedAt = $modifiedAt;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getModifiedAt()
    {
        return $this->modifiedAt;
    }

    /**
     * set shopware order
     *
     * @param Order $order
     *
     * @return BlisstributeShippingRequest
     */
    public function setOrder($order)
    {
        $this->order = $order;
        return $this;
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param string $number
     *
     * @return BlisstributeShippingRequest
     */
    public function setNumber($number)
    {
        $this->number = $number;
        return $this;
    }

    /**
     * @return string
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * @param string $carrierCode
     *
     * @return BlisstributeShippingRequest
     */
    public function setCarrierCode($carrierCode)
    {
        $this->carrierCode = $carrierCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getCarrierCode()
    {
        return $this->carrierCode;
    }

    /**
     * @param string $trackingCode
     *
     * @return BlisstributeShippingRequest
     */
    public function setTrackingCode($trackingCode)
    {
        $this->trackingCode = $trackingCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getTrackingCode()
    {
        return $this->trackingCode;
    }

    /**
     * @param ArrayCollection $shippingRequestItemCollection
     *
     * @return BlisstributeShippingRequest
     */
    public function setShippingRequestItemCollection($shippingRequestItemCollection)
    {
        $this->shippingRequestItemCollection = $shippingRequestItemCollection;
        return $this;
    }

    /**
     * add single item
     *
     * @param BlisstributeShippingRequestItems $shippingRequestItems
     *
     * @return BlisstributeShippingRequest
     */
    public function addShippingRequestItem(BlisstributeShippingRequestItems $shippingRequestItems)
    {
        $this->shippingRequestItemCollection->add($shippingRequestItems);
        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getShippingRequestItemCollection()
    {
        return $this->shippingRequestItemCollection;
    }
}
