<?php

declare(strict_types=1);

namespace App\Erp\Application\Customer;

use App\B2B\Entity\SalesRepresentative;
use App\Entity\Customer\Customer;
use App\Entity\Customer\CustomerGroup;
use App\Entity\User\ShopUser;
use App\Erp\Application\ImportResult;
use App\Erp\Port\Customer\Dto\SalesRepresentativeSnapshot;
use App\Erp\Port\Customer\SalesRepresentativeSyncInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Sylius\Component\User\Canonicalizer\CanonicalizerInterface;
use Sylius\Component\User\Security\Generator\GeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final readonly class SalesRepresentativeImporter
{
    public const ACCOUNT_EMAIL = 'app_sales_representative_account';

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private SalesRepresentativeSyncInterface $salesRepresentativeSync,
        private EntityManagerInterface $entityManager,
        private CanonicalizerInterface $canonicalizer,
        #[Autowire(service: 'sylius.shop_user.token_generator.password_reset')]
        private GeneratorInterface $passwordResetTokenGenerator,
        private SenderInterface $sender,
        #[Autowire(service: 'sylius.repository.channel')]
        private ChannelRepositoryInterface $channelRepository,
        private LoggerInterface $logger,
        #[Autowire('%app.b2b.sales_representative_group%')]
        private string $customerGroupCode,
        #[Autowire('%app.erp.channel_code%')]
        private string $channelCode,
        #[Autowire('%app.erp.locale_code%')]
        private string $localeCode,
    ) {
    }

    public function import(bool $sendAccountEmails = true): ImportResult
    {
        $result = new ImportResult('sales_representatives');
        $group = $this->entityManager->getRepository(CustomerGroup::class)->findOneBy(['code' => $this->customerGroupCode]);
        $newUsers = [];

        foreach ($this->salesRepresentativeSync->pullAll() as $snapshot) {
            $representative = $this->entityManager->getRepository(SalesRepresentative::class)->findOneBy(['externalIdentifier' => $snapshot->externalIdentifier]);
            if (!$representative instanceof SalesRepresentative) {
                $representative = new SalesRepresentative($snapshot->externalIdentifier, $snapshot->getName());
                $this->entityManager->persist($representative);
                $result->created();
            } else {
                $result->updated();
            }

            $representative->setName($snapshot->getName());
            $representative->setCode($snapshot->code);
            $representative->setEmail($snapshot->email);
            $representative->setPhoneNumber($snapshot->phoneNumber);

            if ($snapshot->email === null || filter_var($snapshot->email, \FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $user = $this->assignCustomerAccount($snapshot, $representative, $group instanceof CustomerGroupInterface ? $group : null);
            if ($user !== null) {
                $newUsers[] = $user;
                $result->warn(sprintf('Nový účet obchodníka %s', $snapshot->getName()));
            }
        }

        $this->entityManager->flush();

        if ($sendAccountEmails) {
            foreach ($newUsers as $user) {
                $this->sendAccountEmail($user);
            }
        }

        return $result;
    }

    private function assignCustomerAccount(SalesRepresentativeSnapshot $snapshot, SalesRepresentative $representative, ?CustomerGroupInterface $group): ?ShopUser
    {
        $email = (string) $snapshot->email;
        $customer = $this->entityManager->getRepository(Customer::class)->findOneBy(['emailCanonical' => $this->canonicalizer->canonicalize($email)]);
        if (!$customer instanceof Customer) {
            $customer = new Customer();
            $customer->setEmail($email);
            $customer->setEmailCanonical($this->canonicalizer->canonicalize($email));
            $customer->setFirstName($snapshot->firstName);
            $customer->setLastName($snapshot->lastName);
            $customer->setPhoneNumber($snapshot->phoneNumber);
            $this->entityManager->persist($customer);
        }

        $customer->setSalesRepresentative($representative);
        if ($group !== null) {
            $customer->setGroup($group);
        }

        if ($customer->getUser() !== null) {
            return null;
        }

        $user = new ShopUser();
        $user->setCustomer($customer);
        $user->setUsername($email);
        $user->setUsernameCanonical($this->canonicalizer->canonicalize($email));
        $user->setPlainPassword(bin2hex(random_bytes(24)));
        $user->setEnabled(true);
        $user->setPasswordResetToken($this->passwordResetTokenGenerator->generate());
        $user->setPasswordRequestedAt(new \DateTime());
        $this->entityManager->persist($user);

        return $user;
    }

    private function sendAccountEmail(ShopUser $user): void
    {
        $channel = $this->channelRepository->findOneByCode($this->channelCode);

        try {
            $this->sender->send(self::ACCOUNT_EMAIL, [(string) $user->getEmail()], [
                'user' => $user,
                'channel' => $channel,
                'localeCode' => $this->localeCode,
            ]);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Sales representative account e-mail failed: {message}', ['message' => $exception->getMessage()]);
        }
    }
}
