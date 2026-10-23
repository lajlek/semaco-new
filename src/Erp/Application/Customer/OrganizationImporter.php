<?php

declare(strict_types=1);

namespace App\Erp\Application\Customer;

use App\B2B\Entity\Organization;
use App\B2B\Repository\OrganizationRepositoryInterface;
use App\B2B\Repository\PriceLevelRepositoryInterface;
use App\Entity\Addressing\Address;
use App\Erp\Application\ImportResult;
use App\Erp\Port\Customer\Dto\AddressSnapshot;
use App\Erp\Port\Customer\Dto\OrganizationSnapshot;
use App\Erp\Port\Customer\OrganizationSyncInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrganizationImporter
{
    private const FLUSH_EVERY = 100;

    public function __construct(
        private OrganizationSyncInterface $organizationSync,
        private OrganizationRepositoryInterface $organizationRepository,
        private PriceLevelRepositoryInterface $priceLevelRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function importChangedSince(\DateTimeInterface $since, ?\DateTimeImmutable &$maxModifiedAt = null): ImportResult
    {
        $result = new ImportResult('organizations');
        $processed = 0;

        foreach ($this->organizationSync->pullChangedSince($since) as $snapshot) {
            if ($maxModifiedAt === null || $snapshot->modifiedAt > $maxModifiedAt) {
                $maxModifiedAt = $snapshot->modifiedAt;
            }

            $organization = $this->organizationRepository->findOneByExternalIdentifier($snapshot->externalIdentifier);
            if ($organization === null) {
                $organization = new Organization($snapshot->externalIdentifier, $snapshot->name);
                $this->entityManager->persist($organization);
                $result->created();
            } else {
                $result->updated();
            }

            $this->apply($organization, $snapshot, $result);

            if (++$processed % self::FLUSH_EVERY === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        return $result;
    }

    private function apply(Organization $organization, OrganizationSnapshot $snapshot, ImportResult $result): void
    {
        $organization->setName($snapshot->name);
        $organization->setOrgNumber($snapshot->orgNumber);
        $organization->setIco($snapshot->ico);
        $organization->setDic($snapshot->dic);
        $organization->setBankAccount($snapshot->bankAccount);
        $organization->setAddress(
            $snapshot->address->street,
            null,
            null,
            $snapshot->address->city,
            $snapshot->address->postcode,
            $snapshot->address->countryCode,
        );
        $organization->setCustomerType($snapshot->customerType);
        $organization->setFreeShipping($snapshot->freeShipping);
        $organization->setSalesRepresentativeCode($snapshot->salesRepresentativeCode);

        if ($snapshot->priceLevelExternalIdentifier !== null) {
            $priceLevel = $this->priceLevelRepository->findOneByExternalIdentifier($snapshot->priceLevelExternalIdentifier);
            if ($priceLevel === null) {
                $result->warn(sprintf('Firma %s: neznámá cenová úroveň %s', $snapshot->externalIdentifier, $snapshot->priceLevelExternalIdentifier));
            }
            $organization->setPriceLevel($priceLevel);
        } else {
            $organization->setPriceLevel(null);
        }

        $defaultAddress = $this->upsertAddress($organization, $snapshot->address);
        if ($defaultAddress !== null) {
            $organization->setDefaultAddress($defaultAddress);
        }

        foreach ($snapshot->deliveryAddresses as $addressSnapshot) {
            $this->upsertAddress($organization, $addressSnapshot);
        }
    }

    private function upsertAddress(Organization $organization, AddressSnapshot $snapshot): ?Address
    {
        if ($snapshot->postcode === null || $snapshot->countryCode === null) {
            return null;
        }

        $address = $this->entityManager->getRepository(Address::class)->findOneBy(['externalIdentifier' => $snapshot->externalIdentifier]);
        if (!$address instanceof Address) {
            $address = new Address();
            $address->setExternalIdentifier($snapshot->externalIdentifier);
            $this->entityManager->persist($address);
        }

        $address->setCompany($snapshot->company);
        $address->setFirstName($snapshot->firstName ?? '');
        $address->setLastName($snapshot->lastName ?? '');
        $address->setStreet($snapshot->street ?? '');
        $address->setCity($snapshot->city ?? '');
        $address->setPostcode($snapshot->postcode);
        $address->setCountryCode($snapshot->countryCode);
        $address->setPhoneNumber($snapshot->phoneNumber);
        $organization->addAddress($address);

        return $address;
    }
}
