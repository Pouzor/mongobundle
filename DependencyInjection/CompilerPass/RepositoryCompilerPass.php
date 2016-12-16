<?php

namespace Pouzor\MongoBundle\DependencyInjection\CompilerPass;


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class RepositoryCompilerPass implements CompilerPassInterface
{

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     *
     * @api
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('mongo.pool')) {
            return;
        }

        $poolDefinition = $container->getDefinition('mongo.pool');

        $taggedServices = $container->findTaggedServiceIds(
            'mongo.repository'
        );

        foreach ($taggedServices as $id => $tags) {

            $definition = $container->getDefinition($id);

            foreach ($tags as $tag) {

                $isParam = (boolean) preg_match(
                        '/%(?P<param>\w*[\.*\w*]*)%/',
                        $tag['collection'],
                        $matches
                    ) && $container->hasParameter($matches['param']);

                $col = $isParam ? $container->getParameter($matches['param']) : $tag['collection'];
                $con = isset($tag['connection']) ? $tag['connection'] : null;

                $definition->addMethodCall('setDefaultConnection', array($con));
                $definition->addMethodCall('setCollection', array($col));


                if (isset($container->getParameter('mongobundle.mongo.bindings')[$col])) {
                    $definition->addMethodCall(
                        'setBindingConfiguration',
                        array($container->getParameter('mongobundle.mongo.bindings')[$col])
                    );
                }

                if ($container->hasParameter($param = sprintf('mongobundle.repository.%s.config', $col))) {
                    if (isset($container->getParameter($param)['indexes'])) {
                        $definition->addMethodCall('setIndexes', array($container->getParameter($param)['indexes']));
                    }

                }
            }

            $poolDefinition->addMethodCall('add', array(new Reference($id)));
        }
    }

}
