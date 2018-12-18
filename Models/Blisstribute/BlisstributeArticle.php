<?php

namespace Shopware\CustomModels\Blisstribute;

use Doctrine\ORM\Mapping AS ORM;
use Shopware\Models\Article\Article;
use Shopware\Components\Model\ModelEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="BlisstributeArticleRepository")
 * @ORM\Table(name="s_plugin_blisstribute_articles")
 */
class BlisstributeArticle extends ModelEntity
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
     * @var \DateTime
     *
     * @Assert\DateTime()
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @var \DateTime
     *
     * @Assert\DateTime()
     * @ORM\Column(name="modified_at", type="datetime", nullable=false)
     */
    private $modifiedAt;

    /**
     * date when tried transmitted last time
     *
     * @var \DateTime
     *
     * @ORM\Column(name="last_cron_at", type="datetime", nullable=false)
     */
    private $lastCronAt;

    /**
     * @var Article
     *
     * @ORM\ManyToOne(targetEntity="\Shopware\Models\Article\Article")
     * @ORM\JoinColumn(name="s_article_id", referencedColumnName="id"),
     */
    private $article;

    /**
     * @var bool
     *
     * @ORM\Column(name="trigger_deleted", type="boolean", nullable=false)
     */
    private $deleted = false;

    /**
     * @var bool $triggerSync
     *
     * @ORM\Column(name="trigger_sync", type="boolean", nullable=false)
     */
    private $triggerSync = false;

    /**
     * @var string $comment
     *
     * @ORM\Column(name="comment", type="text", nullable=true)
     */
    private $comment = null;

    /**
     * @var integer $tries
     *
     * @ORM\Column(name="tries", type="smallint", nullable=false)
     */
    private $tries = 0;

    /**
     * @var string $syncHash
     *
     * @ORM\Column(name="sync_hash", type="string", length=40, nullable=false)
     */
    private $syncHash = '';

    /**
     * BlisstributeArticle constructor.
     */
    public function __construct()
    {
        $this->createdAt  = new \DateTime();
        $this->modifiedAt = new \DateTime();
    }

    /**
     * @param int $id
     *
     * @return BlisstributeArticle
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param \DateTime $createdAt
     *
     * @return BlisstributeArticle
     */
    public function setCreatedAt(\DateTime $createdAt)
    {
        $this->createdAt = $createdAt;
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
     * @param \DateTime $modifiedAt
     *
     * @return BlisstributeArticle
     */
    public function setModifiedAt(\DateTime $modifiedAt)
    {
        $this->modifiedAt = $modifiedAt;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getModifiedAt()
    {
        return $this->modifiedAt;
    }

    /**
     * set last cron activity
     *
     * @param \DateTime $lastCronAt
     *
     * @return BlisstributeArticle
     */
    public function setLastCronAt(\DateTime $lastCronAt)
    {
        $this->lastCronAt = $lastCronAt;
        return $this;
    }

    /**
     * return last cron activity
     *
     * @return \DateTime
     */
    public function getLastCronAt()
    {
        return $this->lastCronAt;
    }

    /**
     * @param Article $article
     *
     * @return BlisstributeArticle
     */
    public function setArticle($article)
    {
        $this->article = $article;
        return $this;
    }

    /**
     * @return Article
     */
    public function getArticle()
    {
        return $this->article;
    }

    /**
     * @return \Shopware\Models\Article\Detail
     */
    public function getMainDetail()
    {
        return $this->article->getMainDetail();
    }

    /**
     * @param boolean $deleted
     *
     * @return BlisstributeArticle
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isDeleted()
    {
        return $this->deleted;
    }


    /**
     * @param boolean $triggerSync
     *
     * @return BlisstributeArticle
     */
    public function setTriggerSync($triggerSync)
    {
        $this->triggerSync = $triggerSync;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isTriggerSync()
    {
        return $this->triggerSync;
    }

    /**
     * @param string $comment
     *
     * @return BlisstributeArticle
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @param int $tries
     *
     * @return BlisstributeArticle
     */
    public function setTries($tries)
    {
        $this->tries = $tries;
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
     * @param string $syncHash
     *
     * @return BlisstributeArticle
     */
    public function setSyncHash($syncHash)
    {
        $this->syncHash = $syncHash;
        return $this;
    }

    /**
     * @return string
     */
    public function getSyncHash()
    {
        return $this->syncHash;
    }
}
