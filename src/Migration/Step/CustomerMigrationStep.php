<?php

declare(strict_types=1);

namespace App\Migration\Step;

use App\B2B\Entity\Organization;
use App\B2B\Entity\SalesRepresentative;
use App\Entity\Addressing\Address;
use App\Entity\Customer\Customer;
use App\Entity\User\ShopUser;
use App\Erp\Application\Customer\OrganizationImporter;
use App\Erp\Application\ImportResult;
use App\Migration\OldShop\OldShopConnectionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsTaggedItem(priority: 80)]
final readonly class CustomerMigrationStep implements MigrationStepInterface
{
    private const ADDRESS_ID_PREFIX = 'old-address:';

    /**
     * @param RepositoryInterface<Customer> $customerRepository
     * @param FactoryInterface<Customer> $customerFactory
     * @param FactoryInterface<ShopUser> $shopUserFactory
     * @param RepositoryInterface<CustomerGroupInterface> $customerGroupRepository
     */
    public function __construct(
        private OldShopConnectionFactory $oldShop,
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'sylius.repository.customer')]
        private RepositoryInterface $customerRepository,
        #[Autowire(service: 'sylius.factory.customer')]
        private FactoryInterface $customerFactory,
        #[Autowire(service: 'sylius.factory.shop_user')]
        private FactoryInterface $shopUserFactory,
        #[Autowire(service: 'sylius.repository.customer_group')]
        private RepositoryInterface $customerGroupRepository,
        private OrganizationImporter $organizationImporter,
    ) {
    }

    public function name(): string
    {
        return 'customers';
    }

    public function run(bool $dryRun): ImportResult
    {
        $result = new ImportResult('customers');
        $old = $this->oldShop->getConnection();

        $customers = self::stringify($old->fetchAllAssociative(
            'SELECT c.*, g.code AS group_code, o.externalIdentifier AS organization_ext, r.externalIdentifier AS representative_ext,
                    u.id AS user_id, u.username, u.enabled AS user_enabled, u.password, u.verified_at, u.last_login, u.created_at AS user_created_at
             FROM sylius_customer c
             LEFT JOIN sylius_customer_group g ON g.id = c.customer_group_id
             LEFT JOIN mergocz_helios_organization o ON o.id = c.organization_id
             LEFT JOIN mergocz_sylius_sales_representative r ON r.id = c.sales_representative
             LEFT JOIN sylius_shop_user u ON u.customer_id = c.id
             ORDER BY c.id ASC',
        ));

        $this->importMissingOrganizations($customers);

        /** @var array<string, list<array<string, string|null>>> $addressesByCustomer */
        $addressesByCustomer = [];
        foreach (self::stringify($old->fetchAllAssociative('SELECT * FROM sylius_address WHERE customer_id IS NOT NULL ORDER BY id ASC')) as $address) {
            $addressesByCustomer[(string) $address['customer_id']][] = $address;
        }

        foreach ($customers as $row) {
            $email = trim((string) $row['email']);
            if ($email === '') {
                $result->skipped();

                continue;
            }

            $customer = $this->customerRepository->findOneBy(['emailCanonical' => mb_strtolower($email)]);
            if (!$customer instanceof Customer) {
                $customer = $this->customerFactory->createNew();
                $this->entityManager->persist($customer);
                $result->created();
            } else {
                $result->updated();
            }

            $this->applyCustomer($customer, $row, $result);
            $this->applyAddresses($customer, $addressesByCustomer[(string) $row['id']] ?? [], $row['default_address_id']);

            if ($row['user_id'] !== null && $row['password'] !== null) {
                $this->applyShopUser($customer, $row);
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $result;
    }

    /** @param array<string, string|null> $row */
    private function applyCustomer(Customer $customer, array $row, ImportResult $result): void
    {
        $customer->setEmail(trim((string) $row['email']));
        $customer->setEmailCanonical(mb_strtolower(trim((string) $row['email'])));
        $customer->setFirstName($row['first_name']);
        $customer->setLastName($row['last_name']);
        $customer->setPhoneNumber($row['phone_number']);
        $customer->setGender($row['gender'] ?? 'u');
        $customer->setBirthday($row['birthday'] !== null ? new \DateTimeImmutable($row['birthday']) : null);
        $customer->setSubscribedToNewsletter((bool) $row['subscribed_to_newsletter']);
        $customer->setCreatedAt(new \DateTimeImmutable((string) $row['created_at']));
        $customer->setCompanyName($row['companyName']);
        $customer->setIdentificationNumber($row['identificationNumber']);
        $customer->setVatNumber($row['vatNumber']);
        $customer->setInvoiceEmail($row['invoiceEmail']);
        $customer->setVatPayer((bool) $row['isVatPayer']);
        $customer->setGdprAgreed((bool) $row['gdprAgree']);
        $customer->setExternalIdentifier($row['externalIdentifier']);

        $group = $row['group_code'] === null ? null : $this->customerGroupRepository->findOneBy(['code' => $row['group_code']]);
        $customer->setGroup($group instanceof CustomerGroupInterface ? $group : null);

        $organization = null;
        if ($row['organization_ext'] !== null) {
            $organization = $this->entityManager->getRepository(Organization::class)->findOneBy(['externalIdentifier' => $row['organization_ext']]);
            if (!$organization instanceof Organization) {
                $result->warn(sprintf('Zákazník %s: firma s Helios id %s v e-shopu není', (string) $row['email'], $row['organization_ext']));
            }
        }
        $customer->setOrganization($organization instanceof Organization ? $organization : null);

        $representative = null;
        if ($row['representative_ext'] !== null) {
            $representative = $this->entityManager->getRepository(SalesRepresentative::class)->findOneBy(['externalIdentifier' => $row['representative_ext']]);
        }
        $customer->setSalesRepresentative($representative instanceof SalesRepresentative ? $representative : null);
    }

    /**
     * @param list<array<string, string|null>> $rows
     */
    private function applyAddresses(Customer $customer, array $rows, ?string $defaultAddressId): void
    {
        $repository = $this->entityManager->getRepository(Address::class);

        foreach ($rows as $row) {
            if ($row['country_code'] === null || $row['postcode'] === null) {
                continue;
            }

            $externalIdentifier = self::ADDRESS_ID_PREFIX . $row['id'];
            $address = $repository->findOneBy(['externalIdentifier' => $externalIdentifier]);
            if (!$address instanceof Address) {
                $address = new Address();
                $address->setExternalIdentifier($externalIdentifier);
                $this->entityManager->persist($address);
            }

            $address->setFirstName((string) $row['first_name']);
            $address->setLastName((string) $row['last_name']);
            $address->setPhoneNumber($row['phone_number']);
            $address->setCompany($row['company']);
            $address->setStreet((string) $row['street']);
            $address->setCity((string) $row['city']);
            $address->setPostcode($row['postcode']);
            $address->setCountryCode($row['country_code']);
            $address->setProvinceCode($row['province_code']);
            $address->setProvinceName($row['province_name']);
            $address->setIdentificationNumber($row['identificationNumber']);
            $address->setVatNumber($row['vatNumber']);

            if (!$customer->hasAddress($address)) {
                $customer->addAddress($address);
            }

            if ($defaultAddressId !== null && $defaultAddressId === $row['id']) {
                $customer->setDefaultAddress($address);
            }
        }
    }

    /** @param array<string, string|null> $row */
    private function applyShopUser(Customer $customer, array $row): void
    {
        $user = $customer->getUser();
        if (!$user instanceof ShopUser) {
            $user = $this->shopUserFactory->createNew();
            $customer->setUser($user);
        }

        $username = (string) ($row['username'] ?? $row['email']);
        $user->setUsername($username);
        $user->setUsernameCanonical(mb_strtolower($username));
        $user->setPassword($row['password']);
        $user->setEnabled((bool) $row['user_enabled']);
        $user->setVerifiedAt($row['verified_at'] !== null ? new \DateTimeImmutable($row['verified_at']) : null);
        $user->setLastLogin($row['last_login'] !== null ? new \DateTimeImmutable($row['last_login']) : null);
        if ($row['user_created_at'] !== null) {
            $user->setCreatedAt(new \DateTimeImmutable($row['user_created_at']));
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, string|null>>
     */
    private static function stringify(array $rows): array
    {
        return array_map(
            static fn (array $row): array => array_map(static fn (mixed $value): ?string => is_scalar($value) ? (string) $value : null, $row),
            $rows,
        );
    }

    /** @param list<array<string, string|null>> $customers */
    private function importMissingOrganizations(array $customers): void
    {
        $repository = $this->entityManager->getRepository(Organization::class);
        $missing = [];
        foreach ($customers as $row) {
            $externalIdentifier = $row['organization_ext'];
            if ($externalIdentifier !== null && $repository->findOneBy(['externalIdentifier' => $externalIdentifier]) === null) {
                $missing[] = $externalIdentifier;
            }
        }

        if ($missing !== []) {
            $this->organizationImporter->importByIds(array_values(array_unique($missing)));
        }
    }
}
