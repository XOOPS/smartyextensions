<?php

declare(strict_types=1);

/**
 * Minimal stubs for XOOPS classes used in tests.
 * Only loaded when the real XOOPS classes are not available (standalone test env).
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */

if (!class_exists('XoopsSecurity', false)) {
    class XoopsSecurity
    {
        public function getTokenHTML(): string
        {
            return '<input type="hidden" name="token" value="test-token">';
        }

        public function check(bool $clearIfValid = false): bool
        {
            return true;
        }

        // Untyped, matching real \XoopsSecurity::createToken()'s loose signature
        // (htdocs/class/xoopssecurity.php) — see project convention on core-method
        // override compatibility (widen/untype, never narrow).
        public function createToken($timeout = 0, $name = 'XOOPS_TOKEN')
        {
            return 'test-token';
        }
    }
}

if (!class_exists('XoopsGroupPermHandler', false)) {
    class XoopsGroupPermHandler
    {
        /**
         * @param string       $gperm_name
         * @param int          $gperm_itemid
         * @param list<string> $gperm_groupid
         * @param int          $gperm_modid
         */
        public function checkRight(string $gperm_name, int $gperm_itemid, array $gperm_groupid, int $gperm_modid = 1): bool
        {
            return false;
        }
    }
}

if (!class_exists('XoopsUser', false)) {
    class XoopsUser
    {
        /** @var array<string, mixed> */
        private array $vars = [];

        /** @param array<string, mixed> $vars */
        public function __construct(array $vars = [])
        {
            $this->vars = $vars;
        }

        public function getVar(string $key, string $format = 's'): mixed
        {
            return $this->vars[$key] ?? null;
        }

        public function isAdmin(int $moduleId = 0): bool
        {
            return false;
        }

        /** @return list<int|string> */
        public function getGroups(): array
        {
            return [];
        }
    }
}

if (!class_exists('XoopsModule', false)) {
    class XoopsModule
    {
        /** @var array<string, mixed> */
        private array $vars = [];

        /** @param array<string, mixed> $vars */
        public function __construct(array $vars = [])
        {
            $this->vars = $vars;
        }

        public static function getByDirname(string $dirname): ?self
        {
            return null;
        }

        public function getVar(string $key, string $format = 's'): mixed
        {
            return $this->vars[$key] ?? null;
        }

        /** @return array<string, mixed> */
        public function getAdminMenu(): array
        {
            return [];
        }
    }
}

if (!function_exists('xoops_getHandler')) {
    /**
     * @return object{getUser?: callable, getByUser?: callable, getByDirname?: callable}
     */
    function xoops_getHandler(string $name): object
    {
        return new class {
            public function getUser(int $uid): ?XoopsUser
            {
                return null;
            }

            /** @return list<object> */
            public function getByUser(int $uid): array
            {
                return [];
            }

            public function getByDirname(string $dirname): ?XoopsModule
            {
                return null;
            }
        };
    }
}
