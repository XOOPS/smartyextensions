<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Test\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xoops\SmartyExtensions\Extension\NavigationExtension;
use Xoops\SmartyExtensions\Test\Stubs\TemplateStub;

/**
 * Coverage for the local-first render_qr_code plugin (S5): QR codes are generated
 * locally via chillerlan/php-qrcode (inline SVG data-URI); the external service is
 * never used unless externalFallback=true is explicitly passed.
 */
#[CoversClass(NavigationExtension::class)]
final class RenderQrCodeTest extends TestCase
{
    private NavigationExtension $ext;

    protected function setUp(): void
    {
        $this->ext = new NavigationExtension();
    }

    private function template(): TemplateStub
    {
        return $this->createMock(TemplateStub::class);
    }

    #[Test]
    public function producesLocalSvgDataUriByDefault(): void
    {
        $result = $this->ext->renderQrCode(['text' => 'https://xoops.org/'], $this->template());

        $this->assertStringContainsString('data:image/svg+xml;base64,', $result);
        $this->assertStringContainsString('<img', $result);
    }

    #[Test]
    public function doesNotUseExternalServiceByDefault(): void
    {
        $result = $this->ext->renderQrCode(['text' => 'https://xoops.org/'], $this->template());

        $this->assertStringNotContainsString('api.qrserver.com', $result);
        $this->assertStringNotContainsString('http://', $result);
    }

    #[Test]
    public function emptyTextReturnsEmptyString(): void
    {
        $this->assertSame('', $this->ext->renderQrCode(['text' => ''], $this->template()));
        $this->assertSame('', $this->ext->renderQrCode([], $this->template()));
    }

    #[Test]
    public function clampsTooSmallSizeToMinimum(): void
    {
        $result = $this->ext->renderQrCode(['text' => 'x', 'size' => 5], $this->template());

        $this->assertStringContainsString('width="32"', $result);
        $this->assertStringContainsString('height="32"', $result);
    }

    #[Test]
    public function clampsTooLargeSizeToMaximum(): void
    {
        $result = $this->ext->renderQrCode(['text' => 'x', 'size' => 5000], $this->template());

        $this->assertStringContainsString('width="1024"', $result);
        $this->assertStringContainsString('height="1024"', $result);
    }

    #[Test]
    public function assignModeReturnsEmptyAndStoresImg(): void
    {
        $template = new class implements TemplateStub {
            /** @var array<string, mixed> */
            public array $assigned = [];

            public function assign(string $name, mixed $value = null): void
            {
                $this->assigned[$name] = $value;
            }

            public function getTemplateVars(?string $name = null): mixed
            {
                return $this->assigned;
            }
        };

        $result = $this->ext->renderQrCode(['text' => 'https://xoops.org/', 'assign' => 'qr'], $template);

        $this->assertSame('', $result);
        $this->assertArrayHasKey('qr', $template->assigned);
        $this->assertStringContainsString('data:image/svg+xml;base64,', (string) $template->assigned['qr']);
    }
}
