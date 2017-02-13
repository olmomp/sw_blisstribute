<?php

namespace Shopware\CustomModels\Blisstribute;

use Doctrine\ORM\Mapping AS ORM;

use Shopware\Models\Shop\Shop;
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
 * @ORM\Entity(repositoryClass="Shopware\CustomModels\Blisstribute\BlisstributeShopRepository")
 * @ORM\Table(name="s_plugin_blisstribute_shop")
 */
class BlisstributeShop extends ModelEntity
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
     * reference to shop
     *
     * @var Shop
     *
     * @ORM\OneToOne(targetEntity="Shopware\Models\Shop\Shop")
     * @ORM\JoinColumn(name="s_shop_id", referencedColumnName="id")
     */
    private $shop;

    /**
     * blisstribute advertising medium code
     *
     * @var string
     *
     * @ORM\Column(name="advertising_medium_code", type="string", nullable=true)
     */
    private $advertisingMediumCode;

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
     * @param Shop $shop
     *
     * @return BlisstributeShop
     */
    public function setShop($shop)
    {
        $this->shop = $shop;
        return $this;
    }

    /**
     * @return Shop
     */
    public function getShop()
    {
        return $this->shop;
    }

    /**
     * @return string
     */
    public function getAdvertisingMediumCode()
    {
        return $this->advertisingMediumCode;
    }

    /**
     * @param string $advertisingMediumCode
     *
     * @return BlisstributeShop
     */
    public function setAdvertisingMediumCode($advertisingMediumCode)
    {
        $this->advertisingMediumCode = $advertisingMediumCode;
        return $this;
    }
}
