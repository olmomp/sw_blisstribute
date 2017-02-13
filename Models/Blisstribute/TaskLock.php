<?php

namespace Shopware\CustomModels\Blisstribute;

use Doctrine\ORM\Mapping AS ORM;
use Shopware\Components\Model\ModelEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * model entity for task lock
 *
 * @author    Julian Engler
 * @package   Shopware\CustomModels\Blisstribute
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @ORM\Entity(repositoryClass="TaskLockRepository")
 * @ORM\Table(name="s_plugin_blisstribute_task_lock")
 */
class TaskLock extends ModelEntity
{
    /**
     * Primary Key - autoincrement value
     *
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="task_name", type="string", length=128)
     */
    private $taskName = '';

    /**
     * @var int
     *
     * @ORM\Column(name="task_pid", type="integer")
     */
    private $taskPid = 0;

    /**
     * @var \DateTime
     *
     * @Assert\DateTime()
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt = 'now';

    /**
     * @var int
     *
     * @ORM\Column(name="tries", type="smallint", nullable=false)
     */
    private $tries = 0;


    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTaskName()
    {
        return $this->taskName;
    }

    /**
     * @param string $taskName
     * @return $this
     */
    public function setTaskName($taskName)
    {
        $this->taskName = $taskName;
        return $this;
    }

    /**
     * @return int
     */
    public function getTaskPid()
    {
        return $this->taskPid;
    }

    /**
     * @param int $taskPid
     * @return $this
     */
    public function setTaskPid($taskPid)
    {
        $this->taskPid = $taskPid;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return int
     */
    public function getTries()
    {
        return $this->tries;
    }

    /**
     * @param int $tries
     * @return $this
     */
    public function setTries($tries)
    {
        $this->tries = $tries;
        return $this;
    }
}