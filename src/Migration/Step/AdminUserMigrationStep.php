<?php

declare(strict_types=1);

namespace App\Migration\Step;

use App\Entity\User\AdminUser;
use App\Erp\Application\ImportResult;
use App\Migration\OldShop\OldShopConnectionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 85)]
final readonly class AdminUserMigrationStep implements MigrationStepInterface
{
    private const ADMIN_ROLE = 'ROLE_ADMINISTRATION_ACCESS';

    public function __construct(
        private OldShopConnectionFactory $oldShop,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function name(): string
    {
        return 'admin-users';
    }

    public function run(bool $dryRun): ImportResult
    {
        $result = new ImportResult('admin-users');

        /** @var list<array<string, string|int|null>> $rows */
        $rows = $this->oldShop->getConnection()->fetchAllAssociative(
            'SELECT username, email, password, enabled, first_name, last_name, locale_code, last_login, created_at
             FROM sylius_admin_user ORDER BY id ASC',
        );

        foreach ($rows as $row) {
            $email = mb_strtolower(trim((string) $row['email']));
            $username = trim((string) ($row['username'] ?? $email));
            if ($email === '' || $row['password'] === null) {
                $result->skipped();

                continue;
            }

            $repository = $this->entityManager->getRepository(AdminUser::class);
            $user = $repository->findOneBy(['emailCanonical' => $email]) ?? $repository->findOneBy(['usernameCanonical' => mb_strtolower($username)]);
            if ($user instanceof AdminUser) {
                $result->warn(sprintf('Administrátor %s už existuje, ponechán beze změny', $username));
                $result->skipped();

                continue;
            }

            $user = new AdminUser();
            $user->setUsername($username);
            $user->setUsernameCanonical(mb_strtolower($username));
            $user->setEmail($email);
            $user->setEmailCanonical($email);
            $user->setPassword((string) $row['password']);
            $user->setEnabled((bool) $row['enabled']);
            $user->setFirstName($row['first_name'] === null ? null : (string) $row['first_name']);
            $user->setLastName($row['last_name'] === null ? null : (string) $row['last_name']);
            $user->setLocaleCode((string) ($row['locale_code'] ?? 'cs_CZ'));
            $user->addRole(self::ADMIN_ROLE);
            if ($row['last_login'] !== null) {
                $user->setLastLogin(new \DateTimeImmutable((string) $row['last_login']));
            }
            $this->entityManager->persist($user);
            $result->created();
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $result;
    }
}
