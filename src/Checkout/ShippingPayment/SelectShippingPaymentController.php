<?php

declare(strict_types=1);

namespace App\Checkout\ShippingPayment;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SelectShippingPaymentController extends AbstractController
{
    public function __construct(
        private readonly CartContextInterface $cartContext,
        private readonly StateMachineInterface $stateMachine,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/{_locale}/checkout/select-shipping-payment', name: 'app_shop_checkout_select_shipping_payment', requirements: ['_locale' => '[a-z]{2}_[A-Z]{2}'], methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $order = $this->cartContext->getCart();
        if (!$order instanceof OrderInterface || $order->isEmpty()) {
            return $this->redirectToRoute('sylius_shop_cart_summary');
        }

        if ($order->getCheckoutState() === OrderCheckoutStates::STATE_CART) {
            return $this->redirectToRoute('sylius_shop_checkout_address');
        }

        $form = $this->createForm(SelectShippingPaymentType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ([OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT] as $transition) {
                if ($this->stateMachine->can($order, OrderCheckoutTransitions::GRAPH, $transition)) {
                    $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, $transition);
                }
            }
            $this->entityManager->flush();

            return $this->redirectToRoute('sylius_shop_checkout_complete');
        }

        return $this->render('shop/checkout/select_shipping_payment.html.twig', [
            'form' => $form,
            'order' => $order,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
