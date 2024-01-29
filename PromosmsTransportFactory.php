<?php

namespace TKusy\Promosms;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

final class PromosmsTransportFactory extends AbstractTransportFactory
{
    /**
     * @return PromosmsTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('promosms' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'promosms', $this->getSupportedSchemes());
        }

        $login = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $from = $dsn->getRequiredOption('from');
        $type = (int) $dsn->getOption('type', PromosmsTransport::TYPE_ECO);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new PromosmsTransport($login, $password, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port)->setType($type);
    }

    protected function getSupportedSchemes(): array
    {
        return ['promosms'];
    }
}
