<?php

namespace OpenStack\Test\Common\Resource;

use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use OpenStack\Common\Resource\AbstractResource;
use OpenStack\Common\Api\Operation;
use OpenStack\Common\Resource\Generator;
use OpenStack\Compute\v2\Models\Server;
use OpenStack\Test\TestCase;
use OpenStack\Compute\v2\Service;
use Prophecy\Argument;
use Prophecy\Promise\ReturnArgumentPromise;
use Prophecy\Prophecy\MethodProphecy;
use Prophecy\Prophecy\ObjectProphecy;

class AbstractResourceTest extends TestCase
{
    private $resource;

    public function setUp()
    {
        parent::setUp();

        $this->rootFixturesDir = __DIR__;
        $this->resource = new TestResource(new Client());
    }

    public function test_it_populates_from_response()
    {
        $response = new Response(200, [], Stream::factory(
            json_encode(['foo' => ['bar' => '1']])
        ));

        $this->resource->populateFromResponse($response);

        $this->assertEquals('1', $this->resource->bar);
    }

    public function test_it_gets_attrs()
    {
        $this->resource->bar = 'foo';

        $this->assertEquals(['bar' => 'foo'], $this->resource->getAttrs(['bar']));
    }

    public function test_it_executes_operations_until_an_empty_response_is_received()
    {
        $operation = $this->prophesize(Operation::class);
        $operation->getValue('limit')->willReturn(null);
        $operation->hasParam('marker')->willReturn(true);

        $response1 = $this->getFixture('servers-page1');
        $response2 = $this->getFixture('servers-page2');
        $response3 = $this->getFixture('servers-empty');

        $operation->send()->willReturn($response1);

        $operation->setValue('marker', Argument::any())->shouldBeCalled();

        $operation->setValue('marker', '5')->will(function() use ($response2) {
            $this->send()->willReturn($response2);
        });

        $operation->setValue('marker', '10')->will(function() use ($response3) {
            $this->send()->willReturn($response3);
        });

        $count = 0;

        foreach ($this->resource->enumerate($operation->reveal()) as $item) {
            $count++;
            $this->assertInstanceOf(TestResource::class, $item);
        }

        $this->assertEquals(10, $count);
    }

    public function test_iteration_halts_when_total_has_been_reached()
    {
        $operation = $this->prophesize(Operation::class);
        $operation->getValue('limit')->willReturn(8);
        $operation->hasParam('marker')->willReturn(true);

        $response1 = $this->getFixture('servers-page1');
        $response2 = $this->getFixture('servers-page2');
        $response3 = $this->getFixture('servers-empty');

        $operation->send()->willReturn($response1);

        $operation->setValue('marker', Argument::any())->shouldBeCalled();

        $operation->setValue('marker', '5')->will(function() use ($response2) {
            $this->send()->willReturn($response2);
        });

        $operation->setValue('marker', '10')->will(function() use ($response3) {
            $this->send()->willReturn($response3);
        });

        $count = 0;

        foreach ($this->resource->enumerate($operation->reveal()) as $item) {
            $count++;
        }

        $this->assertEquals(8, $count);
    }
}

class TestResource extends AbstractResource
{
    protected $resourceKey = 'foo';
    protected $resourcesKey = 'servers';
    protected $markerKey = 'id';

    public $bar;
    public $id;

    public function getAttrs(array $keys)
    {
        return parent::getAttrs($keys);
    }
}

class ReturnServerPromise extends ReturnArgumentPromise
{
    public function execute(array $args, ObjectProphecy $object, MethodProphecy $method)
    {
        $server = new Server(new Client());
        $server->populateFromArray($args[1]);
        return $server;
    }
}