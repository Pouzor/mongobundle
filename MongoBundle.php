<?php

namespace Pouzor\MongoBundle;

use Pouzor\MongoBundle\DependencyInjection\CompilerPass\RepositoryCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class MongoBundle
 * @package Pouzor\MongoBundle
 */
class MongoBundle extends Bundle
{

    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new RepositoryCompilerPass());

    }
}
