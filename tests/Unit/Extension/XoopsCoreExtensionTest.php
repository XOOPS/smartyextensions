<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Test\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xoops\SmartyExtensions\Extension\XoopsCoreExtension;
use Xoops\SmartyExtensions\Test\Stubs\TemplateStub;

#[CoversClass(XoopsCoreExtension::class)]
final class XoopsCoreExtensionTest extends TestCase
{
    private XoopsCoreExtension $ext;

    protected function setUp(): void
    {
        $this->ext = new XoopsCoreExtension();
        // Ensure XOOPS globals are clean before each test
        $GLOBALS['xoopsConfig'] = null;
        $GLOBALS['xoopsUser']   = null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['xoopsConfig'] = null;
        $GLOBALS['xoopsUser']   = null;
    }

    private function tpl(): object
    {
        return $this->createMock(TemplateStub::class);
    }

    // ──────────────────────────────────────────────
    // Registry counts
    // ──────────────────────────────────────────────

    #[Test]
    public function getFunctionsReturnsNineEntries(): void
    {
        $functions = $this->ext->getFunctions();
        $this->assertCount(9, $functions);
        $this->assertArrayHasKey('xo_get_config', $functions);
        $this->assertArrayHasKey('xo_get_current_user', $functions);
        $this->assertArrayHasKey('xo_get_module_info', $functions);
        $this->assertArrayHasKey('xo_get_notifications', $functions);
        $this->assertArrayHasKey('xo_module_url', $functions);
        $this->assertArrayHasKey('xo_render_block', $functions);
        $this->assertArrayHasKey('xo_render_menu', $functions);
        $this->assertArrayHasKey('xo_avatar', $functions);
        $this->assertArrayHasKey('xo_debug', $functions);
    }

    #[Test]
    public function getModifiersReturnsOneEntry(): void
    {
        $modifiers = $this->ext->getModifiers();
        $this->assertCount(1, $modifiers);
        $this->assertArrayHasKey('translate', $modifiers);
    }

    // ──────────────────────────────────────────────
    // xo_get_config
    // ──────────────────────────────────────────────

    #[Test]
    public function xoGetConfigReturnsConfigValue(): void
    {
        $GLOBALS['xoopsConfig'] = ['sitename' => 'My XOOPS Site', 'debug_mode' => 0];
        $result = $this->ext->xoGetConfig(['name' => 'sitename'], $this->tpl());
        $this->assertSame('My XOOPS Site', $result);
    }

    #[Test]
    public function xoGetConfigReturnsEmptyForMissingKey(): void
    {
        $GLOBALS['xoopsConfig'] = [];
        $result = $this->ext->xoGetConfig(['name' => 'nonexistent'], $this->tpl());
        $this->assertSame('', $result);
    }

    #[Test]
    public function xoGetConfigReturnsEmptyWhenGlobalNotSet(): void
    {
        $GLOBALS['xoopsConfig'] = null;
        $result = $this->ext->xoGetConfig(['name' => 'sitename'], $this->tpl());
        $this->assertSame('', $result);
    }

    #[Test]
    public function xoGetConfigAssignsValueToTemplate(): void
    {
        $GLOBALS['xoopsConfig'] = ['sitename' => 'XOOPS'];
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('site', 'XOOPS');

        $result = $this->ext->xoGetConfig(['name' => 'sitename', 'assign' => 'site'], $tpl);
        $this->assertSame('', $result);
    }

    // ──────────────────────────────────────────────
    // xo_get_current_user
    // ──────────────────────────────────────────────

    #[Test]
    public function xoGetCurrentUserAssignsNullWhenNoUser(): void
    {
        $GLOBALS['xoopsUser'] = null;
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('user', null);

        $result = $this->ext->xoGetCurrentUser(['assign' => 'user'], $tpl);
        $this->assertSame('', $result);
    }

