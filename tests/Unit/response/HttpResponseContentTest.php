<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\response;

use actra\yuf\response\HttpErrorResponseContent;
use actra\yuf\response\HttpSuccessResponseContent;
use ArrayObject;
use PHPUnit\Framework\TestCase;
use stdClass;

final class HttpResponseContentTest extends TestCase
{
    public function testSuccessJson(): void
    {
        $data = new stdClass();
        $data->id = 5;

        $content = HttpSuccessResponseContent::createJsonResponseContent(data: $data);

        $this->assertSame('{"success":true,"data":{"id":5}}', $content->content);
    }

    public function testSuccessJsonKeepsUnicode(): void
    {
        $data = new stdClass();
        $data->name = 'Zürich';

        $content = HttpSuccessResponseContent::createJsonResponseContent(data: $data);

        $this->assertSame('{"success":true,"data":{"name":"Zürich"}}', $content->content);
    }

    public function testSuccessText(): void
    {
        $data = new stdClass();
        $data->id = 5;

        $content = HttpSuccessResponseContent::createTextResponseContent(data: $data);

        $this->assertSame('success' . PHP_EOL . print_r($data, true), $content->content);
    }

    public function testErrorJsonWithoutData(): void
    {
        $content = HttpErrorResponseContent::createJsonResponseContent(errorMessage: 'Broken', errorCode: 42);

        $this->assertSame('{"success":false,"error":{"code":42,"message":"Broken"}}', $content->content);
    }

    public function testErrorJsonWithoutCodeHasNullCode(): void
    {
        $content = HttpErrorResponseContent::createJsonResponseContent(errorMessage: 'Broken');

        $this->assertSame('{"success":false,"error":{"code":null,"message":"Broken"}}', $content->content);
    }

    public function testErrorJsonWithObjectData(): void
    {
        $data = new stdClass();
        $data->field = 'email';

        $content = HttpErrorResponseContent::createJsonResponseContent(
            errorMessage: 'Invalid',
            errorCode: 'E_FIELD',
            data: $data,
        );

        $this->assertSame(
            '{"success":false,"error":{"code":"E_FIELD","message":"Invalid"},"data":{"field":"email"}}',
            $content->content,
        );
    }

    public function testErrorJsonLeavesOutEmptyArrayObjectData(): void
    {
        $content = HttpErrorResponseContent::createJsonResponseContent(
            errorMessage: 'Invalid',
            data: new ArrayObject(),
        );

        $this->assertSame('{"success":false,"error":{"code":null,"message":"Invalid"}}', $content->content);
    }

    public function testErrorJsonWithArrayObjectData(): void
    {
        /** @var ArrayObject<array-key, mixed> $data */
        $data = new ArrayObject(['field' => 'email']);
        $content = HttpErrorResponseContent::createJsonResponseContent(
            errorMessage: 'Invalid',
            data: $data,
        );

        $this->assertSame(
            '{"success":false,"error":{"code":null,"message":"Invalid"},"data":{"field":"email"}}',
            $content->content,
        );
    }

    public function testErrorText(): void
    {
        $content = HttpErrorResponseContent::createTextResponseContent(errorMessage: 'Broken', errorCode: 42);

        $this->assertSame('error: Broken (42)', $content->content);
    }

    public function testErrorTextWithoutCode(): void
    {
        $content = HttpErrorResponseContent::createTextResponseContent(errorMessage: 'Broken');

        $this->assertSame('error: Broken ()', $content->content);
    }

    public function testErrorTextWithAdditionalInfo(): void
    {
        /** @var ArrayObject<array-key, mixed> $additionalInfo */
        $additionalInfo = new ArrayObject(['field' => 'email']);

        $content = HttpErrorResponseContent::createTextResponseContent(
            errorMessage: 'Invalid',
            errorCode: 'E_FIELD',
            additionalInfo: $additionalInfo,
        );

        $this->assertSame(
            'error: Invalid (E_FIELD)' . PHP_EOL . PHP_EOL . print_r($additionalInfo, true),
            $content->content,
        );
    }

    public function testErrorTextLeavesOutEmptyAdditionalInfo(): void
    {
        $content = HttpErrorResponseContent::createTextResponseContent(
            errorMessage: 'Invalid',
            errorCode: 1,
            additionalInfo: new ArrayObject(),
        );

        $this->assertSame('error: Invalid (1)', $content->content);
    }
}
