<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\testing;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\BaseView;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\core\ViewContext;
use actra\yuf\exception\UnauthorizedException;
use actra\yuf\security\CsrfTokenSource;
use Override;

/**
 * Two-step form of the tests of `ViewTestClient`: `step1.html` takes an email address (CSRF checked), keeps it in the
 * session, records a "mail" after the response and redirects to `step2.html`, which shows the address.
 */
final class TwoStepTestView extends BaseView
{
    /** @var list<string> */
    public static array $sentMails = [];

    public function __construct(ViewContext $context)
    {
        parent::__construct(
            context: $context,
            requiredViewGroupName: 'frontend',
            ipWhitelist: [],
            authUser: null,
            requiredAccessRights: AccessRightCollection::createEmpty(),
            inputParameterCollection: new InputParameterCollection(),
        );
    }

    #[Override]
    public function execute(): void
    {
        $context = $this->context;
        if ($context->fileTitle === 'step1' && $context->httpRequest->getMethod() === RequestMethodEnum::POST) {
            $token = $context->httpRequest->getPostString(name: CsrfTokenSource::FIELD_NAME) ?? '';
            if ($context->formContext->csrfTokenSource?->isValid(token: $token) !== true) {
                throw new UnauthorizedException(message: 'Invalid CSRF token');
            }
            $email = $context->httpRequest->getPostString(name: 'email') ?? '';
            $context->session?->set(key: 'email', value: $email);
            $context->responseSender->afterResponse(callback: static function () use ($email): void {
                TwoStepTestView::$sentMails[] = $email;
            });
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: '/step2.html',
                httpRequest: $context->httpRequest,
                responseSender: $context->responseSender,
            );
        }
        $this->context->getHtmlDocument()->replacements->addText(
            identifier: 'email',
            text: $context->session?->getString(key: 'email') ?? '',
        );
    }
}
