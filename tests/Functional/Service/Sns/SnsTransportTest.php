<?php declare(strict_types=1);

namespace Bref\Symfony\Messenger\Test\Functional\Service\Sns;

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Bref\Symfony\Messenger\Service\Sns\SnsTransport;
use Bref\Symfony\Messenger\Service\Sns\SnsTransportFactory;
use Bref\Symfony\Messenger\Test\Functional\BaseFunctionalTest;
use Bref\Symfony\Messenger\Test\Resources\TestMessage\TestMessage;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class SnsTransportTest extends BaseFunctionalTest
{
    protected function getDefaultConfig(): array
    {
        return ['sns.yaml'];
    }

    public function test factory(): void
    {
        /** @var SnsTransportFactory $factory */
        $factory = $this->container->get(SnsTransportFactory::class);
        $this->assertInstanceOf(SnsTransportFactory::class, $factory);

        $this->assertTrue($factory->supports('sns://arn:aws:sns:us-east-1:1234567890:test', []));
        $this->assertFalse($factory->supports('https://example.com', []));
        $this->assertFalse($factory->supports('arn:aws:sns:us-east-1:1234567890:test', []));

        $transport = $factory->createTransport('sns://arn:aws:sns:us-east-1:1234567890:test', [], new PhpSerializer);
        $this->assertInstanceOf(SnsTransport::class, $transport);
    }

    public function test send message(): void
    {
        /** @var MockHandler $mock */
        $mock = $this->container->get('mock_handler');
        $topicArn = '';
        $mock->append(function (CommandInterface $cmd, RequestInterface $request) use (&$topicArn) {
            $body = (string) $request->getBody();
            parse_str(urldecode($body), $parsedBody);
            $topicArn = $parsedBody['TopicArn'];

            return new Result(['MessageId' => 'abcd']);
        });

        /** @var MessageBusInterface $bus */
        $bus = $this->container->get(MessageBusInterface::class);
        $bus->dispatch(new TestMessage('hello'));

        // Check that the URL is correct
        $this->assertEquals('arn:aws:sns:us-east-1:1234567890:test', $topicArn);
    }
}
