<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Test\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xoops\SmartyExtensions\Extension\NavigationExtension;
use Xoops\SmartyExtensions\Test\Stubs\TemplateStub;

/**
 * Focused coverage for the upgraded render_pagination plugin, which now
 * supports both the data-driven (total/limit/start) and the legacy
 * back-compatible (totalPages/currentPage) input modes.
 */
#[CoversClass(NavigationExtension::class)]
final class RenderPaginationTest extends TestCase
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

    // ── Data-driven mode (total / limit / start) ────────────

    #[Test]
    public function dataDrivenModeRendersNumberedPageLinks(): void
    {
        // total=95, limit=10 => totalPages = ceil(9.5) = 10
        // start=20          => currentPage = floor(20/10) + 1 = 3
        $result = $this->ext->renderPagination(
            ['total' => 95, 'limit' => 10, 'start' => 20, 'urlPattern' => 'index.php?start={start}'],
            $this->template(),
        );

        // Ten numbered page links 1..10.
        for ($i = 1; $i <= 10; $i++) {
            $this->assertStringContainsString('>' . $i . '</a>', $result);
        }
        $this->assertStringContainsString('pagination', $result);
    }

    #[Test]
    public function dataDrivenModeMarksCurrentPageActive(): void
    {
        $result = $this->ext->renderPagination(
            ['total' => 95, 'limit' => 10, 'start' => 20, 'urlPattern' => 'index.php?start={start}'],
            $this->template(),
        );

        // Page 3 is current: it carries both the active class and aria-current.
        $this->assertStringContainsString(
            '<li class="page-item active" aria-current="page"><a class="page-link" href="index.php?start=20">3</a></li>',
            $result,
        );
    }

    #[Test]
    public function dataDrivenModeComputesStartPlaceholderHrefs(): void
    {
        $result = $this->ext->renderPagination(
            ['total' => 95, 'limit' => 10, 'start' => 20, 'urlPattern' => 'index.php?start={start}'],
            $this->template(),
        );

        // Page 3 => (3-1)*10 = 20
        $this->assertStringContainsString('href="index.php?start=20">3</a>', $result);
        // Page 4 => (4-1)*10 = 30
        $this->assertStringContainsString('href="index.php?start=30">4</a>', $result);
    }

    #[Test]
    public function dataDrivenModePreviousLinkUsesPriorPageStart(): void
    {
        $result = $this->ext->renderPagination(
            ['total' => 95, 'limit' => 10, 'start' => 20, 'urlPattern' => 'index.php?start={start}'],
            $this->template(),
        );

        // Current page 3 => Previous targets page 2 => start = (2-1)*10 = 10
        $this->assertStringContainsString(
            'href="index.php?start=10" aria-label="Previous">&laquo;</a>',
            $result,
        );
    }

    // ── Back-compatible mode (totalPages / currentPage) ─────

    #[Test]
    public function bcModeMarksCurrentPageActive(): void
    {
        $result = $this->ext->renderPagination(
            ['totalPages' => 5, 'currentPage' => 2, 'urlPattern' => '?page={page}'],
            $this->template(),
        );

        // Page 2 is current.
        $this->assertStringContainsString(
            '<li class="page-item active" aria-current="page"><a class="page-link" href="?page=2">2</a></li>',
            $result,
        );
    }

    #[Test]
    public function bcModeRendersPagePlaceholderHrefs(): void
    {
        $result = $this->ext->renderPagination(
            ['totalPages' => 5, 'currentPage' => 2, 'urlPattern' => '?page={page}'],
            $this->template(),
        );

        $this->assertStringContainsString('href="?page=3">3</a>', $result);
    }

    // ── Single-page short circuit ───────────────────────────

    #[Test]
    public function singlePageReturnsEmptyString(): void
    {
        // total=5, limit=10 => totalPages = ceil(0.5) = 1 => no navigation.
        $this->assertSame(
            '',
            $this->ext->renderPagination(['total' => 5, 'limit' => 10], $this->template()),
        );
    }

    // ── assign mode ─────────────────────────────────────────

    #[Test]
    public function assignModeReturnsEmptyAndAssignsHtmlToTemplate(): void
    {
        // Capture the assigned value via a stub implementing the same contract.
        $template = new class implements TemplateStub {
            /** @var array<string, mixed> */
            public array $assigned = [];

            public function assign(string $name, mixed $value = null): void
            {
                $this->assigned[$name] = $value;
            }

            /** @return array<string, mixed> */
            public function getTemplateVars(?string $name = null): mixed
            {
                return $this->assigned;
            }
        };

        $result = $this->ext->renderPagination(
            ['totalPages' => 5, 'currentPage' => 2, 'urlPattern' => '?page={page}', 'assign' => 'nav'],
            $template,
        );

        // Direct return is empty; the HTML lands on the template var instead.
        $this->assertSame('', $result);
        $this->assertArrayHasKey('nav', $template->assigned);
        $this->assertIsString($template->assigned['nav']);
        $this->assertNotSame('', $template->assigned['nav']);
        $this->assertStringContainsString('pagination', $template->assigned['nav']);
    }

    // ── HTML escaping of URL pattern values ─────────────────

    #[Test]
    public function urlPatternValuesAreHtmlEscapedInHrefs(): void
    {
        // An ampersand in the pattern must be escaped to &amp; inside the href.
        $result = $this->ext->renderPagination(
            ['totalPages' => 5, 'currentPage' => 2, 'urlPattern' => '?a=1&page={page}'],
            $this->template(),
        );

        $this->assertStringContainsString('href="?a=1&amp;page=3">3</a>', $result);
        $this->assertStringNotContainsString('href="?a=1&page=3"', $result);
    }
}
