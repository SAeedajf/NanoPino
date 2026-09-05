<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Http;

final readonly class RequestIntegrityPolicy
{
    public function __construct(private CsrfTokenVerifierInterface $csrf) {}

    public function decide(RequestSecurityContext $context): RequestIntegrityDecision
    {
        $method = $context->method();
        if (!in_array($method, ['GET','HEAD','OPTIONS','POST','PUT','PATCH','DELETE'], true)) {
            return new RequestIntegrityDecision(false, 'METHOD_NOT_ALLOWED');
        }

        if (in_array($method, ['GET','HEAD','OPTIONS'], true)) {
            return new RequestIntegrityDecision(true, 'SAFE_METHOD');
        }

        if ($context->auth === AuthMechanism::Anonymous) {
            return new RequestIntegrityDecision(false, 'AUTH_REQUIRED');
        }

        if ($context->auth === AuthMechanism::Session) {
            if (!$context->sameOrigin) return new RequestIntegrityDecision(false, 'ORIGIN_MISMATCH');
            if (!$this->csrf->verify($context->csrfToken, $context->subjectId)) {
                return new RequestIntegrityDecision(false, 'CSRF_INVALID');
            }
        }

        if ($context->contentType !== null) {
            $type = strtolower(trim(explode(';', $context->contentType, 2)[0]));
            if (!in_array($type, [
                'application/json',
                'application/x-www-form-urlencoded',
                'multipart/form-data',
            ], true)) {
                return new RequestIntegrityDecision(false, 'CONTENT_TYPE_NOT_ALLOWED');
            }
        }

        return new RequestIntegrityDecision(true, 'ALLOWED');
    }

    public function authorize(RequestSecurityContext $context): void
    {
        $decision = $this->decide($context);
        if (!$decision->allowed) {
            throw new \RuntimeException('Request integrity denied: ' . $decision->reason);
        }
    }
}
