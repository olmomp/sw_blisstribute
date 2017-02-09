<?php

namespace Shopware\CustomModels\Blisstribute;

use Doctrine\ORM\Mapping AS ORM;

use Shopware\Models\Voucher\Voucher;
use Shopware\Models\Dispatch\Dispatch;
use Shopware\Components\Model\ModelEntity;

/**
 * blisstribute shipment mapping entity
 *
 * @author    Julian Engler
 * @package   Shopware\CustomModels\Blisstribute
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @ORM\Entity(repositoryClass="Shopware\CustomModels\Blisstribute\BlisstributeCouponRepository")
 * @ORM\Table(name="s_plugin_blisstribute_coupon")
 */
class BlisstributeCoupon extends ModelEntity
{
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
     * reference to shopware coupon
     *
     * @var Voucher
     *
     * @ORM\OneToOne(targetEntity="Shopware\Models\Voucher\Voucher")
     * @ORM\JoinColumn(name="s_voucher_id", referencedColumnName="id")
     */
    private $voucher;

    /**
     * flag if coupon is money voucher
     *
     * @var bool
     *
     * @ORM\Column(name="flag_money_voucher", type="boolean", nullable=false, )
     */
    private $isMoneyVoucher = false;

    /**
     * set entity identifier
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * return entity identifier
     *
     * @param int $id
     *
     * @return BlisstributeShipment
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return Voucher
     */
    public function getVoucher()
    {
        return $this->voucher;
    }

    /**
     * @param Voucher $voucher
     *
     * @return BlisstributeCoupon
     */
    public function setVoucher($voucher)
    {
        $this->voucher = $voucher;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getIsMoneyVoucher()
    {
        return $this->isMoneyVoucher;
    }

    /**
     * @param boolean $isMoneyVoucher
     *
     * @return BlisstributeCoupon
     */
    public function setIsMoneyVoucher($isMoneyVoucher)
    {
        $this->isMoneyVoucher = $isMoneyVoucher;
        return $this;
    }
}