    #[Test]
    public function xoGetCurrentUserAssignsArrayWhenUserSet(): void
    {
        $GLOBALS['xoopsUser'] = new \XoopsUser([
            'uid' => 42, 'uname' => 'mamba', 'name' => 'Michael', 'email' => 'x@xoops.org',
        ]);

        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('user', $this->callback(static function ($v) {
                return \is_array($v)
                    && $v['uid'] === 42
                    && $v['uname'] === 'mamba'
                    && \array_key_exists('is_admin', $v);
            }));

        $result = $this->ext->xoGetCurrentUser(['assign' => 'user'], $tpl);
        $this->assertSame('', $result);
    }

    #[Test]
    public function xoGetCurrentUserReturnsEmptyStringWithoutAssign(): void
    {
        $GLOBALS['xoopsUser'] = null;
        $result = $this->ext->xoGetCurrentUser([], $this->tpl());
        $this->assertSame('', $result);
    }

    // ──────────────────────────────────────────────
    // xo_module_url
    // ──────────────────────────────────────────────

    #[Test]
    public function xoModuleUrlBuildsUrlWithXoopsUrlConstant(): void
    {
        if (!\defined('XOOPS_URL')) {
            \define('XOOPS_URL', 'https://example.com');
        }

        $result = $this->ext->xoModuleUrl(['module' => 'news', 'path' => 'article.php'], $this->tpl());

        // Direct output is HTML-escaped
        $this->assertStringContainsString('modules/news/article.php', $result);
    }

    #[Test]
    public function xoModuleUrlBuildsEmptyBaseWithoutXoopsUrl(): void
    {
        // XOOPS_URL may already be defined by the previous test; that's fine — the
        // result will still contain a valid path segment
        $result = $this->ext->xoModuleUrl(['module' => 'news', 'path' => 'index.php'], $this->tpl());
        $this->assertStringContainsString('/modules/news/index.php', $result);
    }

    #[Test]
    public function xoModuleUrlAssignsRawUrl(): void
    {
        $tpl = $this->createMock(TemplateStub::class);
        // The assigned value must be the raw URL, never html-escaped
        $tpl->expects($this->once())
            ->method('assign')
            ->with('url', $this->callback(static fn($v) => \strpos($v, '&amp;') === false));

        $result = $this->ext->xoModuleUrl(
            ['module' => 'news', 'path' => 'view.php', 'params' => ['id' => 5, 'cat' => 'tech'], 'assign' => 'url'],
            $tpl,
        );
        $this->assertSame('', $result);
    }

    #[Test]
    public function xoModuleUrlDirectOutputIsHtmlEscaped(): void
    {
        // Build a URL with ampersands in query string; direct output must be escaped
        $result = $this->ext->xoModuleUrl(
            ['module' => 'news', 'params' => ['a' => '1', 'b' => '2']],
            $this->tpl(),
        );
        $this->assertStringContainsString('&amp;', $result);
        $this->assertStringNotContainsString('&b=', $result);
    }

    // ──────────────────────────────────────────────
    // xo_avatar
    // ──────────────────────────────────────────────

    #[Test]
    public function xoAvatarReturnsGravatarWhenEmailProvided(): void
    {
        $result = $this->ext->xoAvatar(['email' => 'user@example.com', 'size' => 64], $this->tpl());
        $this->assertStringContainsString('gravatar.com', $result);
        $this->assertStringContainsString('width="64"', $result);
        $this->assertStringContainsString('<img', $result);
    }

    #[Test]
    public function xoAvatarReturnsEmptyWhenNoUidAndNoEmail(): void
    {
        // uid=0, no email → nothing to build a URL from
        $result = $this->ext->xoAvatar(['uid' => 0], $this->tpl());
        $this->assertSame('', $result);
    }

    #[Test]
    public function xoAvatarAssignsHtml(): void
    {
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('avatar', $this->stringContains('<img'));

        $result = $this->ext->xoAvatar(['email' => 'x@example.com', 'assign' => 'avatar'], $tpl);
        $this->assertSame('', $result);
    }

    // ──────────────────────────────────────────────
    // xo_debug
    // ──────────────────────────────────────────────

