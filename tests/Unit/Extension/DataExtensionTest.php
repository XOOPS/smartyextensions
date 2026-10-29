<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Test\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xoops\SmartyExtensions\Extension\DataExtension;
use Xoops\SmartyExtensions\Test\Stubs\TemplateStub;

#[CoversClass(DataExtension::class)]
final class DataExtensionTest extends TestCase
{
    private DataExtension $ext;

    protected function setUp(): void
    {
        $this->ext = new DataExtension();
    }

    // ---------------------------------------------------------------
    //  Registry counts
    // ---------------------------------------------------------------

    #[Test]
    public function getFunctionsReturnsSevenEntries(): void
    {
        $functions = $this->ext->getFunctions();
        $this->assertCount(7, $functions);
        $this->assertArrayHasKey('array_to_csv', $functions);
        $this->assertArrayHasKey('base64_encode_file', $functions);
        $this->assertArrayHasKey('embed_pdf', $functions);
        $this->assertArrayHasKey('generate_xml_sitemap', $functions);
        $this->assertArrayHasKey('generate_meta_tags', $functions);
        $this->assertArrayHasKey('get_referrer', $functions);
        $this->assertArrayHasKey('get_session_data', $functions);
    }

    #[Test]
    public function getModifiersReturnsSevenEntries(): void
    {
        $modifiers = $this->ext->getModifiers();
        $this->assertCount(7, $modifiers);
        $this->assertArrayHasKey('array_filter', $modifiers);
        $this->assertArrayHasKey('array_sort', $modifiers);
        $this->assertArrayHasKey('pretty_print_json', $modifiers);
        $this->assertArrayHasKey('get_file_size', $modifiers);
        $this->assertArrayHasKey('get_mime_type', $modifiers);
        $this->assertArrayHasKey('is_image', $modifiers);
        $this->assertArrayHasKey('strip_html_comments', $modifiers);
    }

    // ---------------------------------------------------------------
    //  Modifier tests
    // ---------------------------------------------------------------

    #[Test]
    public function arrayFilterFiltersByKeyValue(): void
    {
        $items = [
            ['status' => 'active', 'name' => 'Alice'],
            ['status' => 'inactive', 'name' => 'Bob'],
            ['status' => 'active', 'name' => 'Carol'],
        ];

        $result = $this->ext->arrayFilter($items, 'status', 'active');

        $this->assertCount(2, $result);
        $this->assertSame('Alice', $result[0]['name']);
        $this->assertSame('Carol', $result[1]['name']);
    }

    #[Test]
    public function arrayFilterReturnsEmptyWhenNoMatch(): void
    {
        $items = [
            ['status' => 'inactive', 'name' => 'Bob'],
        ];

        $result = $this->ext->arrayFilter($items, 'status', 'active');

        $this->assertSame([], $result);
    }

    #[Test]
    public function arraySortSortsByKey(): void
    {
        $items = [
            ['name' => 'Charlie', 'age' => 30],
            ['name' => 'Alice', 'age' => 25],
            ['name' => 'Bob', 'age' => 28],
        ];

        $result = $this->ext->arraySort($items, 'name');

        $this->assertSame('Alice', $result[0]['name']);
        $this->assertSame('Bob', $result[1]['name']);
        $this->assertSame('Charlie', $result[2]['name']);
    }

    #[Test]
    public function arraySortSortsByKeyDescending(): void
    {
        $items = [
            ['name' => 'Alice'],
            ['name' => 'Charlie'],
            ['name' => 'Bob'],
        ];

        $result = $this->ext->arraySort($items, 'name', 'desc');

        $this->assertSame('Charlie', $result[0]['name']);
        $this->assertSame('Bob', $result[1]['name']);
        $this->assertSame('Alice', $result[2]['name']);
    }

    #[Test]
    public function arraySortSortsByValueWhenNoKey(): void
    {
        $items = ['cherry', 'apple', 'banana'];

        $result = $this->ext->arraySort($items);

        $this->assertSame('apple', \array_values($result)[0]);
        $this->assertSame('banana', \array_values($result)[1]);
        $this->assertSame('cherry', \array_values($result)[2]);
    }

