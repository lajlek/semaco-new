<?php

declare(strict_types=1);

namespace App\Inquiry\Security;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class RecaptchaVerifier
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $siteKey,
        private string $secret,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->siteKey !== '' && $this->secret !== '';
    }

    public function getSiteKey(): ?string
    {
        return $this->isEnabled() ? $this->siteKey : null;
    }

    public function verify(?string $token, ?string $clientIp): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        if ($token === null || $token === '') {
            return false;
        }

        try {
            $payload = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => array_filter(['secret' => $this->secret, 'response' => $token, 'remoteip' => $clientIp]),
                'timeout' => 5,
            ])->toArray(false);
        } catch (ExceptionInterface $exception) {
            $this->logger->warning('reCAPTCHA verification failed: {message}', ['message' => $exception->getMessage()]);

            return false;
        }

        return ($payload['success'] ?? false) === true;
    }
}
