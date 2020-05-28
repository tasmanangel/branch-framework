<?php
declare(strict_types=1);

use Branch\App;
use Branch\Container\Resolver;
use Branch\Interfaces\Container\DefinitionInfoInterface;
use Branch\Tests\BaseTestCase;
use Branch\Tests\Mocks\Constructor\WithoutConstructor;
use Branch\Tests\Mocks\Constructor\WithParamNoType;
use Branch\Tests\Mocks\Constructor\WithParams;
use Branch\Tests\Mocks\Constructor\WithParamsNoType;
use Branch\Tests\Mocks\Constructor\WithParamsNoTypeDefault;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class ResolverTest extends BaseTestCase
{
    use ProphecyTrait;

    protected Resolver $resolver;

    protected $appProphecy;

    protected $definitionInfoProphecy;

    public function setUp(): void
    {
        $this->appProphecy = $this->prophesize(App::class);
        $this->definitionInfoProphecy = $this->prophesize(DefinitionInfoInterface::class);

        $this->resolver = new Resolver(
            $this->appProphecy->reveal(),
            $this->definitionInfoProphecy->reveal()
        );
    
    }

    public function testClosureResolved(): void
    {
        $this->definitionInfoProphecy->isClosureDefinition(
            Argument::type(\Closure::class)
        )
            ->willReturn(true)
            ->shouldBeCalledTimes(1);
        $this->definitionInfoProphecy->isArrayObjectDefinition()
            ->shouldNotBeCalled();
        $this->definitionInfoProphecy->isStringObjectDefinition()
            ->shouldNotBeCalled();

        $definition = fn(App $app): string => 'test string';

        $result = $this->resolver->resolve($definition);

        $this->assertSame('test string', $result);
    }

    public function testArrayObjectResolved(): void
    {
        $this->definitionInfoProphecy->isClosureDefinition(
            Argument::any()
        )
            ->willReturn(false)
            ->shouldBeCalledTimes(1);
        $this->definitionInfoProphecy->isArrayObjectDefinition(
            Argument::type('array')
        )
            ->willReturn(true)
            ->shouldBeCalledTimes(1);
        $this->definitionInfoProphecy->isStringObjectDefinition()
            ->shouldNotBeCalled();
        $this->appProphecy->has()
            ->shouldNotBeCalled();
        $this->appProphecy->get()
            ->shouldNotBeCalled();

        $definition = ['class' => WithoutConstructor::class];

        $result = $this->resolver->resolve($definition);

        $this->assertInstanceOf(WithoutConstructor::class, $result);
    }

    public function testStringObjectResolved(): void
    {
        $this->definitionInfoProphecy->isClosureDefinition(
            Argument::any()
        )
            ->willReturn(false)
            ->shouldBeCalledTimes(1);
        $this->definitionInfoProphecy->isArrayObjectDefinition(
            Argument::any()
        )
            ->willReturn(false)
            ->shouldBeCalledTimes(1);
        $this->definitionInfoProphecy->isStringObjectDefinition(
            Argument::type('string')
        )
            ->willReturn(true)
            ->shouldBeCalledTimes(1);
        $this->appProphecy->has()
            ->shouldNotBeCalled();
        $this->appProphecy->get()
            ->shouldNotBeCalled();

        $definition = WithoutConstructor::class;

        $result = $this->resolver->resolve($definition);

        $this->assertInstanceOf(WithoutConstructor::class, $result);
    }

    public function testOtherDefinitionResolved(): void
    {
        $this->definitionInfoProphecy->isClosureDefinition(
            Argument::any()
        )
            ->willReturn(false)
            ->shouldBeCalledTimes(3);
        $this->definitionInfoProphecy->isArrayObjectDefinition(
            Argument::any()
        )
            ->willReturn(false)
            ->shouldBeCalledTimes(3);
        $this->definitionInfoProphecy->isStringObjectDefinition(
            Argument::any()
        )
            ->willReturn(false)
            ->shouldBeCalledTimes(3);

        $stringDefinition = 'test string';
        $intDefinition = 3;
        $arrayDefinition = ['test'];

        $stringResult = $this->resolver->resolve($stringDefinition);
        $intResult = $this->resolver->resolve($intDefinition);
        $arrayResult = $this->resolver->resolve($arrayDefinition);

        $this->assertSame('test string', $stringResult);
        $this->assertSame(3, $intResult);
        $this->assertSame(['test'], $arrayResult);
    }

    public function testObjectWithoutDefinedConstructorResolved(): void
    {
        $this->appProphecy->has()
            ->shouldNotBeCalled();
        $this->appProphecy->get()
            ->shouldNotBeCalled();

        $result = $this->resolver->resolveObject([
            'class' => WithoutConstructor::class
        ]);

        $this->assertInstanceOf(WithoutConstructor::class, $result);
    }

    public function testObjectResolved(): void
    {
        $this->appProphecy->has()
            ->shouldNotBeCalled();
        $this->appProphecy->get()
            ->shouldNotBeCalled();

        $result = $this->resolver->resolveObject([
            'class' => WithParamsNoTypeDefault::class
        ]);

        $this->assertInstanceOf(WithParamsNoTypeDefault::class, $result);
    }

    public function testObjectResolvedWithArgs(): void
    {
        $this->appProphecy->has()
            ->shouldNotBeCalled();
        $this->appProphecy->get()
            ->shouldNotBeCalled();

        $result = $this->resolver->resolveObject([
            'class' => WithParams::class,
            'args' => [
                'string' => 'hello world',
                'int' => 11
            ],
        ]);

        $this->assertSame('hello world', $result->string);
        $this->assertSame(11, $result->int);
    }

    public function testArgsResolved(): void
    {
        $this->appProphecy->has(Argument::exact('string'))
            ->willReturn(true)
            ->shouldBeCalledTimes(1);
        $this->appProphecy->has(Argument::exact('int'))
            ->willReturn(false)
            ->shouldBeCalledTimes(1);
        $this->appProphecy->get(Argument::exact('string'))
            ->willReturn('test')
            ->shouldBeCalledTimes(1);
        $this->appProphecy->get(Argument::exact('int'))
            ->shouldNotBeCalled();
        
        $reflection = new \ReflectionClass(WithParams::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $arguments = $this->resolver->resolveArgs($parameters);

        $this->assertSame('test', $arguments[0]);
    }

    public function testArgsResolvedWithPredefined(): void
    {
        $this->appProphecy->has(Argument::exact('string'))
            ->willReturn(true)
            ->shouldBeCalledTimes(1);
        $this->appProphecy->has(Argument::exact('int'))
            ->shouldNotBeCalled();
        $this->appProphecy->get(Argument::exact('string'))
            ->willReturn('test')
            ->shouldBeCalledTimes(1);
        $this->appProphecy->get(Argument::exact('int'))
            ->shouldNotBeCalled();
        
        $reflection = new \ReflectionClass(WithParams::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $arguments = $this->resolver->resolveArgs($parameters, ['int' => 11]);

        $this->assertSame('test', $arguments[0]);
        $this->assertSame(11, $arguments[1]);
    }

    public function testArgsRrsolvedWithDefaults(): void
    {
        $this->appProphecy->has(Argument::any())
            ->shouldNotBeCalled();
        $this->appProphecy->get(Argument::any())
            ->shouldNotBeCalled();
        
        $reflection = new \ReflectionClass(WithParamsNoTypeDefault::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $arguments = $this->resolver->resolveArgs($parameters);

        $this->assertSame([], $arguments);
    }

    public function testErrorIsThrownIfTypeIsNotAvailable(): void
    {
        $this->appProphecy->has(Argument::any())
            ->shouldNotBeCalled();
        $this->appProphecy->get(Argument::any())
            ->shouldNotBeCalled();
        
        $reflection = new \ReflectionClass(WithParamsNoType::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->expectException(\LogicException::class);
        $this->resolver->resolveArgs($parameters);
    }
}