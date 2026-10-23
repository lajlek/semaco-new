<?php

declare(strict_types=1);

namespace App\Withdrawal\Form;

use App\Inquiry\Form\HoneypotType;
use App\Inquiry\Security\SubmissionGuard;
use App\Withdrawal\Entity\WithdrawalRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WithdrawalRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, ['label' => 'app.withdrawal.field.full_name', 'attr' => ['autocomplete' => 'name']])
            ->add('email', EmailType::class, ['label' => 'app.withdrawal.field.email', 'help' => 'app.withdrawal.field.email_help', 'attr' => ['autocomplete' => 'email']])
            ->add('phone', TelType::class, ['label' => 'app.withdrawal.field.phone', 'required' => false, 'attr' => ['autocomplete' => 'tel']])
            ->add('address', TextType::class, ['label' => 'app.withdrawal.field.address', 'required' => false, 'attr' => ['autocomplete' => 'street-address']])
            ->add('orderNumber', TextType::class, ['label' => 'app.withdrawal.field.order_number', 'help' => 'app.withdrawal.field.order_number_help'])
            ->add('receivedOn', TextType::class, ['label' => 'app.withdrawal.field.received_on', 'required' => false, 'help' => 'app.withdrawal.field.received_on_help'])
            ->add('items', TextareaType::class, ['label' => 'app.withdrawal.field.items', 'help' => 'app.withdrawal.field.items_help', 'attr' => ['rows' => 4]])
            ->add('bankAccount', TextType::class, ['label' => 'app.withdrawal.field.bank_account', 'required' => false, 'help' => 'app.withdrawal.field.bank_account_help'])
            ->add('note', TextareaType::class, ['label' => 'app.withdrawal.field.note', 'required' => false, 'attr' => ['rows' => 3]])
            ->add(SubmissionGuard::HONEYPOT_FIELD, HoneypotType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WithdrawalRequest::class,
            'translation_domain' => 'withdrawal',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'app_withdrawal';
    }
}
