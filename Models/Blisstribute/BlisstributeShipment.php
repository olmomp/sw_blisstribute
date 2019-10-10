<?php

namespace Shopware\CustomModels\Blisstribute;

use Doctrine\ORM\Mapping AS ORM;
use Shopware\Models\Dispatch\Dispatch;
use Shopware\Components\Model\ModelEntity;

/**
 * blisstribute shipment mapping entity
 *
 * @author    Julian Engler
 * @package   Shopware\CustomModels\Blisstribute
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 *
 * @ORM\Entity(repositoryClass="Shopware\CustomModels\Blisstribute\BlisstributeShipmentRepository")
 * @ORM\Table(name="s_plugin_blisstribute_shipment")
 */
class BlisstributeShipment extends ModelEntity
{
    /**@+
     * available shipment class mappings
     */
    const CLASS_DHL = 'DHL';
    const CLASS_DHL_EXPRESS = 'DHLEXPRESS';
    const CLASS_PAT = 'PAT';
    const CLASS_PATEXPRESS = 'PATEXPRESS';
    const CLASS_FEDEX = 'FEDEX';
    const CLASS_SEL = 'SEL';
    const CLASS_DPD = 'DPD';
    const CLASS_DPDS12 = 'DPDS12';
    const CLASS_DPDE12 = 'DPDE12';
    const CLASS_DPDE18 = 'DPDE18';
    const CLASS_FBA = 'FBA';
    const CLASS_7SENDERS = 'SEVENSENDERS';
    /**@-*/

    /**
     * Maps shipping code to labels. This field is used to populate the combobox options in
     * s_premium_dispatch_attributes. It must therefore include all supported shipping codes.
     *
     * @var array
     */
    public static $CODE_LABELS = [
        'DHL'        => 'DHL',
        'DHLEXPRESS' => 'DHL Express',
        'DPD'        => 'DPD',
        'DPDE12'     => 'DPD E12',
        'DPDE18'     => 'DPD E18',
        'DPDS12'     => 'DPD S12',
        'DTPG'       => 'Deutsche Post Warensendung Gross',
        'DTPM'       => 'Deutsche Post Warensendung Maxi',
        'FBA'        => 'FBA',
        'FEDEX'      => 'FedEx',
        'GLS'        => 'GLS',
        'GWW'        => 'Gebr. Weiss',
        'HERMES'     => 'Hermes',
        'LSH'        => 'Briefversand',
        'PAT'        => 'Post AT',
        'PATEXPRESS' => 'Post AT Express',
        'SEL'        => 'Selbstabholer',
        '7SENDERS'   => 'Seven Senders',
        'SKR'        => 'Schenker',
    ];

    /**
     * entity identifier
     *
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * reference to ship
     *
     * @var Dispatch
     *
     * @ORM\OneToOne(targetEntity="Shopware\Models\Dispatch\Dispatch")
     * @ORM\JoinColumn(name="s_premium_dispatch_id", referencedColumnName="id")
     */
    private $shipment;

    /**
     * blisstribute shipment mapping class name
     *
     * @var string
     *
     * @ORM\Column(name="mapping_class_name", type="string", nullable=true)
     */
    private $className;

    /**
     * set entity identifier
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * return entity identifier
     *
     * @param int $id
     *
     * @return BlisstributeShipment
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * set shopware shipment class
     *
     * @param Dispatch $shipment
     *
     * @return BlisstributeShipment
     */
    public function setShipment(Dispatch $shipment)
    {
        $this->shipment = $shipment;
        return $this;
    }

    /**
     * return shopware shipment class
     *
     * @return Dispatch
     */
    public function getShipment()
    {
        return $this->shipment;
    }

    /**
     * set blisstribute mapping class name
     *
     * @param string $className
     *
     * @return BlisstributeShipment
     */
    public function setClassName($className)
    {
        $this->className = $className;
        return $this;
    }

    /**
     * return blisstribute mapping class name
     *
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }
}
