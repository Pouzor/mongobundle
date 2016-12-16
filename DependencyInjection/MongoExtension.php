<?php

namespace Pouzor\MongoBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class MongoExtension extends Extension
{

    /**
     * @var ContainerBuilder
     */
    protected $container;

    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        if (isset($config['mapper'])) {
            $container->setParameter('mongobundle.mongo.bindings', $config['mapper']);
        }

        if (empty($config['default_connection'])) {
            $keys = array_keys($config['connections']);
            $config['default_connection'] = reset($keys);
        }


        if ($container->hasDefinition($config['manager'])) {

            $container->getDefinition($config['manager'])->addMethodCall(
                'setDefaultConnection',
                array(
                    $config['default_connection']
                )
            );

            $this->processConnections(
                $container,
                $config['connections'],
                $container->getDefinition($config['manager'])
            );
        }


    }


    /**
     * Process connection configuration params
     *
     * @param $connections
     * @param Definition $managerDef
     * @throws \InvalidArgumentException
     */
    private function processConnections(ContainerBuilder $container, $connections, Definition $managerDef)
    {

        $locator = new FileLocator();
        $loaderResolver = new LoaderResolver(
            [
                new MongoSchemaLoader($locator)
            ]
        );
        $delegatingLoader = new DelegatingLoader($loaderResolver);

        foreach ($connections as $name => $conf) {
            $container->setParameter(sprintf('mongo.connections.%s', $name), $name);

            if (!file_exists($conf['schema'])) {
                throw new \InvalidArgumentException(sprintf('The config file %s is missing !', $conf['schema']));
            }
            $schema = $delegatingLoader->load($conf['schema']);

            $conf['collections'] = $schema;

            foreach ($conf['collections'] as $collection => $configuration) {
                $container->setParameter(sprintf('mongobundle.repository.%s.config', $collection), $configuration);
            }

            $managerDef->addMethodCall(
                'addConnection',
                array(
                    $name,
                    $conf
                )
            );

            $ref = new Reference('mongo.manager');

            $this->processRepositories($container, $conf['collections'], $ref);
        }
    }

    /**
     * Stablish/Create services for repositories
     *
     * @param $collections
     * @param \Symfony\Component\DependencyInjection\Reference $mongoManagerRef
     * @throws \Exception
     */
    private function processRepositories(ContainerBuilder $container, $collections, Reference $mongoManagerRef)
    {
        foreach ($collections as $collection => $mongoConf) {
            if (isset($mongoConf['repository'])) {
                $id = null;

                if ($container->has($mongoConf['repository'])) {
                    $id = $mongoConf['repository'];
                } else {
                    if (class_exists($mongoConf['repository'])) {

                        if (!in_array('Pouzor\Mongo\Repository\RepositoryInterface', class_implements($mongoConf['repository']))) {
                            throw new \Exception(
                                sprintf(
                                    "The class %s must implements Pouzor\\Mongo\\Repository\\RepositoryInterface in order to work as a mongo repository",
                                    $mongoConf['repository']
                                )
                            );
                        }

                        $id = sprintf('mongobundle.repository.%s', $collection);
                        $container
                            ->register($id, $mongoConf['repository'])
                            ->setProperty('parent', 'mongobundle.repository.abstract')
                            ->addTag('mongo.repository', ['collection' => $collection])
                            ->addMethodCall('setManager', array($mongoManagerRef));
                    }
                }


                if ($id != null && $container->has($id)) {

                    $container->getDefinition('mongo.pool')->addMethodCall(
                        'add',
                        array(new Reference($id))
                    );
                }
            }
        }
    }

}
