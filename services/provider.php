<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.markdownmirror
 */

defined('_JEXEC') or die;

use Buchs\Plugin\System\Markdownmirror\Extension\Markdownmirror;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

require_once __DIR__ . '/../vendor/autoload.php';

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $config  = (array) PluginHelper::getPlugin('system', 'markdownmirror');
                $subject = $container->get(DispatcherInterface::class);

                $plugin = new Markdownmirror($subject, $config);
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};