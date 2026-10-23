<?php

declare(strict_types=1);

namespace App\Tests\Inquiry;

use App\Inquiry\Security\RecaptchaVerifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RecaptchaVerifierTest extends TestCase
{
    public function testDisabledWithoutKeysAcceptsEverything(): void
    {
        $verifier = new RecaptchaVerifier(new MockHttpClient(), new NullLogger(), '', '');

        self::assertFalse($verifier->isEnabled());
        self::assertNull($verifier->getSiteKey());
        self::assertTrue($verifier->verify(null, '127.0.0.1'));
    }

    public function testMissingTokenIsRejectedWhenEnabled(): void
    {
        $verifier = new RecaptchaVerifier(new MockHttpClient(), new NullLogger(), 'site', 'secret');

        self::assertFalse($verifier->verify('', '127.0.0.1'));
    }

    public function testUsesGoogleVerdict(): void
    {
        $client = new MockHttpClient([
            new JsonMockResponse(['success' => true]),
            new JsonMockResponse(['success' => false, 'error-codes' => ['invalid-input-response']]),
        ]);
        $verifier = new RecaptchaVerifier($client, new NullLogger(), 'site', 'secret');

        self::assertTrue($verifier->verify('token', '127.0.0.1'));
        self::assertFalse($verifier->verify('token', '127.0.0.1'));
    }

    public function testTransportFailureIsRejected(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['error' => 'timeout']));
        $verifier = new RecaptchaVerifier($client, new NullLogger(), 'site', 'secret');

        self::assertFalse($verifier->verify('token', null));
    }
}
