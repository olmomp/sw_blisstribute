<?php

namespace Shopware\ExitBBlisstribute\Subscribers;

use \Enlight\Event\SubscriberInterface;
use \Shopware\Components\DependencyInjection\Container;

require_once __DIR__ . '/../Components/Blisstribute/Command/OrderExport.php';
require_once __DIR__ . '/../Components/Blisstribute/Command/ArticleExport.php';

class CommandSubscriber implements SubscriberInterface
{
    /**
     * @var Container
     */
    private $container;

    /**
     * SearchBundleSubscriber constructor.
     * @param Container $container
     */
    public function __construct()
    {
        $this->container = Shopware()->Container();
    }
    
    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Console_Add_Command' => 'onAddConsoleCommand'           
        ];
    }
    
    /**
     * add blisstribute cli commands
     *
     * @param Enlight_Event_EventArgs $args
     * @return ArrayCollection
     */
    public function onAddConsoleCommand(\Enlight_Event_EventArgs $args)
    {
        if ($this->container->get('models')->getRepository('Shopware\Models\Plugin\Plugin')->findOneBy([
            'name' => 'SwagPromotion',
            'active' => true
        ])) {
            $this->container->get('loader')->registerNamespace('Shopware\CustomModels', Shopware()->DocPath() . 'engine/Shopware/Plugins/Community/Frontend/SwagPromotion/Models/');
            $this->container->get('loader')->registerNamespace('Shopware\SwagPromotion', Shopware()->DocPath() . 'engine/Shopware/Plugins/Community/Frontend/SwagPromotion/');
            $this->container->get('loader')->registerNamespace('Shopware\Components', Shopware()->DocPath() . 'engine/Shopware/Plugins/Community/Frontend/SwagPromotion/Components/');
        }

        return new ArrayCollection([
            new Shopware_Components_Blisstribute_Command_OrderExport(),
            new Shopware_Components_Blisstribute_Command_ArticleExport()
        ]);
    }
}
