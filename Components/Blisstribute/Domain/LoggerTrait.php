<?php

use Monolog\Logger;

/**
 * abstract class for sync process
 *
 * @author    Roman Robel
 * @package   Shopware\Components\Blisstribute\Domain
 * @copyright Copyright (c) 2017
 * @since     1.0.0
 */
trait Shopware_Components_Blisstribute_Domain_LoggerTrait
{
    /**
     * @var Monolog\Logger
     */
    protected $_logger = null;

    /**
     * @param string $message
     *
     * @return void
     */
    public function logInfo($message)
    {
        $this->_log($message, Monolog\Logger::INFO);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public function logDebug($message)
    {
        $this->_log($message, Monolog\Logger::DEBUG);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public function logWarn($message)
    {
        $this->_log($message, Monolog\Logger::WARNING);
    }

    /**
     * @param string $message
     * @param int $level
     *
     * @return void
     */
    protected function _log($message, $level = Logger::INFO)
    {
        if ($this->_logger == null) {
            $this->_logger = \Shopware()->PluginLogger();
        }

        $logMessage = 'blisstribute::' . $message;
        $this->_logger->log($level, $logMessage);
    }

}