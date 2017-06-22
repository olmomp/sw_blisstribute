<?php

namespace Shopware\ExitBBlisstribute\Subscribers;

use \Enlight\Event\SubscriberInterface;
use Shopware\Components\DependencyInjection\Container;

class ServiceSubscriber implements SubscriberInterface
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
            'Enlight_Bootstrap_InitResource_blisstribute.google_address_validator' => 'onGetGoogleAddressValidator'
        ];
    }
    
    /**
     * @return string
     */
    public function onGetGoogleAddressValidator(\Enlight_Event_EventArgs $args)
    {
        return new OrderGoogleAddressValidator();
    }
}
