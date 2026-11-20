<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Test\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xmf\I18n\Translation;
use Xoops\SmartyExtensions\Extension\TranslationExtension;
use Xoops\SmartyExtensions\Test\Stubs\TemplateStub;

#[CoversClass(TranslationExtension::class)]
final class TranslationExtensionTest extends TestCase
{
    private TranslationExtension $ext;

    public static function setUpBeforeClass(): void
    {
        \defined('_SE_TR_HELLO') || \define('_SE_TR_HELLO', 'Hello <b>{name}</b>');
        \defined('_SE_TR_PLAIN') || \define('_SE_TR_PLAIN', 'Plain');
        \defined('_SE_TR_NUMBER') || \define('_SE_TR_NUMBER', 42);
        \defined('SE_TR_SECRET') || \define('SE_TR_SECRET', 'not a language constant');
        \defined('_SE_TR_SAME') || \define('_SE_TR_SAME', '_SE_TR_SAME');
    }

    protected function setUp(): void
    {
        $this->ext = new TranslationExtension();
        Translation::reset();
        \XoopsPreload::$events = [];
        \XoopsPreload::$throw  = false;
        unset($GLOBALS['xoopsModule'], $GLOBALS['xoopsLogger']);
    }

    protected function tearDown(): void
    {
        Translation::reset();
        \XoopsPreload::$events = [];
        \XoopsPreload::$throw  = false;
        unset($GLOBALS['xoopsModule'], $GLOBALS['xoopsLogger']);
    }

    private function tpl(): object
    {
        return $this->createMock(TemplateStub::class);
    }

    /**
     * Run $fn and return the user-level PHP errors it raised.
     *
     * @return list<array{level: int, message: string}>
     */
    private function captureErrors(callable $fn): array
    {
        $errors = [];
        \set_error_handler(static function (int $level, string $message) use (&$errors): bool {
            $errors[] = ['level' => $level, 'message' => $message];

            return true;
        });
        try {
            $fn();
        } finally {
            \restore_error_handler();
        }

        return $errors;
    }

    private function throwingStringable(): \Stringable
    {
        return new class implements \Stringable {
            public function __toString(): string
            {
                throw new \RuntimeException('no string for you');
            }
        };
    }

    /** A Smarty 4 shaped object: public plugin table plus registerPlugin(). */
    private function smarty4(): object
    {
        return new class {
            /** @var array<string, array<string, mixed>> */
            public array $registered_plugins = [];

            /** @var list<array{type: string, name: string, cacheable: bool}> */
            public array $calls = [];

            public function registerPlugin(string $type, string $name, callable $callback, bool $cacheable = true): void
            {
                $this->registered_plugins[$type][$name] = [$callback, $cacheable];
                $this->calls[]                         = ['type' => $type, 'name' => $name, 'cacheable' => $cacheable];
            }
        };
    }

    // ──────────────────────────────────────────────
    // Registry
    // ──────────────────────────────────────────────

    #[Test]
    public function getFunctionsReturnsThreeAliases(): void
    {
        $this->assertSame(['xoTrans', 'trans', 'xoT'], \array_keys($this->ext->getFunctions()));
    }

    #[Test]
    public function getModifiersReturnsOneEntry(): void
    {
        $this->assertSame(['xoTrans'], \array_keys($this->ext->getModifiers()));
    }

    #[Test]
    public function aliasesAllResolveTheSameKey(): void
    {
        foreach ($this->ext->getFunctions() as $name => $callback) {
            $this->assertSame('Plain', $callback(['key' => '_SE_TR_PLAIN'], $this->tpl()), $name);
        }
    }

    // ──────────────────────────────────────────────
    // Key and attribute handling
    // ──────────────────────────────────────────────

    #[Test]
    public function emptyKeyReturnsEmptyString(): void
    {
        $this->assertSame('', $this->ext->xoTrans([], $this->tpl()));
        $this->assertSame('', $this->ext->xoTrans(['key' => ''], $this->tpl()));
    }

    #[Test]
    public function idIsAnAliasForKey(): void
    {
        $this->assertSame('Plain', $this->ext->xoTrans(['id' => '_SE_TR_PLAIN'], $this->tpl()));
    }

    #[Test]
    public function arrayOrPlainObjectKeyIsTreatedAsAbsent(): void
    {
        $this->assertSame('', $this->ext->xoTrans(['key' => ['_SE_TR_PLAIN']], $this->tpl()));
        $this->assertSame('', $this->ext->xoTrans(['key' => new \stdClass()], $this->tpl()));
    }