    #[Test]
    public function prettyPrintJsonFormatsArray(): void
    {
        $data = ['name' => 'XOOPS', 'version' => 2];

        $result = $this->ext->prettyPrintJson($data);

        $this->assertStringContainsString('"name": "XOOPS"', $result);
        $this->assertStringContainsString('"version": 2', $result);
        $this->assertStringContainsString("\n", $result);
    }

    #[Test]
    public function prettyPrintJsonDecodesJsonString(): void
    {
        $json = '{"a":1}';

        $result = $this->ext->prettyPrintJson($json);

        $this->assertStringContainsString('"a": 1', $result);
        $this->assertStringContainsString("\n", $result);
    }

    #[Test]
    public function prettyPrintJsonReturnsEmptyObjectOnInvalidUtf8(): void
    {
        // \x80 is invalid UTF-8 — json_encode returns false
        $result = $this->ext->prettyPrintJson("\x80");

        $this->assertSame('{}', $result);
    }

    #[Test]
    public function isImageReturnsTrueForImageExtensions(): void
    {
        $this->assertTrue($this->ext->isImage('photo.png'));
        $this->assertTrue($this->ext->isImage('photo.jpg'));
        $this->assertTrue($this->ext->isImage('photo.jpeg'));
        $this->assertTrue($this->ext->isImage('photo.gif'));
        $this->assertTrue($this->ext->isImage('photo.webp'));
        $this->assertTrue($this->ext->isImage('photo.svg'));
        $this->assertTrue($this->ext->isImage('photo.avif'));
        $this->assertTrue($this->ext->isImage('photo.bmp'));
        $this->assertTrue($this->ext->isImage('photo.ico'));
    }

    #[Test]
    public function isImageReturnsFalseForNonImageExtensions(): void
    {
        $this->assertFalse($this->ext->isImage('readme.txt'));
        $this->assertFalse($this->ext->isImage('style.css'));
        $this->assertFalse($this->ext->isImage('script.php'));
        $this->assertFalse($this->ext->isImage('document.pdf'));
    }

    #[Test]
    public function stripHtmlCommentsRemovesComments(): void
    {
        $html = '<div><!-- hidden comment -->visible</div>';

        $result = $this->ext->stripHtmlComments($html);

        $this->assertSame('<div>visible</div>', $result);
    }

    #[Test]
    public function stripHtmlCommentsRemovesMultilineComments(): void
    {
        $html = "<p>before</p>\n<!--\nmultiline\ncomment\n-->\n<p>after</p>";

        $result = $this->ext->stripHtmlComments($html);

        $this->assertStringNotContainsString('<!--', $result);
        $this->assertStringContainsString('<p>before</p>', $result);
        $this->assertStringContainsString('<p>after</p>', $result);
    }

    // ---------------------------------------------------------------
    //  Function tests (assign pattern)
    // ---------------------------------------------------------------

    #[Test]
    public function arrayToCsvConvertsTwoDimensionalArray(): void
    {
        $tpl = $this->createMock(TemplateStub::class);

        $result = $this->ext->arrayToCsv(
            ['array' => [['Alice', 30], ['Bob', 25]]],
            $tpl,
        );

        $this->assertStringContainsString('Alice', $result);
        $this->assertStringContainsString('Bob', $result);
    }

    #[Test]
    public function arrayToCsvReturnsEmptyForEmptyArray(): void
    {
        $tpl = $this->createMock(TemplateStub::class);

        $result = $this->ext->arrayToCsv(['array' => []], $tpl);

        $this->assertSame('', $result);
    }

    #[Test]
    public function arrayToCsvAssignsToTemplateWhenAssignSet(): void
    {
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('csv_output', $this->isType('string'));

        $result = $this->ext->arrayToCsv(
            ['array' => [['Alice', 30]], 'assign' => 'csv_output'],
            $tpl,
        );

        $this->assertSame('', $result);
    }

    #[Test]
    public function embedPdfReturnsIframeHtml(): void
    {
        $tpl = $this->createMock(TemplateStub::class);

        $result = $this->ext->embedPdf(
            ['url' => 'uploads/doc.pdf', 'width' => '800', 'height' => '500'],
            $tpl,
        );

        $this->assertStringContainsString('<iframe', $result);
        $this->assertStringContainsString('uploads/doc.pdf', $result);
        $this->assertStringContainsString('width="800"', $result);
        $this->assertStringContainsString('height="500"', $result);
    }

