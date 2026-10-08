<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\AuthUser;
use actra\yuf\auth\UnauthorizedAccessRightException;
use actra\yuf\auth\UnauthorizedIpAddressException;
use actra\yuf\common\JsonUtils;
use actra\yuf\common\SimpleXmlExtended;
use actra\yuf\datacheck\Sanitizer;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\request\JsonRequestBody;
use actra\yuf\response\HttpErrorResponseContent;
use actra\yuf\response\HttpSuccessResponseContent;
use LogicException;
use stdClass;
use Throwable;

/**
 * Extension point: every view of a project extends it (abstract; the constructor is protected, a view passes its
 * view group, IP whitelist, access rights and input parameters).
 */
abstract class BaseView
{
    /**
     * @param array<string> $ipWhitelist IP addresses and ranges the view may be called from (see
     *     `IpValidator::isInWhitelist()`), empty for all
     */
    protected function __construct(
        protected readonly ViewContext $context,
        string $requiredViewGroupName,
        array $ipWhitelist,
        ?AuthUser $authUser,
        AccessRightCollection $requiredAccessRights,
        private readonly InputParameterCollection $inputParameterCollection,
        public readonly int $maxAllowedPathVars = 0,
    ) {
        $viewGroup = $context->route->viewGroup;
        if ($viewGroup !== $requiredViewGroupName) {
            throw new LogicException(
                message: 'View group needs to be ' . $requiredViewGroupName . ' instead of ' . $viewGroup,
            );
        }
        $ipAddress = $context->httpRequest->getRemoteAddress();
        if (
            count(value: $ipWhitelist) > 0
            && !IpValidator::isInWhitelist(
                whiteList: $ipWhitelist,
                ipAddressToCheck: $ipAddress,
            )
        ) {
            throw new UnauthorizedIpAddressException(message: 'Invalid IP address ' . $ipAddress);
        }
        if (
            !$requiredAccessRights->isEmpty()
            && (
                $authUser === null
                || !$authUser->hasOneOfRights(accessRightCollection: $requiredAccessRights)
            )
        ) {
            throw new UnauthorizedAccessRightException();
        }
        foreach ($inputParameterCollection->listRequiredParameters() as $inputParameter) {
            if ($this->isInputMissing(inputParameter: $inputParameter)) {
                if ($context->content->getContentType()->isHtml()) {
                    throw new NotFoundException();
                }
                $this->setErrorResponseContent(
                    errorMessage: 'missing or empty mandatory parameter: ' . $inputParameter->name,
                );

                return;
            }
        }
    }

    /**
     * Whether the value of the parameter is missing in its source, an empty text or an empty array.
     */
    private function isInputMissing(InputParameter $inputParameter): bool
    {
        $text = $this->readInputString(inputParameter: $inputParameter);
        if ($text !== null) {
            return $text === '';
        }
        $array = $this->readInputArray(inputParameter: $inputParameter);

        return $array === null || $array === [];
    }

    private function readInputString(InputParameter $inputParameter): ?string
    {
        $httpRequest = $this->context->httpRequest;

        return match ($inputParameter->source) {
            InputSourceEnum::QUERY => $httpRequest->getQueryString(name: $inputParameter->name),
            InputSourceEnum::POST => $httpRequest->getPostString(name: $inputParameter->name),
        };
    }

    /**
     * @return ?array<array-key, mixed>
     */
    private function readInputArray(InputParameter $inputParameter): ?array
    {
        $httpRequest = $this->context->httpRequest;

        return match ($inputParameter->source) {
            InputSourceEnum::QUERY => $httpRequest->getQueryArray(name: $inputParameter->name),
            InputSourceEnum::POST => $httpRequest->getPostArray(name: $inputParameter->name),
        };
    }

    protected function setContent(string $contentString): void
    {
        $this->context->content->setContent(contentString: $contentString);
    }

    abstract public function execute(): void;

    public function getInputDomain(string $keyName): ?string
    {
        $value = $this->getInputString(keyName: $keyName);
        if ($value === null) {
            return null;
        }

        return Sanitizer::domain(input: $value);
    }

    /**
     * @throws LogicException if the view does not declare the input parameter
     */
    private function getDefinedInputParameter(string $parameterName): InputParameter
    {
        return $this->inputParameterCollection->findParameter(name: $parameterName)
            ?? throw new LogicException(message: 'Access to not defined input parameter "' . $parameterName . '"');
    }

    public function getInputString(string $keyName): ?string
    {
        return $this->readInputString(inputParameter: $this->getDefinedInputParameter(parameterName: $keyName));
    }

    public function getInputInteger(string $keyName): ?int
    {
        $inputParameter = $this->getDefinedInputParameter(parameterName: $keyName);

        return match ($inputParameter->source) {
            InputSourceEnum::QUERY => $this->context->httpRequest->getQueryInteger(name: $keyName),
            InputSourceEnum::POST => $this->context->httpRequest->getPostInteger(name: $keyName),
        };
    }

