<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\security;

use actra\yuf\security\CsrfHiddenFieldRenderer;
use actra\yuf\security\SessionCsrfTokenSource;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use PHPUnit\Framework\TestCase;

final class CsrfHiddenFieldRendererTest extends TestCase
{
    public function testRendersTheTokenOfTheSourceAsHiddenField(): void
    {
        $this->assertSame(
            '<input type="hidden" name="csrftoken" value="session-token">',
            CsrfHiddenFieldRenderer::render(csrfTokenSource: new InMemoryCsrfTokenSource(token: 'session-token')),
        );
    }

    public function testCreatesTheTokenOfTheSessionIfMissing(): void
    {
        $session = new Session(storage: new ArraySessionStorage());
        $source = new SessionCsrfTokenSource(session: $session);

        $html = CsrfHiddenFieldRenderer::render(csrfTokenSource: $source);

        $this->assertSame('<input type="hidden" name="csrftoken" value="' . $source->getToken() . '">', $html);
    }

    public function testRendersNothingWithoutTokenSource(): void
    {
        $this->assertSame('', CsrfHiddenFieldRenderer::render(csrfTokenSource: null));
    }
}
