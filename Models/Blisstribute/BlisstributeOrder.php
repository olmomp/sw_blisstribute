<?php

namespace Shopware\CustomModels\Blisstribute;

use Doctrine\ORM\Mapping AS ORM;
use Shopware\Models\Order\Order;
use Shopware\Components\Model\ModelEntity;

/**
 * blisstribute order entity
 *
 * @author    Julian Engler
 * @package   Shopware\CustomModels\Blisstribute
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @ORM\Entity(repositoryClass="BlisstributeOrderRepository")
 * @ORM\Table(name="s_plugin_blisstribute_orders")
 */
class BlisstributeOrder extends ModelEntity
{
    /**@+
     * export default status collection
     *
     * @var int
     */
    const EXPORT_STATUS_NONE = 1;
    const EXPORT_STATUS_IN_TRANSFER = 2;
    const EXPORT_STATUS_TRANSFERRED = 3;
    /**@-*/

    /**@+
     * export error status collection
     *
     * @var int
     */
    const EXPORT_STATUS_TRANSFER_ERROR = 10;
    const EXPORT_STATUS_VALIDATION_ERROR = 11;
    /**@-*/

    /**@+
     * order failed status
     *
     * @var int
     */
    const EXPORT_STATUS_CREATION_PENDING = 20;
    const EXPORT_STATUS_ABORTED = 21;
    /**@-*/

    /**
     * entity identifier
     *
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * creation date
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
     * date when tried transmitted last time
     *
     * @var \DateTime
     *
     * @ORM\Column(name="last_cron_at", type="datetime", nullable=false)
     */
    private $lastCronAt;

    /**
     * reference to shopware order
     *
     * @var Order
     *
     * @ORM\OneToOne(targetEntity="\Shopware\Models\Order\Order")
     * @ORM\JoinColumn(name="s_order_id", referencedColumnName="id")
     */
    private $order;

    /**
     * transfer status
     *
     * @var int
     *
     * @ORM\Column(name="transfer_status", type="integer", nullable=false)
     */
    private $status;

    /**
     * current tries for order transfer
     *
     * @var int
     *
     * @ORM\Column(name="transfer_tries", type="integer", nullable=false)
     */
    private $tries;

    /**
     * last transfer error
     *
     * @var string|null
     *
     * @ORM\Column(name="transfer_error_comment", type="string", nullable=true)
     */
    private $errorComment;

    /**
     * set identifier
     *
     * @param int $id
     *
     * @return BlisstributeOrder
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * return identifier
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * set created at date
     *
     * @param \DateTime $createdAt
     *
     * @return BlisstributeOrder
     */
    public function setCreatedAt(\DateTime $createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * return create at time
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * set last modified date
     *
     * @param \DateTime $modifiedAt
     *
     * @return BlisstributeOrder
     */
    public function setModifiedAt(\DateTime $modifiedAt)
    {
        $this->modifiedAt = $modifiedAt;
        return $this;
    }

    /**
     * return last modified date
     *
     * @return \DateTime
     */
    public function getModifiedAt()
    {
        return $this->modifiedAt;
    }

    /**
     * set last cron activity
     *
     * @param \DateTime $lastCronAt
     *
     * @return BlisstributeOrder
     */
    public function setLastCronAt(\DateTime $lastCronAt)
    {
        $this->lastCronAt = $lastCronAt;
        return $this;
    }

    /**
     * return last cron activity
     *
     * @return \DateTime
     */
    public function getLastCronAt()
    {
        return $this->lastCronAt;
    }

    /**
     * set shopware order
     *
     * @param Order $order
     *
     * @return BlisstributeOrder
     */
    public function setOrder(Order $order)
    {
        $this->order = $order;
        return $this;
    }

    /**
     * return shopware order
     *
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * set transfer status
     *
     * @param int $status
     *
     * @return BlisstributeOrder
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * return transfer status
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * set current tries for transfer to blisstribute
     *
     * @param int $tries
     *
     * @return BlisstributeOrder
     */
    public function setTries($tries)
    {
        $this->tries = $tries;
        return $this;
    }

    /**
     * return current tries for transfer to blisstribute
     *
     * @return int
     */
    public function getTries()
    {
        return $this->tries;
    }

    /**
     * set last error
     *
     * @param string $errorComment
     *
     * @return BlisstributeOrder
     */
    public function setErrorComment($errorComment)
    {
        $this->errorComment = $errorComment;
        return $this;
    }

    /**
     * return last error
     *
     * @return string
     */
    public function getErrorComment()
    {
        return $this->errorComment;
    }
}
