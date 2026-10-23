<?php

declare(strict_types=1);

namespace App\Withdrawal\Mailer;

use App\Withdrawal\Entity\WithdrawalRequest;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final readonly class WithdrawalMailer
{
    public const CUSTOMER = 'app_withdrawal_customer';

    public const ADMIN = 'app_withdrawal_admin';

    public function __construct(
        private SenderInterface $sender,
        private ChannelContextInterface $channelContext,
        private LoggerInterface $logger,
        private string $localeCode,
    ) {
    }

    public function send(WithdrawalRequest $request): void
    {
        $channel = $this->channelContext->getChannel();
        $data = ['withdrawal' => $request, 'channel' => $channel, 'localeCode' => $this->localeCode];

        $this->deliver(self::CUSTOMER, (string) $request->getEmail(), $data, []);

        $contactEmail = $channel instanceof ChannelInterface ? $channel->getContactEmail() : null;
        if ($contactEmail !== null && $contactEmail !== '') {
            $this->deliver(self::ADMIN, $contactEmail, $data, [(string) $request->getEmail()]);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $replyTo
     */
    private function deliver(string $code, string $recipient, array $data, array $replyTo): void
    {
        try {
            $this->sender->send($code, [$recipient], $data, [], $replyTo);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('E-mail {code} to {recipient} failed: {message}', [
                'code' => $code,
                'recipient' => $recipient,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
