<?php

/**
 * base class for blisstribute shipment mapping
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Blisstribute\Order\Shipment\Abstract
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
abstract class Shopware_Components_Blisstribute_Order_Shipment_Abstract
{
    /**
     * blisstribute shipment code
     *
     * @var string
     */
    protected $code;

    /**
     * return blisstribute shipment code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }
}
