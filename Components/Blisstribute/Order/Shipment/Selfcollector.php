<?php

require_once __DIR__ . '/Abstract.php';

/**
 * selfcollector shipment mapping class
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Blisstribute\Order\Shipment
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_Shipment_Selfcollector
    extends Shopware_Components_Blisstribute_Order_Shipment_Abstract
{
    /**
     * @inheritdoc
     */
    protected $code = 'SEL';
}
