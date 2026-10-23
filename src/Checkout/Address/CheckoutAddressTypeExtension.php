<?php

declare(strict_types=1);

namespace App\Checkout\Address;

use Sylius\Bundle\ShopBundle\Form\Type\Checkout\AddressType;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CheckoutAddressTypeExtension extends AbstractTypeExtension
{
    public function __construct(private readonly BillingAddressPrefiller $billingAddressPrefiller)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [AddressType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            foreach (['billingAddress', 'shippingAddress'] as $child) {
                if ($form->has($child)) {
                    $form->get($child)->add('phoneNumber', TelType::class, [
                        'required' => true,
                        'label' => 'sylius.form.address.phone_number',
                        'constraints' => [new NotBlank(message: 'sylius.address.phone_number.not_blank', groups: ['sylius'])],
                    ]);
                }
            }

            $order = $event->getData();
            if ($order instanceof OrderInterface) {
                $this->billingAddressPrefiller->prefill($order);
            }
        });
    }
}
