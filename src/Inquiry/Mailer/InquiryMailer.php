<?php

declare(strict_types=1);

namespace App\Inquiry\Mailer;

use App\Inquiry\Entity\ContactMessage;
use App\Inquiry\Entity\ContactMessageAnswer;
use App\Inquiry\Entity\RegistrationRequest;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final readonly class InquiryMailer
{
    public const REGISTRATION_REQUEST_ADMIN = 'app_registration_request_admin';

    public const REGISTRATION_REQUEST_CUSTOMER = 'app_registration_request_customer';

    public const CONTACT_MESSAGE_ADMIN = 'app_contact_message_admin';

    public const CONTACT_MESSAGE_CUSTOMER = 'app_contact_message_customer';

    public const CONTACT_MESSAGE_ANSWER = 'app_contact_message_answer';

    public function __construct(
        private SenderInterface $sender,
        private ChannelContextInterface $channelContext,
        private LoggerInterface $logger,
        private string $localeCode,
    ) {
    }

    public function sendRegistrationRequest(RegistrationRequest $request): void
    {
        $data = ['registrationRequest' => $request];
        $this->sendToShop(self::REGISTRATION_REQUEST_ADMIN, $data, $request->getEmail());
        $this->send(self::REGISTRATION_REQUEST_CUSTOMER, $request->getEmail(), $data);
    }

    public function sendContactMessage(ContactMessage $message): void
    {
        $data = ['contactMessage' => $message];
        $this->sendToShop(self::CONTACT_MESSAGE_ADMIN, $data, $message->getEmail());
        $this->send(self::CONTACT_MESSAGE_CUSTOMER, $message->getEmail(), $data);
    }

    public function sendCustomerReply(ContactMessageAnswer $answer): void
    {
        $message = $answer->getContactMessage();
        $this->sendToShop(self::CONTACT_MESSAGE_ADMIN, ['contactMessage' => $message, 'answer' => $answer], $message->getEmail());
    }

    public function sendAdminAnswer(ContactMessageAnswer $answer): void
    {
        $message = $answer->getContactMessage();
        $this->send(self::CONTACT_MESSAGE_ANSWER, $message->getEmail(), ['contactMessage' => $message, 'answer' => $answer]);
    }

    /** @param array<string, mixed> $data */
    private function sendToShop(string $code, array $data, ?string $replyTo): void
    {
        $channel = $this->channelContext->getChannel();
        $contactEmail = $channel instanceof ChannelInterface ? $channel->getContactEmail() : null;
        if ($contactEmail === null || $contactEmail === '') {
            $this->logger->warning('Channel has no contact e-mail, {code} not sent.', ['code' => $code]);

            return;
        }

        $this->send($code, $contactEmail, $data, $replyTo === null ? [] : [$replyTo]);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $replyTo
     */
    private function send(string $code, ?string $recipient, array $data, array $replyTo = []): void
    {
        if ($recipient === null || $recipient === '') {
            return;
        }

        try {
            $this->sender->send($code, [$recipient], $data + [
                'channel' => $this->channelContext->getChannel(),
                'localeCode' => $this->localeCode,
            ], [], $replyTo);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('E-mail {code} to {recipient} failed: {message}', [
                'code' => $code,
                'recipient' => $recipient,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
