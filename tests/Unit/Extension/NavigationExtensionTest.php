<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Test\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xoops\SmartyExtensions\Extension\NavigationExtension;

#[CoversClass(NavigationExtension::class)]
final class NavigationExtensionTest extends TestCase
{
    private NavigationExtension $ext;

    protected function setUp(): void
    {
        $this->ext = new NavigationExtension();
    }

    // ── Registry counts ─────────────────────────────────────

    #[Test]
    public function getFunctionsReturnsAllEightFunctions(): void
    {
        $functions = $this->ext->getFunctions();
        $this->assertCount(8, $functions);
        $this->assertArrayHasKey('generate_url', $functions);
        $this->assertArrayHasKey('generate_canonical_url', $functions);
        $this->assertArrayHasKey('url_segment', $functions);
        $this->assertArrayHasKey('social_share', $functions);
        $this->assertArrayHasKey('render_breadcrumbs', $functions);
        $this->assertArrayHasKey('render_pagination', $functions);
        $this->assertArrayHasKey('render_qr_code', $functions);
        $this->assertArrayHasKey('render_alert', $functions);
    }

    #[Test]
    public function getModifiersReturnsAllFiveModifiers(): void
    {
        $modifiers = $this->ext->getModifiers();
        $this->assertCount(5, $modifiers);
        $this->assertArrayHasKey('parse_url', $modifiers);
        $this->assertArrayHasKey('strip_protocol', $modifiers);
        $this->assertArrayHasKey('slugify', $modifiers);
        $this->assertArrayHasKey('youtube_id', $modifiers);
        $this->assertArrayHasKey('linkify', $modifiers);
    }

    // ── Modifier: slugify ───────────────────────────────────

    #[Test]
    public function slugifyConvertsTextToSlug(): void
    {
        $this->assertSame('hello-world', $this->ext->slugify('Hello World!'));
    }

    #[Test]
    public function slugifyHandlesMultipleSpacesAndSpecialChars(): void
    {
        $this->assertSame('hello-world-xoops-cms', $this->ext->slugify('Hello World! XOOPS CMS'));
    }

    // ── Modifier: stripProtocol ─────────────────────────────

    #[Test]
    public function stripProtocolRemovesHttps(): void
    {
        $this->assertSame('xoops.org/page', $this->ext->stripProtocol('https://xoops.org/page'));
    }

    #[Test]
    public function stripProtocolRemovesHttp(): void
    {
        $this->assertSame('xoops.org/page', $this->ext->stripProtocol('http://xoops.org/page'));
    }

    // ── Modifier: youtubeId ─────────────────────────────────

