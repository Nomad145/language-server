<?php

declare(strict_types=1);

namespace LanguageServer\Server\Serializer;

use LanguageServer\Server\MessageSerializer as MessageSerializerInterface;
use LanguageServer\Server\Protocol\Message;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;

use function assert;

class MessageSerializer implements MessageSerializerInterface
{
    private SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function deserialize(string $request): ?Message
    {
        try {
            $message = $this->serializer->deserialize($request, Message::class, 'jsonrpc');

            assert($message instanceof Message);

            return $message;
        } catch (NotEncodableValueException $e) {
            return null;
        }
    }

    public function serialize(Message $response): string
    {
        try {
            return $this->serializer->serialize($response, 'jsonrpc');
        } catch (NotEncodableValueException $e) {
            return '{}';
        }
    }
}
