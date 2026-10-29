<?php

/**
 * PHPStan stubs for Smarty 5 interfaces.
 */

namespace Smarty\FunctionHandler;

interface FunctionHandlerInterface
{
    /** @param array<string, mixed> $params */
    public function handle($params, \Smarty\Template $template): mixed;

    public function isCacheable(): bool;
}

namespace Smarty\BlockHandler;

interface BlockHandlerInterface
{
    /** @param array<string, mixed> $params */
    public function handle($params, $content, \Smarty\Template $template, &$repeat): mixed;

    public function isCacheable(): bool;
}

namespace Smarty;

class Template {}

class Smarty
{
    public function registerPlugin(string $type, string $name, callable $callback, bool $cacheable = true): void {} // NOSONAR php:S1172 signature mirrors Smarty

    public function addExtension(object $extension): void {}

    public function setLeftDelimiter(string $delimiter): void {}

    public function setRightDelimiter(string $delimiter): void {}

    /** @param mixed $value */
    public function assign(string $name, mixed $value = null): void {}

    public function fetch(string $template): string { return ''; }
}
