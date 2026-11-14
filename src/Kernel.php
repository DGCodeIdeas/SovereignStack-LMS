<?php

declare(strict_types=1);

namespace SovereignStack\Lms;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        $contents = require $this->getProjectDir() . '/config/bundles.php';
        foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }

    protected function configureRoutes(\Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator $routes): void
    {
        $routes->import('../config/routes.yaml');
    }

    protected function configureContainer(\Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container): void
    {
        $container->import('../config/services.yaml');
        $configDir = $this->getProjectDir() . '/config/packages';

        if (is_file($configDir . '/_' . $this->environment . '.yaml')) {
            $container->import($configDir . '/_' . $this->environment . '.yaml');
        } else {
            $container->import($configDir . '/*.yaml');
        }

        if (is_file($configDir . '/' . $this->environment . '/*.yaml')) {
            $container->import($configDir . '/' . $this->environment . '/*.yaml');
        }
    }
}
