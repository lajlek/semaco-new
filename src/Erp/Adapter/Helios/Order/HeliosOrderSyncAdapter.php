<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Order;

use App\Erp\Adapter\Helios\Infrastructure\HeliosConnectionFactory;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRow;
use App\Erp\Adapter\Helios\Infrastructure\ReadOnly\HeliosWriteForbiddenException;
use App\Erp\Port\Common\Exception\ErpValidationException;
use App\Erp\Port\Order\Dto\OrderLine;
use App\Erp\Port\Order\Dto\OrderParty;
use App\Erp\Port\Order\Dto\OrderPushRequest;
use App\Erp\Port\Order\OrderSyncInterface;
use Doctrine\DBAL\Connection;

final readonly class HeliosOrderSyncAdapter implements OrderSyncInterface
{
    private const AUTHOR = 'eshop';

    public function __construct(private HeliosConnectionFactory $connections)
    {
    }

    public function push(OrderPushRequest $request): string
    {
        if (!$this->connections->isWriteEnabled()) {
            throw HeliosWriteForbiddenException::forStatement('INSERT INTO SapTabObjHla');
        }

        $connection = $this->connections->getConnection();
        $existing = $connection->fetchOne(
            'SELECT TOP 1 ID FROM SapTabObjHla WHERE navaznaobjednavka = :number AND (chyba IS NULL OR chyba = 0)',
            ['number' => $request->orderNumber],
        );
        if (is_scalar($existing) && $existing !== '') {
            return (string) $existing;
        }

        $now = new \DateTimeImmutable();
        $importOrganizationId = $request->guestBilling === null ? null : $this->insertGuest($connection, $request, $now);
        $headerId = $this->insertHeader($connection, $request, $importOrganizationId, $now);
        foreach ($request->lines as $line) {
            $this->insertLine($connection, $headerId, $line, $now);
        }

        $this->runImportProcedure($connection, $headerId);

        $result = $connection->fetchAssociative('SELECT chyba, textchyby FROM SapTabObjHla WHERE ID = :id', ['id' => $headerId]);
        $errorCode = $result === false ? 0 : (HeliosRow::int($result, 'chyba') ?? 0);
        if ($errorCode !== 0) {
            $message = HeliosRow::string($result, 'textchyby');

            throw new ErpValidationException(
                message: sprintf('Helios odmítl objednávku %s: %s', $request->orderNumber, $message ?? (string) $errorCode),
                erpMessage: $message,
            );
        }

        return (string) $headerId;
    }

    private function insertHeader(Connection $connection, OrderPushRequest $request, ?int $importOrganizationId, \DateTimeImmutable $now): int
    {
        $contactPerson = null;
        if ($request->contactPersonExternalIdentifier !== null) {
            $value = $connection->fetchOne(
                'SELECT TOP 1 idciskos FROM hvw_SapertaTabSapPrihlOsoby WHERE id = :id',
                ['id' => (int) $request->contactPersonExternalIdentifier],
            );
            $contactPerson = is_numeric($value) ? (int) $value : null;
        }

        $id = $connection->fetchOne(
            'INSERT INTO SapTabObjHla (ideshop, DatPorizeni, Autor, datum, navaznaobjednavka, idorg, idorgo, kontaktosoba,
                idsaptabimportorg, mena, jednotkameny, sumamena, zpusobvykryti, zpusobzaokrouhleni, formauhrady, formadopravy, poznamka)
             OUTPUT INSERTED.ID
             VALUES (:ideshop, :created, :author, :datum, :number, :idorg, :idorgo, :contact,
                :importorg, :currency, 1, :total, 0, 1, :payment, :shipping, :notes)',
            [
                'ideshop' => (int) $request->shopExternalIdentifier,
                'created' => $now->format('Y-m-d H:i:s'),
                'author' => self::AUTHOR,
                'datum' => $request->completedAt->format('Y-m-d H:i:s'),
                'number' => $request->orderNumber,
                'idorg' => $request->organizationExternalIdentifier === null ? null : (int) $request->organizationExternalIdentifier,
                'idorgo' => self::numericOrNull($request->deliveryAddressExternalIdentifier),
                'contact' => $contactPerson,
                'importorg' => $importOrganizationId,
                'currency' => $request->currencyCode,
                'total' => $request->total,
                'payment' => $request->paymentMethod,
                'shipping' => $request->shippingMethod,
                'notes' => $request->notes,
            ],
        );

        return self::insertedId($id);
    }

    private function insertLine(Connection $connection, int $headerId, OrderLine $line, \DateTimeImmutable $now): void
    {
        $connection->executeStatement(
            'INSERT INTO SapTabObjPol (iddoklad, DatPorizeni, Autor, idkmenzbozi, idkategorie, typpolozky, mj, sazbadph, mnozstvi,
                cenacelkem, bezdph, sapzakladnicena, ccsdphpos, slevaorg, slevaskupzbo, slevasozna, slevazbokmen, slevazbosklad, vstupnicena)
             VALUES (:header, :created, :author, :variant, 0, :type, \'ks\', :vat, :quantity,
                :total, :priceType, :total, :total, 0, 0, 0, 0, 0, 7)',
            [
                'header' => $headerId,
                'created' => $now->format('Y-m-d H:i:s'),
                'author' => self::AUTHOR,
                'variant' => $line->variantExternalIdentifier === null ? 0 : (int) $line->variantExternalIdentifier,
                'type' => $line->type,
                'vat' => $line->vatRate,
                'quantity' => $line->quantity,
                'total' => $line->total,
                'priceType' => $line->priceType,
            ],
        );
    }

    private function insertGuest(Connection $connection, OrderPushRequest $request, \DateTimeImmutable $now): int
    {
        $billing = $request->guestBilling;
        \assert($billing instanceof OrderParty);
        $shipping = $request->guestShipping;

        $id = $connection->fetchOne(
            'INSERT INTO SapTabImportOrg (DatPorizeni, Autor, ideshopu, lhutasplatnosti, pravniforma, elfak, news, newso, sapertasouhlaso,
                nazev, jmeno, prijmeni, ulice, misto, psc, idzeme, telefon, mail, ico, dic,
                nazevo, jmenoo, prijmenio, uliceo, mistoo, psco, idzemeo, telefono, mailo)
             OUTPUT INSERTED.id
             VALUES (:created, :author, :shop, 0, :legalForm, 0, 0, 0, 1,
                :company, :firstName, :lastName, :street, :city, :postcode, :country, :phone, :email, :ico, :dic,
                :companyO, :firstNameO, :lastNameO, :streetO, :cityO, :postcodeO, :countryO, :phoneO, :emailO)',
            [
                'created' => $now->format('Y-m-d H:i:s'),
                'author' => self::AUTHOR,
                'shop' => (int) $request->shopExternalIdentifier,
                'legalForm' => $billing->ico !== null ? 0 : 1,
                'company' => $billing->company,
                'firstName' => $billing->firstName,
                'lastName' => $billing->lastName,
                'street' => $billing->street,
                'city' => $billing->city,
                'postcode' => $billing->postcode,
                'country' => $billing->countryCode,
                'phone' => $billing->phone,
                'email' => $billing->email,
                'ico' => $billing->ico,
                'dic' => $billing->dic,
                'companyO' => $shipping?->company,
                'firstNameO' => $shipping?->firstName,
                'lastNameO' => $shipping?->lastName,
                'streetO' => $shipping?->street,
                'cityO' => $shipping?->city,
                'postcodeO' => $shipping?->postcode,
                'countryO' => $shipping?->countryCode,
                'phoneO' => $shipping?->phone,
                'emailO' => $shipping?->email,
            ],
        );

        return self::insertedId($id);
    }

    private function runImportProcedure(Connection $connection, int $headerId): void
    {
        $native = $connection->getNativeConnection();
        if (!$native instanceof \PDO) {
            throw new \RuntimeException('Helios connection is not PDO.');
        }

        $statement = $native->prepare('EXEC dbo.ar_importdokladuzeshopu @selectem = 1, @id = ?');
        $statement->bindValue(1, $headerId, \PDO::PARAM_INT);
        $statement->execute();
        do {
            $statement->fetchAll();
        } while ($statement->nextRowset());
    }

    private static function insertedId(mixed $id): int
    {
        if (!is_numeric($id)) {
            throw new \RuntimeException('Helios nevrátil ID vloženého záznamu.');
        }

        return (int) $id;
    }

    private static function numericOrNull(?string $value): ?int
    {
        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }
}