    #[Test]
    public function embedPdfReturnsEmptyForMissingUrl(): void
    {
        $tpl = $this->createMock(TemplateStub::class);

        $result = $this->ext->embedPdf([], $tpl);

        $this->assertSame('', $result);
    }

    #[Test]
    public function generateXmlSitemapProducesValidXml(): void
    {
        $tpl = $this->createMock(TemplateStub::class);
        $pages = [
            ['url' => 'https://example.com/', 'priority' => '1.0', 'lastmod' => '2026-03-20', 'changefreq' => 'daily'],
        ];

        $result = $this->ext->generateXmlSitemap(['pages' => $pages], $tpl);

        $this->assertStringContainsString('<?xml version="1.0"', $result);
        $this->assertStringContainsString('<urlset', $result);
        $this->assertStringContainsString('<loc>https://example.com/</loc>', $result);
        $this->assertStringContainsString('<priority>1.0</priority>', $result);
        $this->assertStringContainsString('<lastmod>2026-03-20</lastmod>', $result);
        $this->assertStringContainsString('<changefreq>daily</changefreq>', $result);
    }

    #[Test]
    public function generateMetaTagsProducesMetaElements(): void
    {
        $tpl = $this->createMock(TemplateStub::class);
        $config = ['description' => 'My page', 'robots' => 'index,follow'];

        $result = $this->ext->generateMetaTags(['config' => $config], $tpl);

        $this->assertStringContainsString('<meta name="description" content="My page">', $result);
        $this->assertStringContainsString('<meta name="robots" content="index,follow">', $result);
    }

    #[Test]
    public function generateMetaTagsReturnsEmptyForEmptyConfig(): void
    {
        $tpl = $this->createMock(TemplateStub::class);

        $result = $this->ext->generateMetaTags(['config' => []], $tpl);

        $this->assertSame('', $result);
    }

    #[Test]
    public function embedPdfAssignsWhenAssignSet(): void
    {
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('pdf_html', $this->isType('string'));

        $result = $this->ext->embedPdf(
            ['url' => 'doc.pdf', 'assign' => 'pdf_html'],
            $tpl,
        );

        $this->assertSame('', $result);
    }

    // ---------------------------------------------------------------
    //  get_referrer: assign contract — raw value, not html-escaped
    // ---------------------------------------------------------------

    #[Test]
    public function getReferrerAssignsRawValue(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://example.com/search?q=a&b=c';
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('ref', 'https://example.com/search?q=a&b=c'); // raw, not html-escaped

        $result = $this->ext->getReferrer(['assign' => 'ref'], $tpl);

        $this->assertSame('', $result);
        unset($_SERVER['HTTP_REFERER']);
    }

    // ── Hardening regressions ───────────────────────

    #[Test]
    public function embedPdfRejectsExecutableSchemes(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        foreach (['javascript:alert(1)', 'JaVaScRiPt:alert(1)', ' javascript:alert(1)', 'data:text/html,x'] as $url) {
            $this->assertSame('', $this->ext->embedPdf(['url' => $url], $tpl), $url);
        }
        $this->assertStringContainsString('src="/docs/a.pdf"', $this->ext->embedPdf(['url' => '/docs/a.pdf'], $tpl));
    }

    #[Test]
    public function prettyPrintJsonEncodesMarkup(): void
    {
        $result = $this->ext->prettyPrintJson(['a' => '</pre><script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    #[Test]
    public function fileHelpersRejectNetworkPathsBeforeProbing(): void
    {
        $tpl = $this->createMock(\Xoops\SmartyExtensions\Test\Stubs\TemplateStub::class);
        foreach (['ftp://127.0.0.1/x', 'phar://x.phar/a', 'php://filter/resource=x', '\\\\host\\share\\x', '//host/x', "a\0b"] as $path) {
            $this->assertSame('', $this->ext->base64EncodeFile(['path' => $path], $tpl), $path);
            $this->assertSame('', $this->ext->getFileSize($path), $path);
            $this->assertSame('', $this->ext->getMimeType($path), $path);
        }
    }

    #[Test]
    public function fileHelpersStillReadLocalFiles(): void
    {
        $this->assertNotSame('', $this->ext->getFileSize(__FILE__));
        $this->assertNotSame('', $this->ext->getMimeType(__FILE__));
    }
}
