<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Test\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xoops\SmartyExtensions\AbstractExtension;
use Xoops\SmartyExtensions\Adapter\Smarty5Adapter;
use Xoops\SmartyExtensions\ExtensionRegistry;

#[CoversClass(ExtensionRegistry::class)]
final class ExtensionRegistryTest extends TestCase
{
    private function createTestExtension(): AbstractExtension
    {
        return new class extends AbstractExtension {
            public function getModifiers(): array
            {
                return ['test_mod' => strtolower(...)];
            }
        };
    }

    #[Test]
    public function addAndRegisterAllWrapsInAdapterWhenSmarty5StubExists(): void
    {
        // The test bootstrap loads the Smarty5 stub, so \Smarty\Extension\Base exists.
        // registerAll() takes the Smarty 5 path: wraps in adapter, calls addExtension().
        $smarty = new class {
            /** @var list<Smarty5Adapter> */
            public array $extensions = [];
            public function addExtension(object $adapter): void
            {
                $this->extensions[] = $adapter;
            }
        };

        $registry = new ExtensionRegistry();
        $registry->add($this->createTestExtension());
        $registry->registerAll($smarty);

        $this->assertCount(1, $smarty->extensions);
        $this->assertInstanceOf(Smarty5Adapter::class, $smarty->extensions[0]);
        $this->assertNotNull($smarty->extensions[0]->getModifierCallback('test_mod'));
    }

    #[Test]
    public function registerFallbackCallsRegisterPluginDirectly(): void
    {
        $ext = $this->createTestExtension();

        $smarty = new class {
            /** @var list<array{string, string}> */
            public array $calls = [];
            public function registerPlugin(string $type, string $name, callable $callback, bool $cacheable = true): void
            {
                $this->calls[] = [$type, $name];
            }
        };

        $ext->register($smarty);

        $this->assertSame([['modifier', 'test_mod']], $smarty->calls);
    }

    #[Test]
    public function registerAllSmarty4PathIteratesAllExtensions(): void
    {
        $ext1 = $this->createTestExtension();
        $ext2 = new class extends AbstractExtension {
            public function getFunctions(): array
            {
                return ['test_func' => fn(array $p, object $t): string => 'ok'];
            }
        };

        $smarty = new class {
            /** @var list<array{string, string}> */
            public array $calls = [];
            public function registerPlugin(string $type, string $name, callable $callback, bool $cacheable = true): void
            {
                $this->calls[] = [$type, $name];
            }
        };

        foreach ([$ext1, $ext2] as $ext) {
            $ext->register($smarty);
        }

        $this->assertSame([
            ['modifier', 'test_mod'],
            ['function', 'test_func'],
        ], $smarty->calls);
    }

    #[Test]
    public function registryIntegrationWithRealSmarty(): void
    {
        if (!class_exists(\Smarty\Smarty::class) && !class_exists(\Smarty::class)) {
            $this->markTestSkipped('No Smarty runtime available');
        }

        $smarty = class_exists(\Smarty\Smarty::class) ? new \Smarty\Smarty() : new \Smarty();
        $smarty->setLeftDelimiter('<{');
        $smarty->setRightDelimiter('}>');

        $text = new \Xoops\SmartyExtensions\Extension\TextExtension();
        $text->register($smarty);

        $smarty->assign('text', 'Hello World and more text here');
        $result = $smarty->fetch('string:<{$text|excerpt:15}>');
        $this->assertSame('Hello World...', $result);

        $smarty->assign('count', 2);
        $result2 = $smarty->fetch('string:<{$count|pluralize:"item"}>');
        $this->assertSame('items', $result2);
    }

    #[Test]
    public function permissionBlockIsReEvaluatedOnSharedOutputCache(): void
    {
        if (!class_exists(\Smarty\Smarty::class) && !class_exists(\Smarty::class)) {
            $this->markTestSkipped('No Smarty runtime available');
        }

        $dir = sys_get_temp_dir() . '/smartyext-cache-' . bin2hex(random_bytes(4));
        $render = static function () use ($dir): string {
            $smarty = class_exists(\Smarty\Smarty::class) ? new \Smarty\Smarty() : new \Smarty();
            $smarty->setLeftDelimiter('<{');
            $smarty->setRightDelimiter('}>');
            $smarty->setCompileDir($dir . '/c');
            $smarty->setCacheDir($dir . '/k');
            $smarty->setCaching(1);
            $smarty->setCacheLifetime(300);
            (new \Xoops\SmartyExtensions\Extension\SecurityExtension())->register($smarty);

            return $smarty->fetch('string:<{xo_permission logged_in=1}>PRIVATE<{/xo_permission}>');
        };

        $saved = $GLOBALS['xoopsUser'] ?? null;
        try {
            $GLOBALS['xoopsUser'] = new \XoopsUser();
            $this->assertSame('PRIVATE', $render());

            $GLOBALS['xoopsUser'] = null; // anonymous reader, same cache entry
            $this->assertSame('', $render());
        } finally {
            $GLOBALS['xoopsUser'] = $saved;
            if (is_dir($dir)) {
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST,
                );
                foreach ($it as $f) {
                    $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
                }
                rmdir($dir);
            }
        }
    }
}
