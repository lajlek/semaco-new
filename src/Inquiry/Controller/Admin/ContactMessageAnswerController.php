<?php

declare(strict_types=1);

namespace App\Inquiry\Controller\Admin;

use App\Entity\User\AdminUser;
use App\Inquiry\Entity\ContactMessage;
use App\Inquiry\Entity\ContactMessageAnswer;
use App\Inquiry\Form\ContactMessageAnswerType;
use App\Inquiry\Mailer\InquiryMailer;
use App\Inquiry\Repository\ContactMessageRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactMessageAnswerController extends AbstractController
{
    public function __construct(
        private readonly ContactMessageRepositoryInterface $contactMessageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly InquiryMailer $mailer,
    ) {
    }

    public function form(int $id): Response
    {
        $message = $this->findMessage($id);

        return $this->render('admin/inquiry/contact_message/answer_form.html.twig', [
            'form' => $this->createAnswerForm($message, new ContactMessageAnswer($message)),
        ]);
    }

    #[Route('/admin/contact-messages/{id}/answer', name: 'app_admin_contact_message_answer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function answer(Request $request, int $id): Response
    {
        $message = $this->findMessage($id);
        $answer = new ContactMessageAnswer($message);
        $form = $this->createAnswerForm($message, $answer);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'app.inquiry.contact.answer_invalid');

            return $this->redirectToRoute('app_admin_contact_message_show', ['id' => $id]);
        }

        $adminUser = $this->getUser();
        $answer->setAdminUser($adminUser instanceof AdminUser ? $adminUser : null);
        $answer->setFromAdmin(true);
        $message->addAnswer($answer);
        $this->entityManager->flush();

        $this->mailer->sendAdminAnswer($answer);
        $this->addFlash('success', 'app.inquiry.contact.answer_sent');

        return $this->redirectToRoute('app_admin_contact_message_show', ['id' => $id]);
    }

    private function findMessage(int $id): ContactMessage
    {
        $message = $this->contactMessageRepository->find($id);
        if (!$message instanceof ContactMessage) {
            throw $this->createNotFoundException();
        }

        return $message;
    }

    private function createAnswerForm(ContactMessage $message, ContactMessageAnswer $answer): FormInterface
    {
        return $this->createForm(ContactMessageAnswerType::class, $answer, [
            'action' => $this->generateUrl('app_admin_contact_message_answer', ['id' => $message->getId()]),
        ]);
    }
}
