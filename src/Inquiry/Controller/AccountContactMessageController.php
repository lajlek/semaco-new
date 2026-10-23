<?php

declare(strict_types=1);

namespace App\Inquiry\Controller;

use App\Entity\Customer\Customer;
use App\Inquiry\Entity\ContactMessage;
use App\Inquiry\Entity\ContactMessageAnswer;
use App\Inquiry\Form\ContactMessageAnswerType;
use App\Inquiry\Mailer\InquiryMailer;
use App\Inquiry\Repository\ContactMessageRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/account/messages', requirements: ['_locale' => '[a-z]{2}_[A-Z]{2}'])]
final class AccountContactMessageController extends AbstractController
{
    public function __construct(
        private readonly ContactMessageRepositoryInterface $contactMessageRepository,
        private readonly CustomerContextInterface $customerContext,
        private readonly EntityManagerInterface $entityManager,
        private readonly InquiryMailer $mailer,
    ) {
    }

    #[Route('', name: 'app_shop_account_contact_message_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('shop/inquiry/account/index.html.twig', [
            'messages' => $this->contactMessageRepository->findByCustomer($this->getCustomer()),
        ]);
    }

    #[Route('/{id}', name: 'app_shop_account_contact_message_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(Request $request, int $id): Response
    {
        $message = $this->contactMessageRepository->findOneByIdAndCustomer($id, $this->getCustomer());
        if (!$message instanceof ContactMessage) {
            throw $this->createNotFoundException();
        }

        $answer = new ContactMessageAnswer($message);
        $form = $this->createForm(ContactMessageAnswerType::class, $answer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $message->addAnswer($answer);
            $this->entityManager->flush();

            $this->mailer->sendCustomerReply($answer);
            $this->addFlash('success', 'app.inquiry.contact.reply_sent');

            return $this->redirectToRoute('app_shop_account_contact_message_show', ['id' => $id]);
        }

        return $this->render('shop/inquiry/account/show.html.twig', [
            'message' => $message,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function getCustomer(): Customer
    {
        $customer = $this->customerContext->getCustomer();
        if (!$customer instanceof Customer) {
            throw $this->createAccessDeniedException();
        }

        return $customer;
    }
}