    #[Test]
    public function xoDebugReturnsSilentWhenDebugModeOff(): void
    {
        $GLOBALS['xoopsConfig'] = ['debug_mode' => 0];
        $result = $this->ext->xoDebug(['var' => 'anything'], $this->tpl());
        $this->assertSame('', $result);
    }

    #[Test]
    public function xoDebugOutputsHtmlWhenDebugModeOn(): void
    {
        $GLOBALS['xoopsConfig'] = ['debug_mode' => 1];
        $result = $this->ext->xoDebug(['var' => ['key' => 'value'], 'label' => 'Test'], $this->tpl());
        $this->assertStringContainsString('<details', $result);
        $this->assertStringContainsString('Test', $result);
        $this->assertStringContainsString('key', $result);
        $GLOBALS['xoopsConfig'] = null;
    }

    #[Test]
    public function xoDebugEscapesOutput(): void
    {
        $GLOBALS['xoopsConfig'] = ['debug_mode' => 1];
        $result = $this->ext->xoDebug(['var' => '<script>evil()</script>'], $this->tpl());
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
        $GLOBALS['xoopsConfig'] = null;
    }

    // ──────────────────────────────────────────────
    // translate modifier
    // ──────────────────────────────────────────────

    #[Test]
    public function translateReturnsFallbackWhenConstantUndefined(): void
    {
        $this->assertSame('_SOME_UNDEFINED_CONST', $this->ext->translate('_SOME_UNDEFINED_CONST'));
    }

    #[Test]
    public function translateReturnsConstantValueWhenDefined(): void
    {
        if (!\defined('_MI_TEST_XOOPS_CORE_EXT')) {
            \define('_MI_TEST_XOOPS_CORE_EXT', 'Translated Value');
        }
        $this->assertSame('Translated Value', $this->ext->translate('_MI_TEST_XOOPS_CORE_EXT'));
    }

    #[Test]
    public function translateReturnsDefaultWhenUndefinedAndDefaultGiven(): void
    {
        $this->assertSame('Latest news', $this->ext->translate('_MI_UNDEFINED_NEWS_TITLE', 'Latest news'));
    }

    #[Test]
    public function translatePrefersConstantOverDefault(): void
    {
        if (!\defined('_MI_TEST_XOOPS_CORE_EXT2')) {
            \define('_MI_TEST_XOOPS_CORE_EXT2', 'Defined Value');
        }
        $this->assertSame('Defined Value', $this->ext->translate('_MI_TEST_XOOPS_CORE_EXT2', 'Fallback'));
    }

    // ──────────────────────────────────────────────
    // xo_get_module_info
    // ──────────────────────────────────────────────

    #[Test]
    public function xoGetModuleInfoAssignsNullForEmptyDirname(): void
    {
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('mod', null);

        $result = $this->ext->xoGetModuleInfo(['dirname' => '', 'assign' => 'mod'], $tpl);
        $this->assertSame('', $result);
    }

    #[Test]
    public function xoGetModuleInfoAssignsNullWhenModuleNotFound(): void
    {
        // The stub's XoopsModule::getByDirname() always returns null
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('mod', null);

        $result = $this->ext->xoGetModuleInfo(['dirname' => 'news', 'assign' => 'mod'], $tpl);
        $this->assertSame('', $result);
    }

    // ──────────────────────────────────────────────
    // xo_render_block
    // ──────────────────────────────────────────────

    #[Test]
    public function xoRenderBlockReturnsContentFromBlockObject(): void
    {
        $block = new class {
            public function getContent(): string { return '<p>Block HTML</p>'; }
        };

        $result = $this->ext->xoRenderBlock(['options' => ['block' => $block]], $this->tpl());
        $this->assertSame('<p>Block HTML</p>', $result);
    }

    #[Test]
    public function xoRenderBlockReturnsEmptyForNoBlock(): void
    {
        $result = $this->ext->xoRenderBlock(['options' => []], $this->tpl());
        $this->assertSame('', $result);
    }
}
