<?php

declare(strict_types=1);

namespace App\Inquiry\Security;

use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SubmissionGuard
{
    public const HONEYPOT_FIELD = 'website';

    public function __construct(
        private RateLimiterFactory $limiterFactory,
        private RecaptchaVerifier $recaptcha,
        private TranslatorInterface $translator,
    ) {
    }

    public function isHoneypotFilled(FormInterface $form): bool
    {
        if (!$form->has(self::HONEYPOT_FIELD)) {
            return false;
        }

        $value = $form->get(self::HONEYPOT_FIELD)->getData();

        return is_string($value) && trim($value) !== '';
    }

    public function validate(Request $request, FormInterface $form): void
    {
        if (!$this->recaptcha->verify($request->request->getString('g-recaptcha-response'), $request->getClientIp())) {
            $form->addError(new FormError($this->translator->trans('app.inquiry.error.recaptcha', [], 'inquiry')));

            return;
        }

        $limiter = $this->limiterFactory->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume()->isAccepted()) {
            $form->addError(new FormError($this->translator->trans('app.inquiry.error.too_many', [], 'inquiry')));
        }
    }

    public function getRecaptchaSiteKey(): ?string
    {
        return $this->recaptcha->getSiteKey();
    }
}
