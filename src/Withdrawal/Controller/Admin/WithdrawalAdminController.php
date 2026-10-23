<?php

declare(strict_types=1);

namespace App\Withdrawal\Controller\Admin;

use App\Withdrawal\Entity\WithdrawalRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WithdrawalAdminController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/admin/withdrawal-requests/{id}/handle', name: 'app_admin_withdrawal_request_handle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function handle(Request $request, int $id): Response
    {
        $withdrawal = $this->entityManager->find(WithdrawalRequest::class, $id);
        if (!$withdrawal instanceof WithdrawalRequest) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('withdrawal_handle_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$withdrawal->isHandled()) {
            $withdrawal->markHandled(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', 'app.withdrawal.handled');
        }

        return $this->redirectToRoute('app_admin_withdrawal_request_show', ['id' => $id]);
    }
}
