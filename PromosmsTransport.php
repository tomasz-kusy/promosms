<?php

namespace TKusy\Promosms;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PromosmsTransport extends AbstractTransport
{
    public const TYPE_FLASH = 0;
    public const TYPE_ECO = 1;
    public const TYPE_FULL = 3;
    public const TYPE_SPEED = 4;

    protected const HOST = 'promosms.com';

    private $authToken;
    private $from;
    private $type = self::TYPE_ECO;

    public function __construct(string $login, string $password, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->authToken = base64_encode($login . ':' . $password);
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function setType(int $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function __toString(): string
    {
        $query = array_filter([
            'from' => $this->from,
            'type' => $this->type,
        ]);

        return sprintf('promosms://%s%s', $this->getEndpoint(),  $query ? '?' . http_build_query($query) : '');
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $endpoint = sprintf('https://%s/api/rest/v3_2/sms', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'Authorization' => 'Basic ' . $this->authToken,
                'Accept' => 'text/json'
            ],
            'json' => [
                'type' => $this->type,
                'sender' => $this->from,
                'recipients' => [$message->getPhone()],
                'text' => $message->getSubject(),
                'long-sms' => 1,
                'special-chars' => 1,
                'return-send-recipients' => 1
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Promosms server.', $response, 0, $e);
        }

        try {
            $content = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new TransportException('Could not decode body to an array.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new TransportException(sprintf('[%d] Unable to send the SMS: "%s".', $statusCode, $content['status'] ?? 'unknown error'), $response);
        }

        $messageStatus = $content['response']['recipientsResults'][0]['status'] ?? $content['response']['status'];

        if ($messageStatus !== 0) {
            throw new TransportException(sprintf('Unable to send the SMS: "%s".', $messageStatus ?? 'unknown error'), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($content['response']['recipientsResults'][0]['sms-id']);

        return $sentMessage;
    }
}
