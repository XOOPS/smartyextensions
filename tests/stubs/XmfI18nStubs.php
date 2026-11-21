<?php

declare(strict_types=1);

/**
 * Minimal stand-in for the XMF 1.5 translation engine, which this package does
 * not require. Only loaded when the real classes are not available.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */

namespace Xmf\I18n;

if (!class_exists(LocaleContext::class, false)) {
    final class LocaleContext
    {
        public function __construct(public string $tag)
        {
        }
    }
}

if (!class_exists(Translation::class, false)) {
    final class Translation
    {
        public static bool $initialized = false;

        public static string $tag = 'en_US';

        /** @var array<string, string> key => catalog string */
        public static array $catalog = [];

        /** @var list<array{dirname: string, key: string, params: array<array-key, mixed>, domain: string, count: int|null}> */
        public static array $calls = [];

        /** When true, explain() throws, as the real resolver can (an invalid dirname). */
        public static bool $explainThrows = false;

        public static function reset(): void
        {
            self::$initialized   = false;
            self::$tag           = 'en_US';
            self::$catalog       = [];
            self::$calls         = [];
            self::$explainThrows = false;
        }

        public static function isInitialized(): bool
        {
            return self::$initialized;
        }

        public static function getInstance(): ?self
        {
            return self::$initialized ? new self() : null;
        }

        /**
         * @param array<array-key, mixed> $params
         */
        public static function translate(
            string $dirname,
            string $key,
            array $params = [],
            string $domain = 'main',
            ?int $count = null,
        ): string {
            self::$calls[] = [
                'dirname' => $dirname,
                'key'     => $key,
                'params'  => $params,
                'domain'  => $domain,
                'count'   => $count,
            ];

            // As the real engine: a miss returns the key untouched, without placeholders.
            if (!isset(self::$catalog[$key])) {
                return $key;
            }

            $out = self::$catalog[$key];
            foreach ($params as $name => $value) {
                if (\is_scalar($value)) {
                    $out = \str_replace('{' . $name . '}', (string) $value, $out);
                }
            }

            return $out;
        }

        public function context(): LocaleContext
        {
            return new LocaleContext(self::$tag);
        }

        /** As the real engine: resolves without translating and without a call to the listener. */
        public function explain(string $dirname, string $key, string $domain = 'main'): Catalog\Resolution
        {
            if (self::$explainThrows) {
                throw new \RuntimeException('Invalid dirname component: ' . $dirname);
            }

            return new Catalog\Resolution(isset(self::$catalog[$key]));
        }
    }
}

namespace Xmf\I18n\Catalog;

if (!class_exists(Resolution::class, false)) {
    final class Resolution
    {
        public function __construct(private readonly bool $found)
        {
        }

        public function found(): bool
        {
            return $this->found;
        }
    }
}
