<?php

require_once __DIR__ . '/../SoapClient.php';

/**
 * article soap client
 *
 * @author    Julian Engler
 * @package   Shopware_Components_Blisstribute_Article_SoapClient
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class Shopware_Components_Blisstribute_Article_SoapClient extends Shopware_Components_Blisstribute_SoapClient
{
    /**
     * @var string
     */
    protected $blisstributeService = 'material';

    /**
     * send article collection to blisstribute to register new articles in blisstribute or update article information
     *
     * @param array $articleCollection
     *
     * @return array
     */
    public function syncArticleCollection(array $articleCollection)
    {
        $response = $this->sendActionRequest('processMaterialRegistration', $articleCollection);
        return $response;
    }
}
