<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\UnauthorizedAccessRightException;
use actra\yuf\auth\UnauthorizedIpAddressException;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpStatusCode;
use actra\yuf\core\InputParameter;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\exception\NotFoundException;
use actra\yuf\tests\Double\auth\TestAuthUser;
use actra\yuf\tests\Double\core\ConfigurableTestView;
use actra\yuf\tests\Double\core\ViewContextFactory;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Not covered: required input parameters that are present, and getJsonRequestBody() (both read HttpRequest /
 * php://input, which are statically cached and cannot be fed in tests); getHtmlDocument() (HtmlDocument reads
 * RequestHandler::get() until step 10).
 */
final class BaseViewTest extends TestCase
{
    private bool $hadRemoteAddress = false;
    private mixed $remoteAddress = null;

    #[\Override]
    protected function setUp(): void
    {
        $this->hadRemoteAddress = array_key_exists(key: 'REMOTE_ADDR', array: $_SERVER);
        $this->remoteAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->hadRemoteAddress) {
            $_SERVER['REMOTE_ADDR'] = $this->remoteAddress;
        } else {
            unset($_SERVER['REMOTE_ADDR']);
        }
        TestAuthUser::release();
    }

    public function testContextIsAvailableToTheView(): void
    {
        $context = ViewContextFactory::create();
        $view = new ConfigurableTestView(context: $context);

        $this->assertSame($context, $view->callGetContext());
    }

    public function testViewGroupMismatchThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('View group needs to be backend instead of frontend');
        new ConfigurableTestView(
            context: ViewContextFactory::create(viewGroup: 'frontend'),
            requiredViewGroupName: 'backend',
        );
    }

    public function testIpAddressInWhitelistIsAllowed(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.5';

        $view = new ConfigurableTestView(
            context: ViewContextFactory::create(),
            ipWhitelist: ['10.0.0.1', '192.168.1.0/24'],
        );

        $this->assertSame(0, $view->maxAllowedPathVars);
    }

    public function testIpAddressNotInWhitelistThrows(): void
    {
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        $this->expectException(UnauthorizedIpAddressException::class);
        $this->expectExceptionMessageIs('Invalid IP address 8.8.8.8');
        new ConfigurableTestView(context: ViewContextFactory::create(), ipWhitelist: ['10.0.0.1']);
    }

    public function testEmptyWhitelistAllowsAll(): void
    {
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        $view = new ConfigurableTestView(context: ViewContextFactory::create(), ipWhitelist: []);

        $this->assertSame(0, $view->maxAllowedPathVars);
    }

    public function testRequiredAccessRightsWithoutUserThrow(): void
    {
        $this->expectException(UnauthorizedAccessRightException::class);
        new ConfigurableTestView(
            context: ViewContextFactory::create(),
            requiredAccessRights: AccessRightCollection::createFromStringArray(input: ['admin']),
        );
    }

    public function testRequiredAccessRightsWithoutMatchingRightThrow(): void
    {
        $this->expectException(UnauthorizedAccessRightException::class);
        new ConfigurableTestView(
            context: ViewContextFactory::create(),
            authUser: TestAuthUser::create(accessRights: ['editor']),
            requiredAccessRights: AccessRightCollection::createFromStringArray(input: ['admin']),
        );
    }

    public function testRequiredAccessRightsWithMatchingRightPass(): void
    {
        $view = new ConfigurableTestView(
            context: ViewContextFactory::create(),
            authUser: TestAuthUser::create(accessRights: ['admin']),
            requiredAccessRights: AccessRightCollection::createFromStringArray(input: ['admin', 'editor']),
        );

        $this->assertSame(0, $view->maxAllowedPathVars);
    }

    public function testMaxAllowedPathVarsIsKept(): void
    {
        $view = new ConfigurableTestView(context: ViewContextFactory::create(), maxAllowedPathVars: 2);

        $this->assertSame(2, $view->maxAllowedPathVars);
    }

    public function testPathVars(): void
    {
        $view = new ConfigurableTestView(
            context: ViewContextFactory::create(pathVars: ['subscription', ' 42 ', 'x', '', '4x']),
        );

        $this->assertSame('subscription', $view->callGetPathVar(nr: 0));
        $this->assertSame('42', $view->callGetPathVar(nr: 1));
        $this->assertNull($view->callGetPathVar(nr: 9));
        $this->assertNull($view->callGetPathVarAsInt(nr: 1));
        $this->assertSame(42, new ConfigurableTestView(
            context: ViewContextFactory::create(pathVars: ['a', '42']),
        )->callGetPathVarAsInt(nr: 1));
        $this->assertNull($view->callGetPathVarAsInt(nr: 4));
        $this->assertNull($view->callGetPathVarAsInt(nr: 9));
        $this->assertSame('x', $view->callGetRequiredPathVarAsString(nr: 2));
    }

    public function testRequiredPathVarAsInt(): void
    {
        $view = new ConfigurableTestView(context: ViewContextFactory::create(pathVars: ['a', '42', 'x']));

        $this->assertSame(42, $view->callGetRequiredPathVarAsInt(nr: 1));
        $this->expectException(NotFoundException::class);
        $view->callGetRequiredPathVarAsInt(nr: 2);
    }

    public function testRequiredPathVarAsStringThrowsIfEmpty(): void
    {
        $view = new ConfigurableTestView(context: ViewContextFactory::create(pathVars: ['a', ' ']));

        $this->expectException(NotFoundException::class);
        $view->callGetRequiredPathVarAsString(nr: 1);
    }

    public function testSetContentReachesTheContentHandler(): void
    {
        $context = ViewContextFactory::create();
        $view = new ConfigurableTestView(context: $context);

        $view->callSetContent(contentString: 'hello');

        $this->assertSame('hello', $context->content->getContent());
    }

    public function testSetContentTypeReachesTheContentHandler(): void
    {
        $context = ViewContextFactory::create();
        $view = new ConfigurableTestView(context: $context);

        $view->callSetContentType(contentType: ContentType::createJson());

        $this->assertTrue($context->content->getContentType()->isJson());
    }

    public function testJsonErrorResponseSetsContentAndStatusCode(): void
    {
        $context = ViewContextFactory::create(contentType: ContentType::createJson());
        $view = new ConfigurableTestView(context: $context);

        $view->callSetErrorResponseContent(
            errorMessage: 'broken',
            httpStatusCode: HttpStatusCode::HTTP_NOT_FOUND,
            errorCode: 7,
        );

        $this->assertSame(HttpStatusCode::HTTP_NOT_FOUND, $context->content->httpStatusCode);
        $this->assertJsonStringEqualsJsonString(
            '{"success":false,"error":{"code":7,"message":"broken"}}',
            $context->content->getContent(),
        );
    }

    public function testJsonErrorResponseDefaultsToBadRequest(): void
    {
        $context = ViewContextFactory::create(contentType: ContentType::createJson());

        new ConfigurableTestView(context: $context)->callSetErrorResponseContent(errorMessage: 'x');

        $this->assertSame(HttpStatusCode::HTTP_BAD_REQUEST, $context->content->httpStatusCode);
    }

    public function testErrorResponseForHtmlThrows(): void
    {
        $view = new ConfigurableTestView(context: ViewContextFactory::create());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('Invalid contentType: ' . ContentType::HTML);
        $view->callSetErrorResponseContent(errorMessage: 'x');
    }

    public function testJsonSuccessResponse(): void
    {
        $context = ViewContextFactory::create(contentType: ContentType::createJson());
        $data = new stdClass();
        $data->id = 5;

        new ConfigurableTestView(context: $context)->callSetSuccessResponseContent(data: $data);

        $this->assertSame(HttpStatusCode::HTTP_OK, $context->content->httpStatusCode);
        $this->assertJsonStringEqualsJsonString(
            '{"success":true,"data":{"id":5}}',
            $context->content->getContent(),
        );
    }

    public function testSuccessResponseForHtmlThrows(): void
    {
        $view = new ConfigurableTestView(context: ViewContextFactory::create());

        $this->expectException(LogicException::class);
        $view->callSetSuccessResponseContent();
    }

    public function testMissingRequiredParameterThrowsNotFoundForHtml(): void
    {
        $parameters = new InputParameterCollection();
        $parameters->add(inputParameter: new InputParameter(name: 'viewTestMissingParam', isRequired: true));

        $this->expectException(NotFoundException::class);
        new ConfigurableTestView(
            context: ViewContextFactory::create(),
            inputParameterCollection: $parameters,
        );
    }

    public function testMissingRequiredParameterSetsErrorResponseForJson(): void
    {
        $parameters = new InputParameterCollection();
        $parameters->add(inputParameter: new InputParameter(name: 'viewTestMissingParam', isRequired: true));
        $context = ViewContextFactory::create(contentType: ContentType::createJson());

        new ConfigurableTestView(context: $context, inputParameterCollection: $parameters);

        $this->assertSame(HttpStatusCode::HTTP_BAD_REQUEST, $context->content->httpStatusCode);
        $this->assertStringContainsString(
            'missing or empty mandatory parameter: viewTestMissingParam',
            $context->content->getContent(),
        );
    }

    public function testUndefinedInputParameterThrows(): void
    {
        $view = new ConfigurableTestView(context: ViewContextFactory::create());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('Access to not defined input parameter "foo"');
        $view->getInputString(keyName: 'foo');
    }

    public function testDefinedOptionalInputParameterIsNullIfAbsent(): void
    {
        $parameters = new InputParameterCollection();
        $parameters->add(inputParameter: new InputParameter(name: 'viewTestOptionalParam', isRequired: false));
        $view = new ConfigurableTestView(
            context: ViewContextFactory::create(),
            inputParameterCollection: $parameters,
        );

        $this->assertNull($view->getInputString(keyName: 'viewTestOptionalParam'));
    }
}
