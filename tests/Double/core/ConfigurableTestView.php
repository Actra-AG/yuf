<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\core;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\AuthUser;
use actra\yuf\core\BaseView;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\core\ViewContext;
use actra\yuf\html\HtmlDocument;
use actra\yuf\request\JsonRequestBody;
use Override;
use stdClass;

/**
 * A view with all constructor arguments of BaseView that tests vary, and public access to its protected methods.
 */
final class ConfigurableTestView extends BaseView
{
    /**
     * @param array<string> $ipWhitelist
     */
    public function __construct(
        ViewContext $context,
        string $requiredViewGroupName = 'frontend',
        array $ipWhitelist = [],
        ?AuthUser $authUser = null,
        ?AccessRightCollection $requiredAccessRights = null,
        ?InputParameterCollection $inputParameterCollection = null,
        int $maxAllowedPathVars = 0,
    ) {
        parent::__construct(
            context: $context,
            requiredViewGroupName: $requiredViewGroupName,
            ipWhitelist: $ipWhitelist,
            authUser: $authUser,
            requiredAccessRights: $requiredAccessRights ?? AccessRightCollection::createEmpty(),
            inputParameterCollection: $inputParameterCollection ?? new InputParameterCollection(),
            maxAllowedPathVars: $maxAllowedPathVars,
        );
    }

    public function callGetContext(): ViewContext
    {
        return $this->context;
    }

    public function callGetPathVar(int $nr): ?string
    {
        return $this->getPathVar(nr: $nr);
    }

    public function callGetPathVarAsInt(int $nr): ?int
    {
        return $this->getPathVarAsInt(nr: $nr);
    }

    public function callGetRequiredPathVarAsInt(int $nr): int
    {
        return $this->getRequiredPathVarAsInt(nr: $nr);
    }

    public function callGetRequiredPathVarAsString(int $nr): string
    {
        return $this->getRequiredPathVarAsString(nr: $nr);
    }

    public function callSetContent(string $contentString): void
    {
        $this->setContent(contentString: $contentString);
    }

    public function callSetContentType(ContentType $contentType): void
    {
        $this->setContentType(contentType: $contentType);
    }

    public function callSetErrorResponseContent(
        string $errorMessage,
        HttpStatusCodeEnum $httpStatusCode = HttpStatusCodeEnum::HTTP_BAD_REQUEST,
        int|string|null $errorCode = null,
        ?stdClass $data = null,
        bool $sendAndExit = false,
    ): void {
        $this->setErrorResponseContent(
            errorMessage: $errorMessage,
            httpStatusCode: $httpStatusCode,
            errorCode: $errorCode,
            data: $data,
            sendAndExit: $sendAndExit,
        );
    }

    public function callSetSuccessResponseContent(stdClass $data = new stdClass(), bool $sendAndExit = false): void
    {
        $this->setSuccessResponseContent(data: $data, sendAndExit: $sendAndExit);
    }

    public function callRespondNotModifiedIfUnchanged(string $dataVersion): void
    {
        $this->respondNotModifiedIfUnchanged(dataVersion: $dataVersion);
    }

    public function callGetHtmlDocument(): HtmlDocument
    {
        return $this->getHtmlDocument();
    }

    public function callGetJsonRequestBody(): JsonRequestBody
    {
        return $this->getJsonRequestBody();
    }

    #[Override]
    public function execute(): void {}
}
