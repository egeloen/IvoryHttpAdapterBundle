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
 * Ivory http adapter bundle test.
 *
 * @author GeLo <geloen.eric@gmail.com>
 */
class IvoryHttpAdapterBundleTest extends \PHPUnit_Framework_TestCase
{
    private $bundle;

    protected function setUp()
    {
        $this->bundle = new IvoryHttpAdapterBundle();
    }

    protected function tearDown()
    {
        unset($this->bundle);
    }

    public function testInheritance()
    {
        $this->assertInstanceOf('Symfony\Component\HttpKernel\Bundle\Bundle', $this->bundle);
    }

    public function testRegisterListenerCompilerPass()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerBuilder');
        $container
            ->expects($this->once())
            ->method('addCompilerPass')
            ->with(
                $this->isInstanceOf('Ivory\HttpAdapterBundle\DependencyInjection\Compiler\RegisterListenerCompilerPass')
            );

        $this->bundle->build($container);
    }
}
