<?php

require_once __DIR__ . '/Library/nuSOAP/nusoap.php';
require_once __DIR__ . '/Exception/TransferException.php';

/**
 * abstract soap client class for blisstribute sync
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Blisstribute\SoapClient
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
abstract class Shopware_Components_Blisstribute_SoapClient
{
    /**@+
     * blisstribute soap endpoints
     *
     * @var string
     */
    const SERVICE_MATERIAL = 'material';
    const SERVICE_ORDER = 'order';
    /**@-*/

    /**
     * @var string
     */
    protected $blisstributeService = null;

    /**
     * @var string
     */
    protected $blisstributeClient = null;
    
    /**
     * @var string
     */
    protected $blisstributeHttpUser = null;
    
    /**
     * @var string
     */
    protected $blisstributeHttpPassword = null;

    /**
     * @var string
     */
    protected $blisstributeUser = null;

    /**
     * @var string
     */
    protected $blisstributePassword = null;

    /**
     * @var \Enlight_Config
     */
    protected $config;
    
    /**
     * @var object
     */
    protected $_soapClient = null;

    /**
     * Constructor method
     *
     * Expects a configuration parameter.
     *
     * @param Enlight_Config $config
     *
     * @throws Exception
     */
    public function __construct(\Enlight_Config $config)
    {
        $this->config = $config;
       
        if ($config->get('blisstribute-http-login') && $config->get('blisstribute-http-password')) {
            $this->blisstributeHttpUser = $config->get('blisstribute-http-login');
            $this->blisstributeHttpPassword = $config->get('blisstribute-http-password');
        }

        $this->blisstributeClient = $config->get('blisstribute-soap-client');
        $this->blisstributeUser = $config->get('blisstribute-soap-username');
        $this->blisstributePassword = $config->get('blisstribute-soap-password');
    }

    /**
     * init nusoap
     *
     * @return nusoap_client
     *
     * @throws Shopware_Components_Blisstribute_Exception_TransferException
     */
    public function getSoapClient()
    {
        if ($this->_soapClient == null) {
            $soapClient = new nusoap_client($this->getServiceUrl(true), true);

            if (!is_null($this->blisstributeHttpUser) && trim($this->blisstributeHttpPassword)) {
                $soapClient->setCredentials($this->blisstributeHttpUser, $this->blisstributeHttpPassword);
            }

            /** @var nusoap_client $oSoapProxy */
            $soapProxy = $soapClient->getProxy();
            if ($soapProxy == null) {
                throw new Shopware_Components_Blisstribute_Exception_TransferException(
                    'could not connect to blisstribute'
                );
            }

            $soapProxy->setEndpoint($this->getServiceUrl(false));
            $this->_soapClient = $soapProxy;
        }

        return $this->_soapClient;
    }

    /**
     * send request to blisstribute
     *
     * @param string $method
     * @param array $params
     *
     * @return mixed
     *
     * @throws Shopware_Components_Blisstribute_Exception_TransferException
     */
    public function sendActionRequest($method, array $params)
    {
        if (!$this->sendAuthenticationRequest()) {
            throw new Shopware_Components_Blisstribute_Exception_TransferException(
                'blisstribute soap authentication failed'
            );
        }

        $result = $this->__call($method, $params);
        return $result;
    }

    /**
     * authenticate against blisstribute
     *
     * @return bool
     */
    public function sendAuthenticationRequest()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $result = $this->getSoapClient()->authenticate(
            $this->blisstributeClient,
            $this->blisstributeUser,
            $this->blisstributePassword
        );

        return $result;
    }

    /**
     * build service urls
     *
     * @param bool $wsdlEndpoint
     *
     * @return string
     */
    protected function getServiceUrl($wsdlEndpoint = false)
    {
        $url = ($this->config->get('blisstribute-soap-protocol') == 1 ? 'http' : 'https')
            . '://' . $this->config->get('blisstribute-soap-host')
            . '/' . $this->blisstributeService;

        if ($wsdlEndpoint) {
            $url .=  '/?wsdl';
        }

        return $url;
    }

    /**
     * get nusoap call
     *
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $soapClient = $this->getSoapClient();
        $soapClient->timeout = 60;
        $soapClient->response_timeout = 120;

        $this->_lastMethod = $name;

        return $soapClient->{$name}($arguments);
    }
}
