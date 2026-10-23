<?php

declare(strict_types=1);

namespace App\Tests\Withdrawal;

use App\Withdrawal\Entity\WithdrawalRequest;
use PHPUnit\Framework\TestCase;

final class WithdrawalRequestTest extends TestCase
{
    public function testDraftRoundTripTrimsAndDropsEmptyValues(): void
    {
        $request = WithdrawalRequest::fromDraft([
            'fullName' => '  Jan Novák ',
            'email' => 'jan@example.test',
            'phone' => '   ',
            'orderNumber' => '365',
            'items' => 'Celá objednávka',
            'note' => ['not', 'a', 'string'],
        ]);

        self::assertSame('Jan Novák', $request->getFullName());
        self::assertNull($request->getPhone());
        self::assertNull($request->getNote());
        self::assertSame('365', $request->getOrderNumber());

        $copy = WithdrawalRequest::fromDraft($request->toDraft());
        self::assertSame($request->toDraft(), $copy->toDraft());
        self::assertNotSame($request->getToken(), $copy->getToken());
    }

    public function testSubmissionMetadataAndHandling(): void
    {
        $request = new WithdrawalRequest();
        $submittedAt = new \DateTimeImmutable('2027-01-04 10:15:30');
        $request->markSubmitted($submittedAt, '127.0.0.1', str_repeat('a', 300));

        self::assertSame($submittedAt, $request->getSubmittedAt());
        self::assertSame(255, mb_strlen((string) $request->getUserAgent()));
        self::assertFalse($request->isOrderMatched());
        self::assertFalse($request->isHandled());

        $request->markHandled(new \DateTimeImmutable());
        self::assertTrue($request->isHandled());
        self::assertSame(32, strlen($request->getToken()));
    }
}
