<?php

namespace Shopware\CustomModels\Blisstribute;

use Doctrine\ORM\Mapping AS ORM;
use Shopware\Components\Model\ModelEntity;
use Shopware\Models\Order\Detail;

/**
 * database entity for blisstribute shipping request items
 *
 * @author    Julian Engler
 * @package   Shopware\CustomModels\Blisstribute
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @ORM\Entity(repositoryClass="Shopware\CustomModels\Blisstribute\BlisstributeShippingRequestItemsRepository")
 * @ORM\Table(name="s_plugin_blisstribute_shipping_request_items")
 */
class BlisstributeShippingRequestItems extends ModelEntity
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
     * reference to shipping request
     *
     * @var BlisstributeShippingRequest
     *
     * @ORM\ManyToOne(targetEntity="Shopware\CustomModels\Blisstribute\BlisstributeShippingRequest")
     * @ORM\JoinColumn(name="blisstribute_shipping_request_id", referencedColumnName="id")
     */
    private $shippingRequest;

    /**
     * reference to order line
     *
     * @var Detail
     *
     * @ORM\OneToOne(targetEntity="Shopware\Models\Order\Detail")
     * @ORM\JoinColumn(name="s_order_detail_id", referencedColumnName="id")
     */
    private $orderDetail;

    /***
     * quantity which was returned by customer
     *
     * @var int
     *
     * @ORM\Column(name="blisstribute_quantity_returned", type="integer", nullable=false)
     */
    private $quantityReturned = 0;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return BlisstributeShippingRequestItems
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @param \DateTime $createdAt
     *
     * @return BlisstributeShippingRequestItems
     */
    public function setCreatedAt($createdAt)
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
     * @return BlisstributeShippingRequestItems
     */
    public function setModifiedAt($modifiedAt)
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
     * @param BlisstributeShippingRequest $shippingRequest
     *
     * @return BlisstributeShippingRequestItems
     */
    public function setShippingRequest($shippingRequest)
    {
        $this->shippingRequest = $shippingRequest;
        return $this;
    }

    /**
     * @return BlisstributeShippingRequest
     */
    public function getShippingRequest()
    {
        return $this->shippingRequest;
    }

    /**
     * @param Detail $orderDetail
     *
     * @return BlisstributeShippingRequestItems
     */
    public function setOrderDetail($orderDetail)
    {
        $this->orderDetail = $orderDetail;
        return $this;
    }

    /**
     * @return Detail
     */
    public function getOrderDetail()
    {
        return $this->orderDetail;
    }

    /**
     * @param int $quantityReturned
     *
     * @return BlisstributeShippingRequestItems
     */
    public function setQuantityReturned($quantityReturned)
    {
        $this->quantityReturned = $quantityReturned;
        return $this;
    }

    /**
     * @return int
     */
    public function getQuantityReturned()
    {
        return $this->quantityReturned;
    }
}
