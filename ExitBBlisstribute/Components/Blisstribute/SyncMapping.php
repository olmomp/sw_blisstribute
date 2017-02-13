<?php

require_once __DIR__ . '/Exception/MappingException.php';

use Shopware\Components\Model\ModelEntity;

/**
 * interface for blisstribute sync mappings
 *
 * @author    Julian Engler
 * @package   Shopware_Components_Blisstribute_SyncMapping
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
abstract class Shopware_Components_Blisstribute_SyncMapping
{
    /**
     * model entity to map
     *
     * @var ModelEntity|null
     */
    protected $modelEntity = null;

    /**
     * @param ModelEntity $modelEntity
     *
     * @return Shopware_Components_Blisstribute_SyncMapping
     */
    public function setModelEntity($modelEntity)
    {
        $this->modelEntity = $modelEntity;
        return $this;
    }

    /**
     * @return ModelEntity
     */
    public function getModelEntity()
    {
        return $this->modelEntity;
    }

    /**
     * map entity for sync to blisstribute
     *
     * @return array
     *
     * @throws Shopware_Components_Blisstribute_Exception_MappingException
     */
    public function buildMapping()
    {
        if ($this->getModelEntity() == null) {
            throw new Shopware_Components_Blisstribute_Exception_MappingException('no model entity given');
        }

        $mappingData = $this->buildBaseData();

        $this->resetData();
        return $mappingData;
    }

    /**
     * reset mapping data
     *
     * @return void
     */
    protected function resetData()
    {
        $this->setModelEntity(null);
    }

    /**
     * start mapping here
     *
     * @return array
     */
    abstract protected function buildBaseData();
}