<?php

/*
 * This file is part of the Ivory Http Adapter bundle package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Ivory\HttpAdapterBundle\Tests;

use Ivory\HttpAdapterBundle\IvoryHttpAdapterBundle;

/**
 * @author GeLo <geloen.eric@gmail.com>
 */
class IvoryHttpAdapterBundleTest extends AbstractTestCase
{
    /**
     * @var IvoryHttpAdapterBundle
     */
    private $bundle;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->bundle = new IvoryHttpAdapterBundle();
    }

    public function testInheritance()
    {
        $this->assertInstanceOf('Symfony\Component\HttpKernel\Bundle\Bundle', $this->bundle);
    }

    public function testRegisterListenerCompilerPass()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')
            ->setMethods(['addCompilerPass'])
            ->getMock();

        $container
            ->expects($this->once())
            ->method('addCompilerPass')
            ->with($this->isInstanceOf(
                'Ivory\HttpAdapterBundle\DependencyInjection\Compiler\RegisterListenerCompilerPass'
            ));

        $this->bundle->build($container);
    }
}