    #[Test]
    public function throwingStringableKeyIsTreatedAsAbsent(): void
    {
        $this->assertSame('', $this->ext->xoTrans(['key' => $this->throwingStringable()], $this->tpl()));
    }

    // ──────────────────────────────────────────────
    // Constant fallback (engine absent or not initialized)
    // ──────────────────────────────────────────────

    #[Test]
    public function fallbackReturnsTheCatalogStringRawAndEscapesPlaceholderValues(): void
    {
        $out = $this->ext->xoTrans(['key' => '_SE_TR_HELLO', 'name' => '<i>"A" & B</i>'], $this->tpl());

        $this->assertSame('Hello <b>&lt;i&gt;&quot;A&quot; &amp; B&lt;/i&gt;</b>', $out);
        $this->assertSame([], Translation::$calls);
    }

    #[Test]
    public function fallbackLeavesUnmatchedPlaceholdersInPlace(): void
    {
        $this->assertSame('Hello <b>{name}</b>', $this->ext->xoTrans(['key' => '_SE_TR_HELLO'], $this->tpl()));
    }

    #[Test]
    public function fallbackTakesValuesFromTheParamsArrayAndAdHocAttributesWin(): void
    {
        $out = $this->ext->xoTrans(
            ['key' => '_SE_TR_HELLO', 'params' => ['name' => 'array'], 'name' => 'attribute'],
            $this->tpl(),
        );

        $this->assertSame('Hello <b>attribute</b>', $out);
        $this->assertSame(
            'Hello <b>array</b>',
            $this->ext->xoTrans(['key' => '_SE_TR_HELLO', 'params' => ['name' => 'array']], $this->tpl()),
        );
    }

    #[Test]
    public function fallbackReplacesNonStringableValuesWithEmptyString(): void
    {
        $out = $this->ext->xoTrans(['key' => '_SE_TR_HELLO', 'name' => new \stdClass()], $this->tpl());

        $this->assertSame('Hello <b></b>', $out);
    }

    #[Test]
    public function fallbackLeavesTheTokenWhenAValueCannotBeConvertedToString(): void
    {
        $out = $this->ext->xoTrans(['key' => '_SE_TR_HELLO', 'name' => $this->throwingStringable()], $this->tpl());

        $this->assertSame('Hello <b>{name}</b>', $out);
    }

    #[Test]
    public function aPlaceholderInsideAValueIsNotReplacedAgain(): void
    {
        \defined('_SE_TR_TWO') || \define('_SE_TR_TWO', '{name} is {role}');

        $out = $this->ext->xoTrans(['key' => '_SE_TR_TWO', 'name' => '{role}', 'role' => 'Admin'], $this->tpl());

        $this->assertSame('{role} is Admin', $out);
    }

    #[Test]
    public function aValueThatFailsToConvertOnceIsNeverInsertedUnescaped(): void
    {
        $flaky = new class implements \Stringable {
            private int $calls = 0;

            public function __toString(): string
            {
                if (1 === ++$this->calls) {
                    throw new \RuntimeException('first conversion fails');
                }

                return '<script>';
            }
        };

        $out = $this->ext->xoTrans(['key' => '_SE_TR_HELLO', 'name' => $flaky], $this->tpl());

        $this->assertSame('Hello <b>{name}</b>', $out);
    }

    #[Test]
    public function nestedArrayValuesAreEscapedAtEveryDepth(): void
    {
        Translation::$initialized = true;

        $this->ext->xoTrans(['key' => 'k', 'params' => ['a' => ['b' => ['<c>']]]], $this->tpl());

        $this->assertSame(['a' => ['b' => ['&lt;c&gt;']]], Translation::$calls[0]['params']);
    }

    #[Test]
    public function placeholdersAreNotAppliedToAKeyThatDoesNotResolve(): void
    {
        $ext = new TranslationExtension(true);

        $out = $ext->xoTrans(['key' => 'missing.{name}', 'name' => 'title', 'default' => 'Fallback'], $this->tpl());

        $this->assertSame('Fallback', $out);
        $this->assertSame('missing.{name}', $ext->xoTrans(['key' => 'missing.{name}', 'name' => 'title'], $this->tpl()));
        $this->assertCount(2, \XoopsPreload::$events);
    }

