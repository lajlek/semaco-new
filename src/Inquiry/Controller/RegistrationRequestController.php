<?php

declare(strict_types=1);

namespace App\Inquiry\Controller;

use App\Entity\Customer\Customer;
use App\Inquiry\Entity\RegistrationRequest;
use App\Inquiry\Form\RegistrationRequestType;
use App\Inquiry\Mailer\InquiryMailer;
use App\Inquiry\Security\SubmissionGuard;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationRequestController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomerContextInterface $customerContext,
        private readonly SubmissionGuard $guard,
        private readonly InquiryMailer $mailer,
    ) {
    }

    #[Route('/{_locale}/registration-request', name: 'app_shop_registration_request', requirements: ['_locale' => '[a-z]{2}_[A-Z]{2}'], methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        $registrationRequest = new RegistrationRequest();
        $customer = $this->customerContext->getCustomer();
        if ($customer instanceof Customer) {
            $registrationRequest->setCustomer($customer);
            $registrationRequest->setEmail($customer->getEmail());
            $registrationRequest->setFirstName($customer->getFirstName());
            $registrationRequest->setLastName($customer->getLastName());
            $registrationRequest->setPhone($customer->getPhoneNumber());
        }

        $form = $this->createForm(RegistrationRequestType::class, $registrationRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->guard->isHoneypotFilled($form)) {
                $this->addFlash('success', 'app.inquiry.registration_request.success');

                return $this->redirectToRoute('app_shop_registration_request');
            }

            $this->guard->validate($request, $form);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $registrationRequest->setIp($request->getClientIp());
            $registrationRequest->setUserAgent($request->headers->get('User-Agent'));
            $this->entityManager->persist($registrationRequest);
            $this->entityManager->flush();

            $this->mailer->sendRegistrationRequest($registrationRequest);
            $this->addFlash('success', 'app.inquiry.registration_request.success');

            return $this->redirectToRoute('app_shop_registration_request');
        }

        return $this->render('shop/inquiry/registration_request.html.twig', [
            'form' => $form,
            'recaptcha_site_key' => $this->guard->getRecaptchaSiteKey(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