    #[Test]
    public function youtubeIdExtractsFromWatchUrl(): void
    {
        $this->assertSame('dQw4w9WgXcQ', $this->ext->youtubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    #[Test]
    public function youtubeIdExtractsFromShortUrl(): void
    {
        $this->assertSame('dQw4w9WgXcQ', $this->ext->youtubeId('https://youtu.be/dQw4w9WgXcQ'));
    }

    #[Test]
    public function youtubeIdExtractsFromEmbedUrl(): void
    {
        $this->assertSame('dQw4w9WgXcQ', $this->ext->youtubeId('https://www.youtube.com/embed/dQw4w9WgXcQ'));
    }

    #[Test]
    public function youtubeIdReturnsEmptyForInvalidUrl(): void
    {
        $this->assertSame('', $this->ext->youtubeId('https://example.com'));
    }

    // ── Modifier: parseUrl ──────────────────────────────────

    #[Test]
    public function parseUrlReturnsComponents(): void
    {
        $result = $this->ext->parseUrl('https://xoops.org/path?q=1');
        $this->assertIsArray($result);
        $this->assertSame('xoops.org', $result['host']);
        $this->assertSame('/path', $result['path']);
        $this->assertSame('q=1', $result['query']);
        $this->assertSame('https', $result['scheme']);
    }

    // ── Modifier: linkify ───────────────────────────────────

    #[Test]
    public function linkifyConvertsUrlsToAnchors(): void
    {
        $result = $this->ext->linkify('Visit https://xoops.org today');
        $this->assertStringContainsString('<a href', $result);
        $this->assertStringContainsString('https://xoops.org', $result);
        $this->assertStringContainsString('target="_blank"', $result);
    }

    #[Test]
    public function linkifyLeavesPlainTextAlone(): void
    {
        $this->assertSame('No links here', $this->ext->linkify('No links here'));
    }

    // ── Function: generateUrl ───────────────────────────────

    #[Test]
    public function generateUrlBuildsUrlWithParams(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        $result = $this->ext->generateUrl(
            ['route' => 'modules/news/article.php', 'params' => ['id' => '5']],
            $tpl,
        );
        // Direct output is HTML-escaped
        $this->assertSame('modules/news/article.php?id=5', $result);
    }

    #[Test]
    public function generateUrlAssignsRawUrlToTemplateVariable(): void
    {
        // assign stores the RAW url — no htmlspecialchars applied — so the
        // caller can use it in href="<{$url|escape}>" without double-escaping.
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('myUrl', 'search.php?q=a%26b');

        $result = $this->ext->generateUrl(
            ['route' => 'search.php', 'params' => ['q' => 'a&b'], 'assign' => 'myUrl'],
            $tpl,
        );
        $this->assertSame('', $result);
    }

    // ── Function: renderAlert ───────────────────────────────

    #[Test]
    public function renderAlertOutputsBootstrapAlert(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        $result = $this->ext->renderAlert(
            ['message' => 'Saved!', 'type' => 'success'],
            $tpl,
        );
        $this->assertStringContainsString('alert alert-success', $result);
        $this->assertStringContainsString('Saved!', $result);
    }

    #[Test]
    public function renderAlertReturnsEmptyForEmptyMessage(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        $this->assertSame('', $this->ext->renderAlert(['message' => ''], $tpl));
    }

    #[Test]
    public function renderAlertDismissibleIncludesCloseButton(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        $result = $this->ext->renderAlert(
            ['message' => 'Error!', 'type' => 'danger', 'dismissible' => true],
            $tpl,
        );
        $this->assertStringContainsString('alert-dismissible', $result);
        $this->assertStringContainsString('btn-close', $result);
    }

    // ── Function: renderBreadcrumbs ─────────────────────────

    #[Test]
    public function renderBreadcrumbsOutputsBootstrapNav(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        $result = $this->ext->renderBreadcrumbs(
            ['items' => ['/home' => 'Home', '' => 'Current']],
            $tpl,
        );
        $this->assertStringContainsString('breadcrumb', $result);
        $this->assertStringContainsString('Home', $result);
        $this->assertStringContainsString('aria-current="page"', $result);
    }

    #[Test]
    public function renderBreadcrumbsReturnsEmptyForNoItems(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        $this->assertSame('', $this->ext->renderBreadcrumbs(['items' => []], $tpl));
    }

    #[Test]
    public function renderBreadcrumbsAcceptsXoopsListShape(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        $result = $this->ext->renderBreadcrumbs(
            ['items' => [
                ['link' => '/home', 'title' => 'Home'],
                ['link' => '/news', 'title' => 'News'],
                ['link' => '', 'title' => 'Article'],
            ]],
            $tpl,
        );

        $this->assertStringContainsString('<a href="/home">Home</a>', $result);
        $this->assertStringContainsString('<a href="/news">News</a>', $result);
        // Last crumb (empty link) is the non-linked active page.
        $this->assertStringContainsString('aria-current="page">Article</li>', $result);
        $this->assertStringNotContainsString('href="">Article', $result);
    }

    #[Test]
    public function renderBreadcrumbsAcceptsUrlLabelAliasKeys(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        $result = $this->ext->renderBreadcrumbs(
            ['items' => [
                ['url' => '/home', 'label' => 'Home'],
                ['url' => '/here', 'label' => 'Here'],
            ]],
            $tpl,
        );

        $this->assertStringContainsString('<a href="/home">Home</a>', $result);
        $this->assertStringContainsString('aria-current="page">Here</li>', $result);
    }

    #[Test]
    public function renderBreadcrumbsHandlesMixedListWithoutWarning(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        // A null/scalar first element must not derail detection or cast an array to string.
        $result = $this->ext->renderBreadcrumbs(
            ['items' => [null, ['link' => '/x', 'title' => 'X']]],
            $tpl,
        );

        $this->assertStringContainsString('aria-current="page">X</li>', $result);
    }

    // ── Function: renderPagination ──────────────────────────

    #[Test]
    public function renderPaginationOutputsBootstrapPagination(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        $result = $this->ext->renderPagination(
            ['totalPages' => 5, 'currentPage' => 2, 'urlPattern' => '?page={page}'],
            $tpl,
        );
        $this->assertStringContainsString('pagination', $result);
        $this->assertStringContainsString('active', $result);
        $this->assertStringContainsString('aria-label="Previous"', $result);
        $this->assertStringContainsString('aria-label="Next"', $result);
    }

    #[Test]
    public function renderPaginationReturnsEmptyForSinglePage(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        $this->assertSame('', $this->ext->renderPagination(['totalPages' => 1], $tpl));
    }

    // ── Function: renderQrCode ──────────────────────────────

    #[Test]
    public function renderQrCodeOutputsImgTag(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        $result = $this->ext->renderQrCode(
            ['text' => 'https://xoops.org', 'size' => 200],
            $tpl,
        );
        $this->assertStringContainsString('<img src=', $result);
        $this->assertStringContainsString('200', $result);
        $this->assertStringContainsString('loading="lazy"', $result);
    }

    #[Test]
    public function renderQrCodeReturnsEmptyForEmptyText(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        $this->assertSame('', $this->ext->renderQrCode(['text' => ''], $tpl));
    }

    // ── Function: socialShare ───────────────────────────────

    #[Test]
    public function socialShareSinglePlatformReturnsLink(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        $result = $this->ext->socialShare(
            ['url' => 'https://xoops.org', 'title' => 'XOOPS', 'platform' => 'twitter'],
            $tpl,
        );
        $this->assertStringContainsString('twitter.com', $result);
    }

    #[Test]
    public function socialShareNoPlatformReturnsFullBar(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        $result = $this->ext->socialShare(
            ['url' => 'https://xoops.org', 'title' => 'XOOPS'],
            $tpl,
        );
        $this->assertStringContainsString('social-share', $result);
        $this->assertStringContainsString('Twitter', $result);
        $this->assertStringContainsString('Facebook', $result);
        $this->assertStringContainsString('LinkedIn', $result);
    }

    #[Test]
    public function socialShareReturnsEmptyForEmptyUrl(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        $this->assertSame('', $this->ext->socialShare(['url' => ''], $tpl));
    }

    // ── Function: generateCanonicalUrl ──────────────────────

    #[Test]
    public function generateCanonicalUrlReturnsEmptyWithoutXoopsUrl(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        $result = $this->ext->generateCanonicalUrl(['path' => 'news/'], $tpl);
        $this->assertSame('', $result);
    }
}
