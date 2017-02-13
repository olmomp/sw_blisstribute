<?php

/**
 * blisstribute custom api order extension controller
 *
 * @author    Conrad Gülzow
 * @package   Shopware\Controllers\Api\Btorders
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Controllers_Api_Btorders extends Shopware_Controllers_Api_Rest
{
    /**
     * @var Shopware\Components\Api\Resource\Btorder
     */
    protected $resource = null;

    public function init()
    {
        $this->resource = \Shopware\Components\Api\Manager::getResource('btorder');
    }

    /**
     * Update order
     *
     * PUT /api/btorders/{id}
     */
    public function putAction()
    {
        $orderNumber = $this->Request()->getParam('id');
        $params = $this->Request()->getPost();

        $order = $this->resource->update($orderNumber, $params);
        $location = $this->apiBaseUrl . 'btorders/' . $order->getId();

        $data = array(
            'id' => $order->getId(),
            'location' => $location
        );

        $this->View()->assign(array('success' => true, 'data' => $data));
    }
}