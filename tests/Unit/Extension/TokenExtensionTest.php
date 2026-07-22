<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Test\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xoops\SmartyExtensions\Extension\TokenExtension;
use Xoops\SmartyExtensions\Test\Stubs\TemplateStub;

#[CoversClass(TokenExtension::class)]
final class TokenExtensionTest extends TestCase
{
    private function tpl(): object
    {
        return $this->createMock(TemplateStub::class);
    }

    // ──────────────────────────────────────────────
    // Registry
    // ──────────────────────────────────────────────

    #[Test]
    public function getFunctionsReturnsOneEntry(): void
    {
        $ext = new TokenExtension();
        $functions = $ext->getFunctions();
        $this->assertCount(1, $functions);
        $this->assertArrayHasKey('xoToken', $functions);
    }

    // ──────────────────────────────────────────────
    // xoToken
    // ──────────────────────────────────────────────

    #[Test]
    public function xoTokenRendersMetaTagContainingToken(): void
    {
        $ext = new TokenExtension(new \XoopsSecurity());
        $result = $ext->xoToken([], $this->tpl());

        $this->assertStringContainsString('<meta name="xoops-token"', $result);
        $this->assertStringContainsString('content="test-token"', $result);
    }

    #[Test]
    public function xoTokenEscapesTokenForHtml(): void
    {
        // Untyped override (matches real \XoopsSecurity::createToken()'s loose
        // signature) — see project convention on core-method override compatibility.
        $security = new class extends \XoopsSecurity {
            public function createToken($timeout = 0, $name = 'XOOPS_TOKEN')
            {
                return '"><script>alert(1)</script>';
            }
        };

        $ext = new TokenExtension($security);
        $result = $ext->xoToken([], $this->tpl());

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $result);
    }

    #[Test]
    public function xoTokenReturnsEmptyWhenNoSecurity(): void
    {
        $ext = new TokenExtension(null);
        $this->assertSame('', $ext->xoToken([], $this->tpl()));
    }
}
