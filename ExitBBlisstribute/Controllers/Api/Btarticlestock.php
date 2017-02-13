<?php

/**
 * blisstribute custom api order extension controller
 *
 * @author    Conrad Gülzow
 * @package   Shopware\Controllers\Api\Btorders
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Controllers_Api_Btarticlestock extends Shopware_Controllers_Api_Rest
{
    /**
     * @var Shopware\Components\Api\Resource\Btarticlestock
     */
    protected $resource = null;

    /**
     * init api controller
     *
     * @return void
     */
    public function init()
    {
        $this->resource = \Shopware\Components\Api\Manager::getResource('btarticlestock');
    }

    /**
     * list of article stock information
     *
     * @return void
     */
    public function indexAction()
    {
        $page = $this->Request()->getParam('page', 1) - 1;
        $limit = $this->Request()->getParam('limit', 25);

        $response = $this->resource->getList($page, $limit);

        $this->View()->assign($response);
        $this->View()->assign('page', $page);
        $this->View()->assign('success', true);
    }

    /**
     * single article stock information
     *
     * @return void
     */
    public function getAction()
    {
        $vhsArticleNumber = $this->Request()->getParam('id');

        $response = $this->resource->getOne($vhsArticleNumber);

        $this->View()->assign($response);
        $this->View()->assign('page', 1);
        $this->View()->assign('success', true);
    }
}