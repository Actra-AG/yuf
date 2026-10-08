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
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\InputParameter;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\core\InputSourceEnum;
use actra\yuf\exception\NotFoundException;
use actra\yuf\tests\Double\auth\TestAuthUser;
use actra\yuf\tests\Double\core\ConfigurableTestView;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\core\ViewContextFactory;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Not covered: getHtmlDocument() (needs a processed request).
 */
final class BaseViewTest extends TestCase
{
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
        $view = new ConfigurableTestView(
            context: ViewContextFactory::create(
                httpRequest: HttpRequestFactory::create(remoteAddress: '192.168.1.5'),
            ),
            ipWhitelist: ['10.0.0.1', '192.168.1.0/24'],
        );

        $this->assertSame(0, $view->maxAllowedPathVars);
    }

    public function testIpAddressNotInWhitelistThrows(): void
    {
        $this->expectException(UnauthorizedIpAddressException::class);
        $this->expectExceptionMessageIs('Invalid IP address 8.8.8.8');
        new ConfigurableTestView(
            context: ViewContextFactory::create(httpRequest: HttpRequestFactory::create(remoteAddress: '8.8.8.8')),
            ipWhitelist: ['10.0.0.1'],
        );
    }

    public function testEmptyWhitelistAllowsAll(): void
    {
        $view = new ConfigurableTestView(
            context: ViewContextFactory::create(httpRequest: HttpRequestFactory::create(remoteAddress: '8.8.8.8')),
            ipWhitelist: [],
        );

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
            httpStatusCode: HttpStatusCodeEnum::HTTP_NOT_FOUND,
            errorCode: 7,
        );

        $this->assertSame(HttpStatusCodeEnum::HTTP_NOT_FOUND, $context->content->httpStatusCode);
        $this->assertJsonStringEqualsJsonString(
            '{"success":false,"error":{"code":7,"message":"broken"}}',
            $context->content->getContent(),
        );
    }

    public function testJsonErrorResponseDefaultsToBadRequest(): void
    {
        $context = ViewContextFactory::create(contentType: ContentType::createJson());

        new ConfigurableTestView(context: $context)->callSetErrorResponseContent(errorMessage: 'x');

        $this->assertSame(HttpStatusCodeEnum::HTTP_BAD_REQUEST, $context->content->httpStatusCode);
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

        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $context->content->httpStatusCode);
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
        $parameters->add(
            inputParameter: new InputParameter(
                name: 'viewTestMissingParam',
                source: InputSourceEnum::QUERY,
                isRequired: true,
            ),
        );

        $this->expectException(NotFoundException::class);
        new ConfigurableTestView(
            context: ViewContextFactory::create(),
            inputParameterCollection: $parameters,
        );
    }

    public function testMissingRequiredParameterSetsErrorResponseForJson(): void
    {
        $parameters = new InputParameterCollection();
        $parameters->add(
            inputParameter: new InputParameter(
                name: 'viewTestMissingParam',
                source: InputSourceEnum::QUERY,
                isRequired: true,
            ),
        );
        $context = ViewContextFactory::create(contentType: ContentType::createJson());

        new ConfigurableTestView(context: $context, inputParameterCollection: $parameters);

        $this->assertSame(HttpStatusCodeEnum::HTTP_BAD_REQUEST, $context->content->httpStatusCode);
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
        $parameters->add(
            inputParameter: new InputParameter(
                name: 'viewTestOptionalParam',
                source: InputSourceEnum::QUERY,
                isRequired: false,
            ),
        );
        $view = new ConfigurableTestView(
            context: ViewContextFactory::create(),
            inputParameterCollection: $parameters,
        );

        $this->assertNull($view->getInputString(keyName: 'viewTestOptionalParam'));
    }

    /**
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $post
     */
    private function createViewWithInput(
        InputSourceEnum $source,
        array $query = [],
        array $post = [],
        bool $isRequired = false,
        string $body = '',
    ): ConfigurableTestView {
        $parameters = new InputParameterCollection();
        $parameters->add(inputParameter: new InputParameter(name: 'p', source: $source, isRequired: $isRequired));
        $httpRequest = HttpRequestFactory::create(queryParameters: $query, postParameters: $post, body: $body);

        return new ConfigurableTestView(
            context: ViewContextFactory::create(httpRequest: $httpRequest),
            inputParameterCollection: $parameters,
        );
    }

    public function testInputIsReadFromTheDeclaredQuerySource(): void
    {
        $view = $this->createViewWithInput(
            source: InputSourceEnum::QUERY,
            query: ['p' => ' from query '],
            post: ['p' => 'from post'],
        );

        $this->assertSame('from query', $view->getInputString(keyName: 'p'));
    }

    public function testInputIsReadFromTheDeclaredPostSource(): void
    {
        $view = $this->createViewWithInput(
            source: InputSourceEnum::POST,
            query: ['p' => 'from query'],
            post: ['p' => ' from post '],
        );

        $this->assertSame('from post', $view->getInputString(keyName: 'p'));
    }

    public function testInputOfTheOtherSourceIsIgnored(): void
    {
        $view = $this->createViewWithInput(source: InputSourceEnum::POST, query: ['p' => 'from query']);

        $this->assertNull($view->getInputString(keyName: 'p'));
    }

    public function testIntegerFloatAndArrayAreReadFromTheDeclaredSource(): void
    {
        $query = $this->createViewWithInput(source: InputSourceEnum::QUERY, query: ['p' => '42']);
        $post = $this->createViewWithInput(source: InputSourceEnum::POST, post: ['p' => '1.5']);
        $array = $this->createViewWithInput(source: InputSourceEnum::POST, post: ['p' => ['a', 'b']]);

        $this->assertSame(42, $query->getInputInteger(keyName: 'p'));
        $this->assertSame(1.5, $post->getInputFloat(keyName: 'p'));
        $this->assertSame(['a', 'b'], $array->getInputArray(keyName: 'p'));
    }

    public function testNumberInputIsStrict(): void
    {
        $view = $this->createViewWithInput(source: InputSourceEnum::QUERY, query: ['p' => '12abc']);

        $this->assertNull($view->getInputInteger(keyName: 'p'));
        $this->assertNull($view->getInputFloat(keyName: 'p'));
    }

    public function testInputDomainIsSanitized(): void
    {
        $view = $this->createViewWithInput(source: InputSourceEnum::QUERY, query: ['p' => ' Example.COM ']);

        $this->assertSame('example.com', $view->getInputDomain(keyName: 'p'));
    }

    public function testInputOfAnUndefinedParameterThrowsForEveryType(): void
    {
        $view = $this->createViewWithInput(source: InputSourceEnum::QUERY, query: ['other' => '1']);

        $this->expectException(LogicException::class);
        $view->getInputInteger(keyName: 'other');
    }

    /**
     * @return iterable<string, array{InputSourceEnum, array<array-key, mixed>, array<array-key, mixed>, bool}>
     */
    public static function requiredParameterProvider(): iterable
    {
        yield 'query text' => [InputSourceEnum::QUERY, ['p' => 'x'], [], true];
        yield 'query zero' => [InputSourceEnum::QUERY, ['p' => '0'], [], true];
        yield 'query array' => [InputSourceEnum::QUERY, ['p' => ['x']], [], true];
        yield 'post text' => [InputSourceEnum::POST, [], ['p' => 'x'], true];
        yield 'query missing' => [InputSourceEnum::QUERY, [], [], false];
        yield 'query empty' => [InputSourceEnum::QUERY, ['p' => ''], [], false];
        yield 'query blank' => [InputSourceEnum::QUERY, ['p' => '  '], [], false];
        yield 'query empty array' => [InputSourceEnum::QUERY, ['p' => []], [], false];
        yield 'post missing, query given' => [InputSourceEnum::POST, ['p' => 'x'], [], false];
        yield 'query missing, post given' => [InputSourceEnum::QUERY, [], ['p' => 'x'], false];
    }

    /**
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $post
     */
    #[DataProvider('requiredParameterProvider')]
    public function testRequiredParameterIsCheckedInTheDeclaredSource(
        InputSourceEnum $source,
        array $query,
        array $post,
        bool $isPresent,
    ): void {
        if (!$isPresent) {
            $this->expectException(NotFoundException::class);
        }

        $view = $this->createViewWithInput(source: $source, query: $query, post: $post, isRequired: true);

        $this->assertSame(0, $view->maxAllowedPathVars);
    }

    public function testJsonRequestBodyIsReadFromTheRequest(): void
    {
        $view = $this->createViewWithInput(source: InputSourceEnum::QUERY, body: '{"id": 5, "name": " Ann "}');

        $jsonRequestBody = $view->callGetJsonRequestBody();

        $this->assertSame(5, $jsonRequestBody->getRequiredInteger(keyName: 'id'));
        $this->assertSame('Ann', $jsonRequestBody->getRequiredString(keyName: 'name'));
    }

    public function testEmptyRequestBodyIsAnEmptyJsonObject(): void
    {
        $view = $this->createViewWithInput(source: InputSourceEnum::QUERY);

        $this->assertNull($view->callGetJsonRequestBody()->getOptionalString(keyName: 'id'));
    }
}
