<?php

declare(strict_types=1);

namespace App\Inquiry\Controller;

use App\Entity\Customer\Customer;
use App\Inquiry\Entity\ContactMessage;
use App\Inquiry\Form\ContactMessageType;
use App\Inquiry\Mailer\InquiryMailer;
use App\Inquiry\Security\SubmissionGuard;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ContactController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomerContextInterface $customerContext,
        private readonly SubmissionGuard $guard,
        private readonly InquiryMailer $mailer,
    ) {
    }

    public function request(Request $request): Response
    {
        $contactMessage = new ContactMessage();
        $customer = $this->customerContext->getCustomer();
        if ($customer instanceof Customer) {
            $contactMessage->setCustomer($customer);
            $contactMessage->setEmail($customer->getEmail());
            $contactMessage->setFirstName($customer->getFirstName());
            $contactMessage->setLastName($customer->getLastName());
            $contactMessage->setPhone($customer->getPhoneNumber());
        }

        $prefill = trim($request->query->getString('message'));
        if ($prefill !== '') {
            $contactMessage->setMessage(mb_substr($prefill, 0, 500));
        }

        $form = $this->createForm(ContactMessageType::class, $contactMessage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->guard->isHoneypotFilled($form)) {
                $this->addFlash('success', 'app.inquiry.contact.success');

                return $this->redirectToRoute('sylius_shop_contact_request');
            }

            $this->guard->validate($request, $form);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $contactMessage->setIp($request->getClientIp());
            $contactMessage->setUserAgent($request->headers->get('User-Agent'));
            $this->entityManager->persist($contactMessage);
            $this->entityManager->flush();

            $this->mailer->sendContactMessage($contactMessage);
            $this->addFlash('success', 'app.inquiry.contact.success');

            return $this->redirectToRoute('sylius_shop_contact_request');
        }

        return $this->render('shop/inquiry/contact.html.twig', [
            'form' => $form,
            'recaptcha_site_key' => $this->guard->getRecaptchaSiteKey(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
