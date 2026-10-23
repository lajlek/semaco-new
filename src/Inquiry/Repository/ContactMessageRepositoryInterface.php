<?php

declare(strict_types=1);

namespace App\Inquiry\Repository;

use App\Entity\Customer\Customer;
use App\Inquiry\Entity\ContactMessage;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

interface ContactMessageRepositoryInterface extends RepositoryInterface
{
    /** @return list<ContactMessage> */
    public function findByCustomer(Customer $customer): array;

    public function findOneByIdAndCustomer(int $id, Customer $customer): ?ContactMessage;
}
