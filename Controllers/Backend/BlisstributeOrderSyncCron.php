<?php

use Shopware\Components\CSRFWhitelistAware;


/**
 * cron to sync order to blisstribute
 *
 * @author    Julian Engler
 * @package   Shopware\Controllers\Backend
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Controllers_Backend_BlisstributeOrderSyncCron extends Enlight_Controller_Action implements CSRFWhitelistAware
{
    /**
     * init cron controller
     *
     * @return void
     */
    public function init()
    {
        Shopware()->Plugins()->Backend()->Auth()->setNoAuth();
        Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();
    }

    public function getWhitelistedCSRFActions()
    {
        return [
            'index'
        ];
    }

    /**
     * cron action
     *
     * @return void
     *
     * @throws Exception
     * @throws Zend_Controller_Response_Exception
     */
    public function indexAction()
    {
        if (!Shopware()->Plugins()->Core()->Cron()->authorizeCronAction($this->Request())) {
            $this->Response()
                 ->clearHeaders()
                 ->setHttpResponseCode(403)
                 ->appendBody("Forbidden");

            return;
        }

        echo date('r') . ' - Processing BLISSTRIBUTE order sync cron' . PHP_EOL;

        require_once __DIR__ . '/../../Components/Blisstribute/Order/Sync.php';

        try {
            $controller = new Shopware_Components_Blisstribute_Order_Sync(
                Shopware()->Plugins()->Backend()->ExitBBlisstribute()->Config()
            );

            $controller->processBatchOrderSync();
        } catch (Exception $ex) {
            echo date('r') . ' - Failed BLISSTRIBUTE order sync cron' . PHP_EOL;
            return;
        }

        echo date('r') . ' - Done BLISSTRIBUTE order sync cron' . PHP_EOL;
    }
}
