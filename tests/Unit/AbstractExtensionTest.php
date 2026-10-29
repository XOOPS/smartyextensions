<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Test\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xoops\SmartyExtensions\AbstractExtension;

#[CoversClass(AbstractExtension::class)]
final class AbstractExtensionTest extends TestCase
{
    #[Test]
    public function defaultGettersReturnEmptyArrays(): void
    {
        $ext = new class extends AbstractExtension {};

        $this->assertSame([], $ext->getModifiers());
        $this->assertSame([], $ext->getFunctions());
        $this->assertSame([], $ext->getBlockHandlers());
    }

    #[Test]
    public function registerCallsRegisterPluginForEachType(): void
    {
        $ext = new class extends AbstractExtension {
            public function getModifiers(): array
            {
                return ['my_mod' => strtolower(...)];
            }
            public function getFunctions(): array
            {
                return ['my_func' => fn(array $p, object $t): string => 'ok'];
            }
            public function getBlockHandlers(): array
            {
                return ['my_block' => fn(array $p, ?string $c, object $t, bool &$r): string => ''];
            }
        };

        $smarty = new class {
            /** @var list<array{string, string, bool}> */
            public array $calls = [];
            public function registerPlugin(string $type, string $name, callable $callback, bool $cacheable = true): void
            {
                $this->calls[] = [$type, $name, $cacheable];
            }
        };

        $ext->register($smarty);

        // Functions and blocks are non-cacheable, matching Smarty5Adapter
        $this->assertSame([
            ['modifier', 'my_mod', true],
            ['function', 'my_func', false],
            ['block', 'my_block', false],
        ], $smarty->calls);
    }
}
