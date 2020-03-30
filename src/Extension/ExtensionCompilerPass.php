<?php

declare(strict_types=1);

namespace Bolt\Extension;

use Bolt\Common\Str;
use Composer\Package\PackageInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;
use Webmozart\PathUtil\Path;

class ExtensionCompilerPass implements CompilerPassInterface
{
    /** string */
    private $projectDir;

    public function process(ContainerBuilder $container): void
    {
        if ($container->has(ExtensionRegistry::class) === false) {
            return;
        }

        $registry = $container->findDefinition(ExtensionRegistry::class);
        $packages = array_keys($container->findTaggedServiceIds(ExtensionInterface::CONTAINER_TAG));
        $this->projectDir = $container->getParameter('kernel.project_dir');

        /* @see ExtensionRegistry::addCompilerPass() */
        $registry->addMethodCall('addCompilerPass', [$packages]);

        // Rebuild our own `services_bolt.yml` file.
        $this->buildServices($packages);
    }

    public function buildServices(array $packages): void
    {
        $services = [
            'services' => [
                '_defaults' => [
                    'autowire' => true,
                    'autoconfigure' => true,
                ],
            ],
        ];

   //     $packages = $this->addComposerPackages($packages);

        foreach ($packages as $package) {
            [$name, $service] = $this->createService($package);
            if ($name) {
                $services['services'][$name] = $service;
            }
        }

        $yaml = "# This file is auto-generated by Bolt. Do not modify.\n\n";
        $yaml .= Yaml::dump($services, 3);

        $filename = $this->projectDir . '/config/services_bolt.yaml';
        file_put_contents($filename, $yaml);
    }

    private function createService(string $package): array
    {
        if (! class_exists($package)) {
            return [null, null];
        }

        $reflection = new \ReflectionClass($package);

        $namespace = Str::removeLast($reflection->getName(), Str::splitLast($reflection->getName(), '\\'));
        $path = Path::makeRelative(dirname($reflection->getFileName()), $this->projectDir . '/foo');

        return [$namespace, [
            'resource' => $path . '/*',
            'exclude' => $path . '/{Entity,Exception}',
        ]];
    }

    private function addComposerPackages(array $packages): array
    {
        $composerPackages = Types::get('bolt-extension');

        /** @var PackageInterface $package */
        foreach ($composerPackages as $package) {
            $extra = $package->getExtra();

            if (array_key_exists('entrypoint', $extra)) {
                $packages[] = $extra['entrypoint'];
            }
        }

        return array_unique($packages);
    }
}
