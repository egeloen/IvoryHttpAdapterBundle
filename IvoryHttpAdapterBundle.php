<?php

/*
 * This file is part of the Ivory Http Adapter bundle package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Ivory\HttpAdapterBundle;

use Ivory\HttpAdapterBundle\DependencyInjection\Compiler\RegisterListenerCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Ivory http adapter bundle.
 *
 * @author GeLo <geloen.eric@gmail.com>
 */
class IvoryHttpAdapterBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new RegisterListenerCompilerPass());
    }
}
