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
use actra\yuf\common\SimpleXMLExtended;
use actra\yuf\datacheck\Sanitizer;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use actra\yuf\exception\NotFoundException;
use actra\yuf\response\HttpErrorResponseContent;
use actra\yuf\response\HttpSuccessResponseContent;
use LogicException;
use stdClass;

abstract class BaseView
{
    protected function __construct(
        string $requiredViewGroupName,
        array $ipWhitelist,
        ?AuthUser $authUser,
        AccessRightCollection $requiredAccessRights,
        private readonly InputParameterCollection $inputParameterCollection,
        public readonly int $maxAllowedPathVars = 0
    ) {
        $viewGroup = RequestHandler::get()->route->viewGroup;
        if ($viewGroup !== $requiredViewGroupName) {
            throw new LogicException(
                message: 'View group needs to be ' . $requiredViewGroupName . ' instead of ' . $viewGroup
            );
        }
        $ipAddress = HttpRequest::getRemoteAddress();
        if (
            count(value: $ipWhitelist) > 0
            && !IpValidator::isInWhitelist(
                whiteList: $ipWhitelist,
                ipAddressToCheck: $ipAddress
            )
        ) {
            throw new UnauthorizedIpAddressException(message: 'Invalid IP address ' . $ipAddress);
        }
        if (
            !$requiredAccessRights->isEmpty()
            && (
                is_null(value: $authUser)
                || !$authUser->hasOneOfRights(accessRightCollection: $requiredAccessRights)
            )
        ) {
            throw new UnauthorizedAccessRightException();
        }
        foreach ($inputParameterCollection->listRequiredParameters() as $inputParameter) {
            $name = $inputParameter->name;
            $paramValue = HttpRequest::getInputValue(keyName: $name);
            if (
                is_null(value: $paramValue)
                || (!is_array(value: $paramValue) && trim(string: $paramValue) === '')
                || (is_array(value: $paramValue) && count(value: $paramValue) === 0)
            ) {
                if (ContentHandler::get()->getContentType()->isHtml()) {
                    throw new NotFoundException();
                }
                $this->setErrorResponseContent(errorMessage: 'missing or empty mandatory parameter: ' . $name);

                return;
            }
        }
    }

    protected function setErrorResponseContent(
        string $errorMessage,
        null|int|string $errorCode = null,
        ?stdClass $additionalInfo = null
    ): void {
        $contentType = ContentHandler::get()->getContentType();
        if ($contentType->isJson()) {
            $httpErrorResponseContent = HttpErrorResponseContent::createJsonResponseContent(
                errorMessage: $errorMessage,
                errorCode: $errorCode,
                additionalInfo: $additionalInfo
            );
        } elseif ($contentType->isTxt() || $contentType->isCsv()) {
            $httpErrorResponseContent = HttpErrorResponseContent::createTextResponseContent(
                errorMessage: $errorMessage,
                errorCode: $errorCode
            );
        } else {
            throw new LogicException(message: 'Invalid contentType: ' . $contentType->type);
        }

        $this->setContent(contentString: $httpErrorResponseContent->content);
    }

    protected function setContent(string $contentString): void
    {
        ContentHandler::get()->setContent(contentString: $contentString);
    }

    abstract public function execute(): void;

    public function getInputDomain(string $keyName): ?string
    {
        $this->onlyDefinedInputParametersAllowed($keyName);
        $value = $this->getInputString(keyName: $keyName);
        if (is_null(value: $value)) {
            return null;
        }

        return Sanitizer::domain(input: $value);
    }

    private function onlyDefinedInputParametersAllowed(string $parameterName): void
    {
        if (!$this->inputParameterCollection->hasParameter(name: $parameterName)) {
            throw new LogicException(message: 'Access to not defined input parameter "' . $parameterName . '"');
        }
    }

    public function getInputString(string $keyName): ?string
    {
        $this->onlyDefinedInputParametersAllowed(parameterName: $keyName);

        return HttpRequest::getInputString(keyName: $keyName);
    }

    public function getInputInteger(string $keyName): ?int
    {
        $this->onlyDefinedInputParametersAllowed(parameterName: $keyName);

        return HttpRequest::getInputInteger(keyName: $keyName);
    }

    public function getInputFloat(string $keyName): ?float
    {
        $this->onlyDefinedInputParametersAllowed(parameterName: $keyName);

        return HttpRequest::getInputFloat(keyName: $keyName);
    }

    public function getInputArray(string $keyName): ?array
    {
        $this->onlyDefinedInputParametersAllowed(parameterName: $keyName);

        return HttpRequest::getInputArray(keyName: $keyName);
    }

    protected function setContentType(ContentType $contentType): void
    {
        ContentHandler::get()->setContentType(contentType: $contentType);
    }

    protected function getPathVar(int $nr): ?string
    {
        return RequestHandler::get()->getPathVar(nr: $nr);
    }

    protected function setContentByXmlObject(SimpleXMLExtended $xmlObject): void
    {
        $this->setContent(contentString: $xmlObject->asXML());
    }

    protected function setContentByJsonObject(stdClass $jsonObject): void
    {
        $this->setContent(contentString: JsonUtils::convertToJsonString(valueToConvert: $jsonObject));
    }

    protected function setSuccessResponseContent(stdClass $resultDataObject = new stdClass()): void
    {
        $contentType = ContentHandler::get()->getContentType();
        if ($contentType->isJson()) {
            $httpSuccessResponseContent = HttpSuccessResponseContent::createJsonResponseContent(
                resultDataObject: $resultDataObject
            );
        } elseif ($contentType->isTxt() || $contentType->isCsv()) {
            $httpSuccessResponseContent = HttpSuccessResponseContent::createTextResponseContent(
                resultDataObject: $resultDataObject
            );
        } else {
            throw new LogicException(message: 'Invalid contentType: ' . $contentType->type);
        }

        $this->setContent(contentString: $httpSuccessResponseContent->content);
    }
}