    #[Test]
    public function assignFallsBackToPrintingWhenTheTemplateCannotAssign(): void
    {
        $out = $this->ext->xoTrans(['key' => '_SE_TR_PLAIN', 'assign' => 'title'], new \stdClass());

        $this->assertSame('Plain', $out);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unresolvableKeys(): array
    {
        return [
            'no leading underscore'  => ['SE_TR_SECRET'],
            'PHP constant'           => ['PHP_VERSION'],
            'lower case'             => ['_se_tr_plain'],
            'trailing newline'       => ["_SE_TR_PLAIN\n"],
            'undefined'              => ['_SE_TR_UNDEFINED'],
            'non-string constant'    => ['_SE_TR_NUMBER'],
            'class constant syntax'  => ['_SE::TR'],
        ];
    }

    #[Test]
    #[DataProvider('unresolvableKeys')]
    public function fallbackResolvesOnlyDefinedStringLanguageConstants(string $key): void
    {
        $this->assertSame($key, $this->ext->xoTrans(['key' => $key], $this->tpl()));
    }

    // ──────────────────────────────────────────────
    // escape=
    // ──────────────────────────────────────────────

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function escapeValues(): array
    {
        return [
            'false'           => [false, '<i>'],
            'string false'    => ['false', '<i>'],
            'string off'      => ['off', '<i>'],
            'zero'            => [0, '<i>'],
            'true'            => [true, '&lt;i&gt;'],
            'string on'       => ['on', '&lt;i&gt;'],
            'unrecognized'    => ['banana', '&lt;i&gt;'],
            'null'            => [null, '&lt;i&gt;'],
            'array'           => [['no'], '&lt;i&gt;'],
        ];
    }

    #[Test]
    #[DataProvider('escapeValues')]
    public function onlyARecognizedFalseDisablesEscaping(mixed $escape, string $expected): void
    {
        $out = $this->ext->xoTrans(['key' => '_SE_TR_HELLO', 'name' => '<i>', 'escape' => $escape], $this->tpl());

        $this->assertSame('Hello <b>' . $expected . '</b>', $out);
    }

    #[Test]
    public function anEscapeValueThatCannotBeConvertedKeepsEscapingOn(): void
    {
        $out = $this->ext->xoTrans(
            ['key' => '_SE_TR_HELLO', 'name' => '<i>', 'escape' => $this->throwingStringable()],
            $this->tpl(),
        );

        $this->assertSame('Hello <b>&lt;i&gt;</b>', $out);
    }

    // ──────────────────────────────────────────────
    // default= and assign=
    // ──────────────────────────────────────────────

    #[Test]
    public function defaultIsUsedOnlyWhenTheKeyDoesNotResolve(): void
    {
        $this->assertSame(
            'News',
            $this->ext->xoTrans(['key' => '_SE_TR_UNDEFINED', 'default' => 'News'], $this->tpl()),
        );
        $this->assertSame(
            'Plain',
            $this->ext->xoTrans(['key' => '_SE_TR_PLAIN', 'default' => 'News'], $this->tpl()),
        );
    }

    #[Test]
    public function assignStoresTheResultAndPrintsNothing(): void
    {
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('title', 'Hello <b>&lt;i&gt;</b>');

        $out = $this->ext->xoTrans(['key' => '_SE_TR_HELLO', 'name' => '<i>', 'assign' => 'title'], $tpl);

        $this->assertSame('', $out);
    }

    // ──────────────────────────────────────────────
    // Engine path (Xmf\I18n\Translation initialized)
    // ──────────────────────────────────────────────

    #[Test]
    public function engineReceivesDirnameDomainCountAndEscapedValues(): void
    {
        Translation::$initialized = true;
        Translation::$catalog     = ['news.by' => 'By <em>{name}</em>'];

        $out = $this->ext->xoTrans(
            [
                'key'     => 'news.by',
                'dirname' => 'news',
                'domain'  => 'admin',
                'count'   => '3',
                'name'    => '<i>',
                'params'  => ['list' => ['<a>', 5]],
                'locale'  => 'fr_FR',
                'context' => 'menu',
                'default' => 'unused',
                'escape'  => true,
            ],
            $this->tpl(),
        );

        $this->assertSame('By <em>&lt;i&gt;</em>', $out);
        $this->assertSame(
            [[
                'dirname' => 'news',
                'key'     => 'news.by',
                'params'  => ['list' => ['&lt;a&gt;', '5'], 'name' => '&lt;i&gt;'],
                'domain'  => 'admin',
                'count'   => 3,
            ]],
            Translation::$calls,
        );
    }

    #[Test]
    public function engineDefaultsAreMainDomainNoCountAndNoDirname(): void
    {
        Translation::$initialized = true;

        $this->ext->xoTrans(['key' => 'k', 'count' => 'many'], $this->tpl());

        $this->assertSame(
            [['dirname' => '', 'key' => 'k', 'params' => [], 'domain' => 'main', 'count' => null]],
            Translation::$calls,
        );
    }

    #[Test]
    public function engineMissWithAPlaceholderInTheKeyStillGetsTheDefault(): void
    {
        Translation::$initialized = true;

        $out = $this->ext->xoTrans(['key' => 'missing.{name}', 'name' => 'title', 'default' => 'Fallback'], $this->tpl());

        $this->assertSame('Fallback', $out);
    }

    #[Test]
    public function dirnameComesFromTheCurrentModuleUnlessGiven(): void
    {
        Translation::$initialized = true;
        $GLOBALS['xoopsModule']   = new \XoopsModule(['dirname' => 'publisher']);

        $this->ext->xoTrans(['key' => 'k'], $this->tpl());
        $this->ext->xoTrans(['key' => 'k', 'dirname' => 'news'], $this->tpl());

        $this->assertSame(['publisher', 'news'], \array_column(Translation::$calls, 'dirname'));
    }

    #[Test]
    public function fileIsADeprecatedAliasForDomain(): void
    {
        Translation::$initialized = true;

        $errors = $this->captureErrors(function (): void {
            $this->ext->xoTrans(['key' => 'k', 'file' => 'blocks'], $this->tpl());
            $this->ext->xoTrans(['key' => 'k', 'file' => 'blocks', 'domain' => 'admin'], $this->tpl());
        });

        $this->assertSame(['blocks', 'admin'], \array_column(Translation::$calls, 'domain'));
        $this->assertSame([], $errors, 'no notice outside debug mode');
    }

    #[Test]
    public function fileRaisesADeprecationInDebugModeOnlyWithoutDomain(): void
    {
        $ext = new TranslationExtension(true);

        $errors = $this->captureErrors(function () use ($ext): void {
            $ext->xoTrans(['key' => '_SE_TR_PLAIN', 'file' => 'blocks'], $this->tpl());
            $ext->xoTrans(['key' => '_SE_TR_PLAIN', 'file' => 'blocks', 'domain' => 'admin'], $this->tpl());
        });

        $this->assertCount(1, $errors);
        $this->assertSame(\E_USER_DEPRECATED, $errors[0]['level']);
        $this->assertStringContainsString('domain=', $errors[0]['message']);
    }

    // ──────────────────────────────────────────────
    // Debug mode: missing-key event
    // ──────────────────────────────────────────────

    #[Test]
    public function debugModeReportsAMissingKey(): void
    {
        Translation::$initialized = true;
        Translation::$tag         = 'fr_FR';
        $ext                      = new TranslationExtension(true);

        $out = $ext->xoTrans(['key' => 'news.gone', 'dirname' => 'news', 'domain' => 'admin'], $this->tpl());

        $this->assertSame('news.gone', $out);
        $this->assertSame(
            [[
                'name' => 'core.i18n.missing',
                'args' => [['dirname' => 'news', 'domain' => 'admin', 'key' => 'news.gone', 'locale' => 'fr_FR']],
            ]],
            \XoopsPreload::$events,
        );
    }

    #[Test]
    public function debugModeReportsAMissingKeyWithEmptyLocaleWhenTheEngineIsNotInitialized(): void
    {
        $ext = new TranslationExtension(true);

        $ext->xoTrans(['key' => '_SE_TR_UNDEFINED', 'default' => 'News'], $this->tpl());

        $this->assertCount(1, \XoopsPreload::$events);
        $this->assertSame('', \XoopsPreload::$events[0]['args'][0]['locale']);
    }

    #[Test]
    public function noEventForAResolvedKeyOrOutsideDebugMode(): void
    {
        (new TranslationExtension(true))->xoTrans(['key' => '_SE_TR_PLAIN'], $this->tpl());
        $this->ext->xoTrans(['key' => '_SE_TR_UNDEFINED'], $this->tpl());

        $this->assertSame([], \XoopsPreload::$events);
    }

    #[Test]
    public function aFailingListenerDoesNotBreakRendering(): void
    {
        \XoopsPreload::$throw = true;

        $out = (new TranslationExtension(true))->xoTrans(['key' => '_SE_TR_UNDEFINED'], $this->tpl());

        $this->assertSame('_SE_TR_UNDEFINED', $out);
    }

    #[Test]
    public function aTranslationEqualToItsKeyIsNotAMiss(): void
    {
        $ext = new TranslationExtension(true);

        $this->assertSame('_SE_TR_SAME', $ext->xoTrans(['key' => '_SE_TR_SAME', 'default' => 'News'], $this->tpl()));

        Translation::$initialized = true;
        Translation::$catalog     = ['Save' => 'Save'];

        $this->assertSame('Save', $ext->xoTrans(['key' => 'Save', 'default' => 'News'], $this->tpl()));
        $this->assertSame('Save', $ext->xoTransModifier('Save'));
        $this->assertSame([], \XoopsPreload::$events);
    }

    #[Test]
    public function aKeyTheEngineCannotExplainIsAMiss(): void
    {
        Translation::$initialized   = true;
        Translation::$explainThrows = true;
        Translation::$catalog       = ['Save' => 'Save'];

        $this->assertSame('News', $this->ext->xoTrans(['key' => 'Save', 'default' => 'News'], $this->tpl()));
    }

    #[Test]
    public function aKeyThatDoesNotResolveIsEscaped(): void
    {
        $key = '<b>"a"</b>';
        $esc = '&lt;b&gt;&quot;a&quot;&lt;/b&gt;';

        $this->assertSame($esc, $this->ext->xoTrans(['key' => $key], $this->tpl()));
        $this->assertSame($esc, $this->ext->xoTrans(['key' => $key, 'escape' => false], $this->tpl()));
        $this->assertSame($esc, $this->ext->xoTransModifier($key));

        Translation::$initialized = true;

        $this->assertSame($esc, $this->ext->xoTrans(['key' => $key], $this->tpl()));
        $this->assertSame($esc, $this->ext->xoTransModifier($key));
        // The engine is asked for the key as the template wrote it.
        $this->assertSame([$key, $key], \array_column(Translation::$calls, 'key'));
    }

    #[Test]
    public function defaultIsEscapedUnlessEscapeIsFalse(): void
    {
        $params = ['key' => '_SE_TR_UNDEFINED', 'default' => '<em>R&D</em>'];

        $this->assertSame('&lt;em&gt;R&amp;D&lt;/em&gt;', $this->ext->xoTrans($params, $this->tpl()));
        $this->assertSame('<em>R&D</em>', $this->ext->xoTrans($params + ['escape' => false], $this->tpl()));
        $this->assertSame(
            '&lt;em&gt;R&amp;D&lt;/em&gt;',
            $this->ext->xoTrans($params + ['escape' => 'not-a-boolean'], $this->tpl()),
        );
    }

    // ──────────────────────────────────────────────
    // Modifier
    // ──────────────────────────────────────────────

    #[Test]
    public function modifierFallsBackToTheLanguageConstant(): void
    {
        $this->assertSame('Hello <b>{name}</b>', $this->ext->xoTransModifier('_SE_TR_HELLO'));
        $this->assertSame('SE_TR_SECRET', $this->ext->xoTransModifier('SE_TR_SECRET'));
    }

    #[Test]
    public function modifierUsesTheEngineWhenInitialized(): void
    {
        Translation::$initialized = true;
        Translation::$catalog     = ['k' => 'Translated'];

        $this->assertSame('Translated', $this->ext->xoTransModifier('k', 'news', 'admin'));
        $this->assertSame(
            [['dirname' => 'news', 'key' => 'k', 'params' => [], 'domain' => 'admin', 'count' => null]],
            Translation::$calls,
        );
    }

    #[Test]
    public function modifierDirnameComesFromTheCurrentModuleUnlessGiven(): void
    {
        Translation::$initialized = true;
        $GLOBALS['xoopsModule']   = new \XoopsModule(['dirname' => 'publisher']);

        $this->ext->xoTransModifier('k');
        $this->ext->xoTransModifier('k', 'news');

        $this->assertSame(['publisher', 'news'], \array_column(Translation::$calls, 'dirname'));
    }

    #[Test]
    public function modifierReportsAMissingKeyInDebugModeOnly(): void
    {
        Translation::$initialized = true;
        Translation::$tag         = 'fr_FR';

        $this->ext->xoTransModifier('news.gone', 'news', 'admin');
        $this->assertSame([], \XoopsPreload::$events);

        (new TranslationExtension(true))->xoTransModifier('news.gone', 'news', 'admin');
        $this->assertSame(
            [[
                'name' => 'core.i18n.missing',
                'args' => [['dirname' => 'news', 'domain' => 'admin', 'key' => 'news.gone', 'locale' => 'fr_FR']],
            ]],
            \XoopsPreload::$events,
        );
    }

    // ──────────────────────────────────────────────
    // Smarty 4 registration
    // ──────────────────────────────────────────────

    #[Test]
    public function registerAddsFunctionsNonCacheableAndTheModifier(): void
    {
        $smarty = $this->smarty4();

        $this->ext->register($smarty);

        $this->assertSame(
            [
                ['type' => 'function', 'name' => 'xoTrans', 'cacheable' => false],
                ['type' => 'function', 'name' => 'trans', 'cacheable' => false],
                ['type' => 'function', 'name' => 'xoT', 'cacheable' => false],
                ['type' => 'modifier', 'name' => 'xoTrans', 'cacheable' => true],
            ],
            $smarty->calls,
        );
    }

    #[Test]
    public function registerKeepsAnExistingPluginAndLogsTheCollision(): void
    {
        $smarty                                       = $this->smarty4();
        $smarty->registered_plugins['function']['trans'] = ['theirs', true];
        $logger                                       = new class {
            /** @var list<array{string, string}> */
            public array $extra = [];

            public function addExtra(string $name, string $message): void
            {
                $this->extra[] = [$name, $message];
            }
        };
        $GLOBALS['xoopsLogger'] = $logger;

        $this->ext->register($smarty);

        $this->assertSame(['theirs', true], $smarty->registered_plugins['function']['trans']);
        $this->assertSame(['xoTrans', 'xoT', 'xoTrans'], \array_column($smarty->calls, 'name'));
        $this->assertCount(1, $logger->extra);
        $this->assertSame('i18n', $logger->extra[0][0]);
        $this->assertStringContainsString("'trans'", $logger->extra[0][1]);
    }

    #[Test]
    public function registerRaisesANoticeForACollisionWithoutALogger(): void
    {
        $smarty                                         = $this->smarty4();
        $smarty->registered_plugins['modifier']['xoTrans'] = ['theirs', true];

        $errors = $this->captureErrors(fn () => $this->ext->register($smarty));

        $this->assertCount(1, $errors);
        $this->assertSame(\E_USER_NOTICE, $errors[0]['level']);
        $this->assertStringContainsString('modifier', $errors[0]['message']);
    }

    #[Test]
    public function registerContinuesWhenTheCollisionNoticeBecomesAnException(): void
    {
        $smarty                                        = $this->smarty4();
        $smarty->registered_plugins['function']['trans'] = ['theirs', true];

        \set_error_handler(static function (int $level, string $message): never {
            throw new \ErrorException($message, 0, $level);
        });
        try {
            $this->ext->register($smarty);
        } finally {
            \restore_error_handler();
        }

        $this->assertSame(['xoTrans', 'xoT', 'xoTrans'], \array_column($smarty->calls, 'name'));
    }

    // ──────────────────────────────────────────────
    // Smarty 5 collision probe
    // ──────────────────────────────────────────────

    #[Test]
    public function probeCollisionsListsNamesSmartyAlreadyResolves(): void
    {
        $smarty = new class {
            public function getFunctionHandler(string $name): ?object
            {
                return 'trans' === $name ? new \stdClass() : null;
            }

            public function getModifierCallback(string $name): ?callable
            {
                return 'xoTrans' === $name ? 'strtoupper' : null;
            }
        };

        $this->assertSame(['function:trans', 'modifier:xoTrans'], TranslationExtension::probeCollisions($smarty));
    }

    #[Test]
    public function probeCollisionsIsEmptyForACleanOrUnknownEngine(): void
    {
        $clean = new class {
            public function getFunctionHandler(string $name): ?object
            {
                return null;
            }

            public function getModifierCallback(string $name): ?callable
            {
                return null;
            }
        };

        $this->assertSame([], TranslationExtension::probeCollisions($clean));
        $this->assertSame([], TranslationExtension::probeCollisions(new \stdClass()));
    }
}
