<?php
namespace Shopware\CustomModels\Blisstribute;

use Doctrine\ORM\Mapping AS ORM;
use Shopware\Components\Model\ModelEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * model entity for article type
 *
 * @author    Julian Engler
 * @package   Shopware\CustomModels\Blisstribute
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @ORM\Entity(repositoryClass="Shopware\CustomModels\Blisstribute\BlisstributeArticleTypeRepository")
 * @ORM\Table(name="s_plugin_blisstribute_article_type")
 */
class BlisstributeArticleType extends ModelEntity
{
    /**@+
     * possible article types
     *
     * @var int
     */
    const ARTICLE_TYPE_MEDIA = 1;
    const ARTICLE_TYPE_WEAR = 2;
    const ARTICLE_TYPE_WEAR_ATTIRE = 3;
    const ARTICLE_TYPE_EQUIPMENT = 4;
    /**@-*/

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
     * @var \Shopware\Models\Property\Group
     *
     * @ORM\OneToOne(targetEntity="\Shopware\Models\Property\Group")
     * @ORM\JoinColumn(name="s_filter_id", referencedColumnName="id"),
     */
    private $filter;

    /**
     * @var integer $articleType
     *
     * @ORM\Column(name="article_type", type="smallint", nullable=false)
     */
    private $articleType = 0;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * @param int $id
     *
     * @return lisstributeArticleType
     */
    public function setId($id)
    {
        $this->id = $id;
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
     * @return BlisstributeArticleType
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
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
     * @param \DateTime $modifiedAt
     * @return BlisstributeArticleType
     */
    public function setModifiedAt($modifiedAt)
    {
        $this->modifiedAt = $modifiedAt;
        return $this;
    }

    /**
     * @return \Shopware\Models\Property\Group
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @param \Shopware\Models\Property\Group $filter
     *
     * @return BlisstributeArticleType
     */
    public function setFilter($filter)
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * @return int
     */
    public function getArticleType()
    {
        return $this->articleType;
    }

    /**
     * @param int $articleType
     * @return BlisstributeArticleType
     */
    public function setArticleType($articleType)
    {
        $this->articleType = $articleType;
        return $this;
    }
}