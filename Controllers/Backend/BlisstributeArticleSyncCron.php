<?php

use Shopware\Components\CSRFWhitelistAware;

/**
 * cron to sync article to blisstribute
 *
 * @author    Julian Engler
 * @package   Shopware\Controllers\Backend
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Controllers_Backend_BlisstributeArticleSyncCron extends Enlight_Controller_Action implements CSRFWhitelistAware
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

    /**
     * Prevents an error from calling the cron directly from the browser
     * @return array
     */
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

        echo date('r') . ' - Processing BLISSTRIBUTE article sync cron' . PHP_EOL;

        require_once __DIR__ . '/../../Components/Blisstribute/Article/Sync.php';

        $test = Shopware()->Plugins()->Backend()->ExitBBlisstribute()->Config();

        try {
            $controller = new Shopware_Components_Blisstribute_Article_Sync(
                Shopware()->Plugins()->Backend()->ExitBBlisstribute()->Config()
            );

            $controller->processBatchArticleSync();
        } catch (Exception $ex) {
            echo date('r') . ' - Failed BLISSTRIBUTE article sync cron' . PHP_EOL;
            return;
        }

        echo date('r') . ' - Done BLISSTRIBUTE article sync cron' . PHP_EOL;
    }
}
