<?php

/**
 * blisstribute core controller
 *
 * @package   Shopware\Controllers\Backend
 * @copyright Copyright (c) 2019
 * @since     1.0.0
 */
class Shopware_Controllers_Backend_BlisstributeCore
    extends Shopware_Controllers_Backend_ExtJs
    implements \Shopware\Components\CSRFWhitelistAware
{
    /**
     * plugin
     *
     * @var
     */
    private $_plugin;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        $this->_plugin = Shopware()->Plugins()->Backend()->ExitBBlisstribute();
        parent::init();
    }

    /**
     * {@inheritdoc}
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'checkPluginUpToDate'
        ];
    }

    /**
     * checks if the blisstribute plugin is up to date
     *
     * @return void
     */
    public function checkPluginUpToDateAction()
    {
        $currentVersion = $this->_plugin->getVersion();
        $pluginData = json_decode(file_get_contents('https://raw.githubusercontent.com/ccarnivore/sw_blisstribute/master/plugin.json'), true);
        $latestVersion = trim($pluginData['currentVersion']);
        $isImportant = (bool)$pluginData['isUpdateImportant'];
        $developerMode = getenv('SHOPWARE_ENV') === 'dev';

        $this->View()->assign([
            'success' => true,
            'currentVersion' => $currentVersion,
            'latestVersion' => $latestVersion,
            'importantUpdate' => $isImportant,
            'outdated' => $developerMode ? true : version_compare($currentVersion, $latestVersion) === -1,
            'downloadLink' => 'https://github.com/ccarnivore/sw_blisstribute/releases/latest'
        ]);
    }
}