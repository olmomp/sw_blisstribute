<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Shopware\Components\Model\ModelEntity;
use Shopware\Components\Model\ModelManager;
use Shopware\CustomModels\Blisstribute\TaskLock;

/**
 * abstract class for sync process
 *
 * @author    Julian Engler
 * @package   Shopware\Components\Blisstribute\Sync
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
abstract class Shopware_Components_Blisstribute_Sync
{
    /**
     * @var ModelManager
     */
    protected $modelManager;

    /**
     * @var string
     */
    protected $taskName = '';

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $logBaseName = '';

    /**
     * @var \Enlight_Config
     */
    protected $config;

    /**
     * last error message for frontend
     *
     * @var string
     */
    protected $lastError = '';

    /**
     * log entry
     *
     * @param string $message log message
     * @param string $method log message write
     * @param int $logLevel log level, default info
     *
     * @return void
     */
    protected function logMessage($message, $method, $logLevel = Logger::INFO)
    {
        $logMessage = get_class($this) . '::' . $method . '::' . $message . '::memory ' . memory_get_usage(true);
        switch ($logLevel) {
            case Logger::DEBUG:
                $this->logger->debug($logMessage);
                break;

            case Logger::NOTICE:
                $this->logger->notice($logMessage);
                break;

            case Logger::WARNING:
                $this->logger->warn($logMessage);
                break;

            case Logger::ERROR:
                $this->logger->err($logMessage);
                break;

            case Logger::CRITICAL:
                $this->logger->crit($logMessage);
                break;

            case Logger::ALERT:
                $this->logger->alert($logMessage);
                break;

            case Logger::EMERGENCY:
                $this->logger->emerg($logMessage);
                break;

            case Logger::INFO:
            default:
                $this->logger->info($logMessage);
                break;
        }
    }

    /**
     * @param \Enlight_Config $config
     *
     * @throws Exception
     */
    public function __construct(\Enlight_Config $config)
    {
        $this->config = $config;

        $this->modelManager = Shopware()->Models();

        $appPath = Shopware()->DocPath();
        if (version_compare(Shopware::VERSION, '5.0.4', '<=') && Shopware::VERSION != '___VERSION___') {
            $logFilePath = $appPath . 'logs/' . $this->logBaseName . '-' . date('Y-m-d') . '.log';
        } else {
            $logFilePath = $appPath . 'var/log/' . $this->logBaseName . '-' . date('Y-m-d') . '.log';
        }

        if (!file_exists($logFilePath)) {
            touch($logFilePath);
            chmod($logFilePath, 0777);
        }

        $logFile = fopen($logFilePath, 'a');

        $this->logger = new Logger($this->logBaseName, array(new StreamHandler($logFile)));
    }

    /**
     * initialize model mapping
     *
     * @param ModelEntity $modelEntity
     *
     * @return array
     */
    abstract protected function initializeModelMapping(ModelEntity $modelEntity);

    /**
     * lock sync task
     *
     * @return void
     *
     * @throws Exception
     */
    public function lockTask()
    {
        $taskLock = $this->modelManager
            ->getRepository('Shopware\CustomModels\Blisstribute\TaskLock')
            ->findByTaskName($this->taskName);

        if ($taskLock !== null) {
            /** @var TaskLock $taskLock */
            $taskLock->setTries($taskLock->getTries() + 1);
            $this->modelManager->persist($taskLock);
            $this->modelManager->flush();

            if ($taskLock->getCreatedAt()->add(new DateInterval('PT4H'))->format('YmdHis') <= date('YmdHis')) {
                $taskLock->setTries(0)
                    ->setTaskPid(getmypid())
                    ->setCreatedAt(new DateTime());

                $this->modelManager->persist($taskLock);
                $this->modelManager->flush();

                $this->logMessage(
                    'reset lock due to long inactive::' .
                    $this->taskName,
                    __FUNCTION__,
                    Logger::WARNING
                );

                return;
            }

            $this->logMessage('task locked - break::' . $this->taskName, __FUNCTION__, Logger::ALERT);
            throw new Exception('task already running');
        }

        $taskLock = new TaskLock();
        $taskLock->setTaskName($this->taskName)
            ->setTaskPid(getmypid());

        $this->modelManager->persist($taskLock);
        $this->modelManager->flush();

        $this->logMessage('task locked::' . $this->taskName, __FUNCTION__);
    }

    /**
     * unlock task
     *
     * @return void
     */
    public function unlockTask()
    {
        $taskLock = $this->modelManager
            ->getRepository('Shopware\CustomModels\Blisstribute\TaskLock')
            ->findByTaskName($this->taskName);

        if ($taskLock === null) {
            return;
        }

        $this->modelManager->remove($taskLock);
        $this->modelManager->flush();
    }

    /**
     * set error as last error
     *
     * @param string $lastError
     *
     * @return Shopware_Components_Blisstribute_Sync
     */
    public function setLastError($lastError)
    {
        $this->lastError = $lastError;
        return $this;
    }

    /**
     * return last set error
     *
     * @return string
     */
    public function getLastError()
    {
        return $this->lastError;
    }
}