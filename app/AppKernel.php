<?php

use Commercetools\Sunrise\AppBundle\AppBundle;
use JaySDe\HandlebarsBundle\HandlebarsBundle;
use Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle;
use Sensio\Bundle\DistributionBundle\SensioDistributionBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

/**
 * @author @jayS-de <jens.schulze@commercetools.de>
 */
class AppKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles()
    {
        $bundles = [
            new FrameworkBundle(),
            new SecurityBundle(),
            new TwigBundle(),
            new HandlebarsBundle(),
            new SensioFrameworkExtraBundle(),
            new MonologBundle(),
            new AppBundle(),
        ];
        if ($this->getEnvironment() == 'dev') {
            $bundles[] = new WebProfilerBundle();
            $bundles[] = new SensioDistributionBundle();
        }
        return $bundles;
    }

    public function configureRoutes(RouteCollectionBuilder $routes)
    {
        $file = 'routing.yml';
        if ($this->getEnvironment() === 'dev') {
            $file = 'routing_dev.yml';
        }
        $routes->import(__DIR__ . '/config/' . $file);
        // import the WebProfilerRoutes, only if the bundle is enabled
        if (isset($this->bundles['WebProfilerBundle'])) {
            $routes->mount('/_wdt', $routes->import('@WebProfilerBundle/Resources/config/routing/wdt.xml'));
            $routes->mount('/_profiler', $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.xml'));
        }
    }

    public function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {

        $loader->load(__DIR__.'/config/config_' . $this->getEnvironment() . '.yml');
        if (!empty(getenv('SECRET_TOKEN'))) {
            $c->setParameter('kernel.secret', getenv('SECRET_TOKEN'));
        }
        // configure WebProfilerBundle only if the bundle is enabled
        if (isset($this->bundles['WebProfilerBundle'])) {
            $c->loadFromExtension('web_profiler', array(
                'toolbar' => true,
                'intercept_redirects' => false,
            ));
        }
    }

    public function getRootDir()
    {
        return __DIR__;
    }

    public function getCacheDir()
    {
        return dirname(__DIR__).'/var/cache/'.$this->getEnvironment();
    }

    public function getLogDir()
    {
        return dirname(__DIR__).'/var/logs';
    }
}
