<?php

require_once __DIR__ . '/../SoapClient.php';

/**
 * blisstribute order soap client
 *
 * @author    Julian Engler
 * @package   Shopware_Components_Blisstribute_Order_SoapClient
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Order_SoapClient extends Shopware_Components_Blisstribute_SoapClient
{
    /**
     * blisstribute soap service endpoint
     *
     * @var string
     */
    protected $blisstributeService = 'order';

    /**
     * send article collection to blisstribute to register new articles in blisstribute or update article information
     *
     * @param array $orderCollection
     *
     * @return array
     */
    public function syncOrder(array $orderCollection)
    {
        $response = $this->sendActionRequest('receiveOrder', $orderCollection);
        return $response;
    }
}
