<?php

/**
 * blisstribute api article controller extension
 *
 * @author    Conrad Gülzow
 * @package   Shopware\Controllers\Api\Btorders
 *
 * @copyright Copyright (c) 2017 exitB GmbH
 */
class Shopware_Controllers_Api_Btarticles extends Shopware_Controllers_Api_Rest
{
    /**
     * @var Shopware\Components\Api\Resource\Btarticle
     */
    protected $resource = null;

    /**
     * init api controller
     *
     * @return void
     */
    public function init()
    {
        $this->resource = \Shopware\Components\Api\Manager::getResource('btarticle');
    }

}