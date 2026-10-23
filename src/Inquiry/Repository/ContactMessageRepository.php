<?php

declare(strict_types=1);

namespace App\Inquiry\Repository;

use App\Entity\Customer\Customer;
use App\Inquiry\Entity\ContactMessage;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

final class ContactMessageRepository extends EntityRepository implements ContactMessageRepositoryInterface
{
    public function findByCustomer(Customer $customer): array
    {
        /** @var list<ContactMessage> $messages */
        $messages = $this->findBy(['customer' => $customer], ['createdAt' => 'DESC']);

        return $messages;
    }

    public function findOneByIdAndCustomer(int $id, Customer $customer): ?ContactMessage
    {
        $message = $this->findOneBy(['id' => $id, 'customer' => $customer]);

        return $message instanceof ContactMessage ? $message : null;
    }
}
