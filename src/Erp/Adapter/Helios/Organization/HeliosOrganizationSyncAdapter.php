<?php

declare(strict_types=1);

namespace App\Erp\Adapter\Helios\Organization;

use App\Erp\Adapter\Helios\Infrastructure\HeliosConnectionFactory;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRateLimiter;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRetryTrait;
use App\Erp\Adapter\Helios\Infrastructure\HeliosRow;
use App\Erp\Port\Customer\Dto\AddressSnapshot;
use App\Erp\Port\Customer\Dto\OrganizationSnapshot;
use App\Erp\Port\Customer\OrganizationSyncInterface;
use Doctrine\DBAL\ArrayParameterType;

final readonly class HeliosOrganizationSyncAdapter implements OrganizationSyncInterface
{
    use HeliosRetryTrait;

    private const BATCH_SIZE = 500;

    private const COLUMNS = 'id, idnadrazene, cisloorg, nazev, jmeno, prijmeni, ulice, popcislo, orcislo, misto, psc, isokod,
        ico, dic, cislouctu, kodustavu, telefon, cenovauroven, odpos, _typzakaznika, _SEMACO_NegenerovatDopravu, poslednizmena';

    public function __construct(
        private HeliosConnectionFactory $connections,
        private HeliosRateLimiter $rateLimiter,
    ) {
    }

    public function pullChangedSince(\DateTimeInterface $since): iterable
    {
        $offset = 0;
        while (true) {
            $this->rateLimiter->consume();
            $rows = $this->runWithRetry(fn () => $this->connections->getConnection()->fetchAllAssociative(
                sprintf(
                    'SELECT %s FROM hvw_SapertaTabSapCisOrg
                     WHERE fakturacni = 1 AND odpos IS NOT NULL AND poslednizmena > :since
                     ORDER BY poslednizmena ASC, id ASC
                     OFFSET %d ROWS FETCH NEXT %d ROWS ONLY',
                    self::COLUMNS,
                    $offset,
                    self::BATCH_SIZE,
                ),
                ['since' => HeliosRow::formatSince($since)],
            ));

            if ($rows === []) {
                return;
            }

            $children = $this->loadDescendants(array_values(array_filter(array_map(
                static fn (array $row): ?int => HeliosRow::int($row, 'id'),
                $rows,
            ))));

            foreach ($rows as $row) {
                yield $this->map($row, $children);
            }

            if (count($rows) < self::BATCH_SIZE) {
                return;
            }

            $offset += self::BATCH_SIZE;
            usleep(100_000);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, list<array<string, mixed>>> $children
     */
    private function map(array $row, array $children): OrganizationSnapshot
    {
        $id = HeliosRow::int($row, 'id') ?? 0;
        $account = HeliosRow::string($row, 'cislouctu');
        $bankCode = HeliosRow::string($row, 'kodustavu');

        $deliveryAddresses = [];
        $queue = $children[$id] ?? [];
        $visited = [$id => true];
        while ($queue !== []) {
            $child = array_shift($queue);
            $childId = HeliosRow::int($child, 'id') ?? 0;
            if (isset($visited[$childId])) {
                continue;
            }
            $visited[$childId] = true;
            $deliveryAddresses[] = $this->address($child);
            array_push($queue, ...($children[$childId] ?? []));
        }

        return new OrganizationSnapshot(
            externalIdentifier: (string) $id,
            orgNumber: HeliosRow::string($row, 'cisloorg'),
            name: HeliosRow::string($row, 'nazev') ?? (string) $id,
            ico: HeliosRow::string($row, 'ico'),
            dic: HeliosRow::string($row, 'dic'),
            bankAccount: $account === null ? null : $account . ($bankCode !== null ? '/' . $bankCode : ''),
            address: $this->address($row),
            priceLevelExternalIdentifier: HeliosRow::string($row, 'cenovauroven'),
            customerType: HeliosRow::string($row, '_typzakaznika'),
            freeShipping: HeliosRow::bool($row, '_SEMACO_NegenerovatDopravu'),
            salesRepresentativeCode: HeliosRow::string($row, 'odpos'),
            deliveryAddresses: $deliveryAddresses,
            modifiedAt: HeliosRow::dateTime($row, 'poslednizmena'),
        );
    }

    /** @param array<string, mixed> $row */
    private function address(array $row): AddressSnapshot
    {
        $street = HeliosRow::string($row, 'ulice');
        $number = implode('/', array_filter([HeliosRow::string($row, 'popcislo'), HeliosRow::string($row, 'orcislo')]));

        return new AddressSnapshot(
            externalIdentifier: HeliosRow::string($row, 'id') ?? '',
            company: HeliosRow::string($row, 'nazev'),
            firstName: HeliosRow::string($row, 'jmeno'),
            lastName: HeliosRow::string($row, 'prijmeni'),
            street: $street === null ? null : trim($street . ' ' . $number),
            city: HeliosRow::string($row, 'misto'),
            postcode: HeliosRow::string($row, 'psc'),
            countryCode: self::countryCode(HeliosRow::string($row, 'isokod')),
            phoneNumber: HeliosRow::string($row, 'telefon'),
        );
    }

    private static function countryCode(?string $heliosCode): ?string
    {
        $code = $heliosCode === null ? null : strtoupper($heliosCode);

        return match (true) {
            $code === 'SCH' => 'CH',
            $code !== null && preg_match('/^[A-Z]{2}$/', $code) === 1 => $code,
            default => null,
        };
    }

    /**
     * @param list<int> $parentIds
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private function loadDescendants(array $parentIds): array
    {
        $index = [];
        $seen = array_fill_keys($parentIds, true);
        while ($parentIds !== []) {
            $this->rateLimiter->consume();
            $rows = $this->runWithRetry(fn () => $this->connections->getConnection()->fetchAllAssociative(
                sprintf('SELECT %s FROM hvw_SapertaTabSapCisOrg WHERE idnadrazene IN (:ids) ORDER BY id ASC', self::COLUMNS),
                ['ids' => $parentIds],
                ['ids' => ArrayParameterType::INTEGER],
            ));

            $parentIds = [];
            foreach ($rows as $row) {
                $id = HeliosRow::int($row, 'id');
                $parent = HeliosRow::int($row, 'idnadrazene');
                if ($id === null || $parent === null || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $index[$parent][] = $row;
                $parentIds[] = $id;
            }
        }

        return $index;
    }
}
