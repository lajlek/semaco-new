<?php

declare(strict_types=1);

namespace App\Withdrawal\Controller;

use App\Entity\Customer\Customer;
use App\Inquiry\Security\SubmissionGuard;
use App\Withdrawal\Entity\WithdrawalRequest;
use App\Withdrawal\Form\WithdrawalRequestType;
use App\Withdrawal\Mailer\WithdrawalMailer;
use App\Withdrawal\Order\WithdrawalOrderMatcher;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/{_locale}/withdrawal', requirements: ['_locale' => '[a-z]{2}_[A-Z]{2}'])]
final class WithdrawalController extends AbstractController
{
    private const DRAFT_KEY = 'app_withdrawal_draft';

    private const CSRF_TOKEN_ID = 'withdrawal_confirm';

    public function __construct(
        private readonly CustomerContextInterface $customerContext,
        private readonly SubmissionGuard $guard,
        private readonly WithdrawalOrderMatcher $orderMatcher,
        private readonly WithdrawalMailer $mailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'app_shop_withdrawal', methods: ['GET', 'POST'])]
    public function form(Request $request): Response
    {
        $draft = $request->getSession()->get(self::DRAFT_KEY);
        $withdrawal = is_array($draft) ? WithdrawalRequest::fromDraft($draft) : $this->prefill();
        $orderNumber = $request->query->getString('order');
        if ($orderNumber !== '') {
            $withdrawal->setOrderNumber(mb_substr($orderNumber, 0, 64));
        }

        $form = $this->createForm(WithdrawalRequestType::class, $withdrawal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->guard->isHoneypotFilled($form)) {
                return $this->redirectToRoute('app_shop_withdrawal');
            }

            $this->guard->validate($request, $form);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $request->getSession()->set(self::DRAFT_KEY, $withdrawal->toDraft());

            return $this->redirectToRoute('app_shop_withdrawal_confirm');
        }

        return $this->render('shop/withdrawal/form.html.twig', [
            'form' => $form,
            'recaptcha_site_key' => $this->guard->getRecaptchaSiteKey(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/confirm', name: 'app_shop_withdrawal_confirm', methods: ['GET', 'POST'])]
    public function confirm(Request $request): Response
    {
        $draft = $request->getSession()->get(self::DRAFT_KEY);
        if (!is_array($draft)) {
            return $this->redirectToRoute('app_shop_withdrawal');
        }

        $withdrawal = WithdrawalRequest::fromDraft($draft);
        if (count($this->validator->validate($withdrawal)) > 0) {
            return $this->redirectToRoute('app_shop_withdrawal');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->request->getString('_token'))) {
                $this->addFlash('error', 'app.withdrawal.expired');

                return $this->redirectToRoute('app_shop_withdrawal_confirm');
            }

            $customer = $this->customerContext->getCustomer();
            $withdrawal->setCustomer($customer instanceof Customer ? $customer : null);
            $withdrawal->setOrder($this->orderMatcher->match($withdrawal->getOrderNumber(), $withdrawal->getEmail()));
            $withdrawal->markSubmitted(new \DateTimeImmutable(), $request->getClientIp(), $request->headers->get('User-Agent'));
            $this->entityManager->persist($withdrawal);
            $this->entityManager->flush();

            $request->getSession()->remove(self::DRAFT_KEY);
            $this->mailer->send($withdrawal);

            return $this->redirectToRoute('app_shop_withdrawal_done', ['token' => $withdrawal->getToken()]);
        }

        return $this->render('shop/withdrawal/confirm.html.twig', ['withdrawal' => $withdrawal]);
    }

    #[Route('/{token}', name: 'app_shop_withdrawal_done', requirements: ['token' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function done(string $token): Response
    {
        $withdrawal = $this->entityManager->getRepository(WithdrawalRequest::class)->findOneBy(['token' => $token]);
        if (!$withdrawal instanceof WithdrawalRequest) {
            throw $this->createNotFoundException();
        }

        $response = $this->render('shop/withdrawal/done.html.twig', ['withdrawal' => $withdrawal]);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    private function prefill(): WithdrawalRequest
    {
        $withdrawal = new WithdrawalRequest();
        $customer = $this->customerContext->getCustomer();
        if ($customer instanceof Customer) {
            $withdrawal->setFullName($customer->getFullName() !== '' ? $customer->getFullName() : null);
            $withdrawal->setEmail($customer->getEmail());
            $withdrawal->setPhone($customer->getPhoneNumber());
        }

        return $withdrawal;
    }
}
