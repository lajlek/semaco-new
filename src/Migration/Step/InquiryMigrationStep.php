<?php

declare(strict_types=1);

namespace App\Migration\Step;

use App\Erp\Application\ImportResult;
use App\Inquiry\Entity\ContactMessage;
use App\Inquiry\Entity\ContactMessageAnswer;
use App\Inquiry\Entity\RegistrationRequest;
use App\Migration\OldShop\OldShopConnectionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 60)]
final readonly class InquiryMigrationStep implements MigrationStepInterface
{
    public function __construct(
        private OldShopConnectionFactory $oldShop,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function name(): string
    {
        return 'inquiries';
    }

    public function run(bool $dryRun): ImportResult
    {
        $result = new ImportResult('inquiries');
        $this->migrateRegistrationRequests($result);
        $this->migrateContactMessages($result);

        if (!$dryRun) {
            $this->entityManager->flush();
        }
        $this->entityManager->clear();

        return $result;
    }

    private function migrateRegistrationRequests(ImportResult $result): void
    {
        /** @var list<array<string, string|int|null>> $rows */
        $rows = $this->oldShop->getConnection()->fetchAllAssociative(
            'SELECT id, customerFirstName, customerLastName, email, phone, message, createdAt, userAgent, ip,
                companyName, street, streetNumber, city, postcode, identificationNumber
             FROM mergocz_registration_request_form ORDER BY id ASC',
        );

        foreach ($rows as $row) {
            $legacyId = (int) $row['id'];
            $request = $this->entityManager->getRepository(RegistrationRequest::class)->findOneBy(['legacyId' => $legacyId]);
            if (!$request instanceof RegistrationRequest) {
                $request = new RegistrationRequest();
                $request->setLegacyId($legacyId);
                $this->entityManager->persist($request);
                $result->created();
            } else {
                $result->updated();
            }

            $request->setFirstName(self::text($row, 'customerFirstName'));
            $request->setLastName(self::text($row, 'customerLastName'));
            $request->setEmail(self::text($row, 'email'));
            $request->setPhone(self::text($row, 'phone'));
            $request->setMessage(self::text($row, 'message'));
            $request->setCreatedAt(self::date($row, 'createdAt'));
            $request->setUserAgent(self::text($row, 'userAgent'));
            $request->setIp(self::text($row, 'ip'));
            $request->setCompanyName(self::text($row, 'companyName'));
            $request->setStreet(self::text($row, 'street'));
            $request->setStreetNumber(self::text($row, 'streetNumber'));
            $request->setCity(self::text($row, 'city'));
            $request->setPostcode(self::text($row, 'postcode'));
            $request->setIdentificationNumber(self::text($row, 'identificationNumber'));
        }
    }

    private function migrateContactMessages(ImportResult $result): void
    {
        $connection = $this->oldShop->getConnection();

        /** @var list<array<string, string|int|null>> $rows */
        $rows = $connection->fetchAllAssociative(
            'SELECT id, customerFirstName, customerLastName, email, phone, message, createdAt, userAgent, ip
             FROM mergocz_contact_form ORDER BY id ASC',
        );

        /** @var list<array<string, string|int|null>> $answerRows */
        $answerRows = $connection->fetchAllAssociative(
            'SELECT id, message, createdAt, contactFormMessage_id, adminUser_id
             FROM mergocz_contact_form_message_answer ORDER BY id ASC',
        );
        $answersByMessage = [];
        foreach ($answerRows as $answerRow) {
            $answersByMessage[(int) $answerRow['contactFormMessage_id']][] = $answerRow;
        }

        foreach ($rows as $row) {
            $legacyId = (int) $row['id'];
            $message = $this->entityManager->getRepository(ContactMessage::class)->findOneBy(['legacyId' => $legacyId]);
            if (!$message instanceof ContactMessage) {
                $message = new ContactMessage();
                $message->setLegacyId($legacyId);
                $this->entityManager->persist($message);
                $result->created();
            } else {
                $result->updated();
            }

            $message->setFirstName(self::text($row, 'customerFirstName'));
            $message->setLastName(self::text($row, 'customerLastName'));
            $message->setEmail(self::text($row, 'email'));
            $message->setPhone(self::text($row, 'phone'));
            $message->setMessage(self::text($row, 'message') ?? '');
            $message->setCreatedAt(self::date($row, 'createdAt'));
            $message->setUserAgent(self::text($row, 'userAgent'));
            $message->setIp(self::text($row, 'ip'));

            foreach ($answersByMessage[$legacyId] ?? [] as $answerRow) {
                $answerLegacyId = (int) $answerRow['id'];
                $answer = null;
                foreach ($message->getAnswers() as $existing) {
                    if ($existing->getLegacyId() === $answerLegacyId) {
                        $answer = $existing;
                    }
                }
                if ($answer === null) {
                    $answer = new ContactMessageAnswer($message);
                    $answer->setLegacyId($answerLegacyId);
                    $message->addAnswer($answer);
                }

                $answer->setMessage(self::text($answerRow, 'message') ?? '');
                $answer->setCreatedAt(self::date($answerRow, 'createdAt'));
                $answer->setFromAdmin($answerRow['adminUser_id'] !== null);
            }
        }
    }

    /** @param array<string, string|int|null> $row */
    private static function text(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @param array<string, string|int|null> $row */
    private static function date(array $row, string $key): \DateTimeImmutable
    {
        $value = self::text($row, $key);

        return $value === null ? new \DateTimeImmutable() : new \DateTimeImmutable($value);
    }
}
