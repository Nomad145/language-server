<?php

declare(strict_types=1);

namespace LanguageServer\RPC;

use Psr\Container\ContainerInterface;
use React\Promise\Promise;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;

/**
 * @author Michael Phillips <michael.phillips@realpage.com>
 */
class RpcServer
{
    private $container;
    private $serializer;

    public function __construct(ContainerInterface $container, MessageSerializer $serializer)
    {
        $this->container = $container;
        $this->serializer = $serializer;
    }

    public function listen(ServerInterface $socker): void
    {
        $socker->on('connection', [$this, 'handleRequest']);
    }

    public function handleRequest(ConnectionInterface $connection): void
    {
        $this->serializer->on('deserialize', function (RequestMessage $request) {
            $result = $this->invokeRemoteMethod($request);

            if ($result instanceof Promise) {
                $result->then(
                    function ($result) use ($request) {
                        $response = new ResponseMessage();
                        $response->id = $request->id;
                        $response->result = $result;

                        $this->serializer->serialize($request->id, $response);
                    },
                    function ($error) use ($request) {
                        $response = new ResponseMessage();
                        $response->id = $request->id;
                        $response->error = $error;

                        $this->serializer->serialize($request->id, $response);
                    }
                );
            }
        });

        $this->serializer->on('serialize', function (string $response) use ($connection) {
            $connection->write($response);
        });

        $connection->on('data', [$this->serializer, 'deserialize']);
    }

    private function invokeRemoteMethod(RequestMessage $request): ?object
    {
        $object = $this->container->get($request->method);

        return $object->__invoke($request->params);
    }
}