    public function getInputFloat(string $keyName): ?float
    {
        $inputParameter = $this->getDefinedInputParameter(parameterName: $keyName);

        return match ($inputParameter->source) {
            InputSourceEnum::QUERY => $this->context->httpRequest->getQueryFloat(name: $keyName),
            InputSourceEnum::POST => $this->context->httpRequest->getPostFloat(name: $keyName),
        };
    }

    /**
     * @return ?array<array-key, mixed>
     */
    public function getInputArray(string $keyName): ?array
    {
        return $this->readInputArray(inputParameter: $this->getDefinedInputParameter(parameterName: $keyName));
    }

    protected function setContentType(ContentType $contentType): void
    {
        $this->context->content->setContentType(contentType: $contentType);
    }

    protected function getPathVar(int $nr): ?string
    {
        return $this->context->pathVars->get(nr: $nr);
    }

    /**
     * `null` if the path variable is missing or not strictly an integer (e.g. `"12abc"`, `"+12"`, `" 12"`, overflow).
     */
    protected function getPathVarAsInt(int $nr): ?int
    {
        return $this->context->pathVars->getAsInt(nr: $nr);
    }

    /**
     * For an ID in the URL (`subscription-42.html`): a missing or non-integer value throws a `NotFoundException`.
     */
    protected function getRequiredPathVarAsInt(int $nr): int
    {
        return $this->context->pathVars->getRequiredAsInt(nr: $nr);
    }

    /**
     * The trimmed value; a missing or empty value throws a `NotFoundException`.
     */
    protected function getRequiredPathVarAsString(int $nr): string
    {
        return $this->context->pathVars->getRequiredAsString(nr: $nr);
    }

    protected function getHtmlDocument(): HtmlDocument
    {
        return $this->context->getHtmlDocument();
    }

    protected function setContentByXmlObject(SimpleXmlExtended $xmlObject): void
    {
        $xml = $xmlObject->asXML();
        if ($xml === false) {
            throw new LogicException(message: 'The XML object cannot be converted to a string');
        }
        $this->setContent(contentString: $xml);
    }

    protected function setContentByJsonObject(stdClass $jsonObject): void
    {
        $this->setContent(contentString: JsonUtils::convertToJsonString(valueToConvert: $jsonObject));
    }

    protected function setSuccessResponseContent(
        stdClass $data = new stdClass(),
        bool $sendAndExit = false,
    ): void {
        $contentType = $this->context->content->getContentType();
        if ($contentType->isJson()) {
            $httpSuccessResponseContent = HttpSuccessResponseContent::createJsonResponseContent(
                data: $data,
            );
        } elseif ($contentType->isTxt() || $contentType->isCsv()) {
            $httpSuccessResponseContent = HttpSuccessResponseContent::createTextResponseContent(
                data: $data,
            );
        } else {
            throw new LogicException(message: 'Invalid contentType: ' . $contentType->type);
        }
        if (!$sendAndExit) {
            $this->setContent(contentString: $httpSuccessResponseContent->content);
            return;
        }
        HttpResponse::createResponseFromString(
            httpStatusCode: HttpStatusCodeEnum::HTTP_OK,
            contentString: $httpSuccessResponseContent->content,
            contentType: $contentType,
            httpRequest: $this->context->httpRequest,
        )->sendAndExit();
    }

    protected function setErrorResponseContent(
        string $errorMessage,
        HttpStatusCodeEnum $httpStatusCode = HttpStatusCodeEnum::HTTP_BAD_REQUEST,
        int|string|null $errorCode = null,
        ?stdClass $data = null,
        bool $sendAndExit = false,
    ): void {
        $contentHandler = $this->context->content;
        $contentType = $contentHandler->getContentType();
        if ($contentType->isJson()) {
            $httpErrorResponseContent = HttpErrorResponseContent::createJsonResponseContent(
                errorMessage: $errorMessage,
                errorCode: $errorCode,
                data: $data,
            );
        } elseif ($contentType->isTxt() || $contentType->isCsv()) {
            $httpErrorResponseContent = HttpErrorResponseContent::createTextResponseContent(
                errorMessage: $errorMessage,
                errorCode: $errorCode,
            );
        } else {
            throw new LogicException(message: 'Invalid contentType: ' . $contentType->type);
        }
        if (!$sendAndExit) {
            $this->setContent(contentString: $httpErrorResponseContent->content);
            $contentHandler->httpStatusCode = $httpStatusCode;
            return;
        }
        HttpResponse::createResponseFromString(
            httpStatusCode: $httpStatusCode,
            contentString: $httpErrorResponseContent->content,
            contentType: $contentType,
            httpRequest: $this->context->httpRequest,
        )->sendAndExit();
    }

    protected function getJsonRequestBody(): JsonRequestBody
    {
        try {
            return $this->context->getJsonRequestBody();
        } catch (Throwable $throwable) {
            $this->setErrorResponseContent(
                errorMessage: $throwable->getMessage(),
                errorCode: $throwable->getCode(),
                sendAndExit: true,
            );
            exit;
        }
    }
}
