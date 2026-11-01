# xoops/smartyextensions: Copilot Instructions

<!-- Generic XOOPS conventions: see .github/xoops-copilot-template.md for reuse in other repos -->

## About This Repository

`xoops/smartyextensions` is a PHP library of Smarty 4/5 template plugins for the XOOPS CMS: formatting, text, navigation, forms, security, assets, data, CSRF token and XOOPS core helpers. Plugins are grouped by domain into extension classes. Callers add the extensions they want to an `ExtensionRegistry` and register them on each Smarty instance.

## Project Layout

```
src/                          # Library source (namespace: Xoops\SmartyExtensions\)
src/AbstractExtension.php     # Base class: getModifiers()/getFunctions()/getBlockHandlers() + Smarty 4 register()
src/ExtensionRegistry.php     # Registers added extensions; wraps each in Smarty5Adapter on Smarty 5
src/Adapter/Smarty5Adapter.php
src/Extension/*Extension.php  # One final class per plugin domain
tests/Unit/                   # PHPUnit 11 tests (namespace: Xoops\SmartyExtensions\Test\)
tests/stubs/                  # Test bootstrap stubs, also scanned by PHPStan
stubs/                        # Smarty 4/5, XOOPS, Ray and DebugBar stubs for IDEs and analysis
.github/workflows/ci.yml      # GitHub Actions: lint, PHPStan, PHPUnit, coverage
```

## Build & Test

```bash
composer install
composer test       # PHPUnit
composer analyse    # PHPStan (level max)
composer lint       # PHPCS (PSR-12)
composer fix        # PHPCBF
make ci             # install -> lint -> analyse -> test; stops on a lint failure
```

On Windows, `.\scripts\ci.ps1` runs the same steps with lint as advisory; `-SkipInstall` skips `composer install`.

## PHP and Smarty Compatibility

- PHP 8.2+ (`composer.json`: `^8.2`). PHP 8.2 features are allowed: constructor promotion, readonly properties, `match`, union types, enums.
- Smarty `^4.5 || ^5.0`. Code must work on both majors; CI's lowest-deps leg installs Smarty 4.
- Smarty 5-only code belongs in `src/Adapter/Smarty5Adapter.php`, which PHPStan excludes. `\Smarty` is the global class on 4.x and a runtime alias on 5.x.

## Coding Conventions

- Every PHP file in `src/` and `tests/` starts with `declare(strict_types=1);` and follows PSR-12 (`phpcs.xml`). The analysis stubs in `stubs/` mirror third-party signatures and are exempt; don't add strict types to them.
- Class docblocks carry `@copyright` and `@license` tags.
- Use `self::` for constants defined by the class.
- Extension classes are `final`, extend `AbstractExtension`, and return `name => callable` maps from `getModifiers()`, `getFunctions()` and `getBlockHandlers()`.
- Prefer `\Throwable` in catch blocks over `\Exception`.
- Encoding is passed as the literal `'UTF-8'`. The repo has no `ENCODING` class constant; don't add one to a single class.

## Output and Escaping Contract

The `AbstractExtension` class docblock states the assign, HTML-generator and block-handler rules. The rules below add per-plugin escaping, `ENT_SUBSTITUTE`, format-specific encoding and URL validation.

- Escaping is decided per plugin, not per plugin type. Plugins that produce a finished display value (formatted dates, masked or sanitised strings, functions that print a plain-text value) escape their direct output with `htmlspecialchars()` using `ENT_QUOTES` and `'UTF-8'`. Add `ENT_SUBSTITUTE` where the value may hold invalid UTF-8: without it the whole string comes back empty. With an `assign` parameter, functions store the raw value; only direct output is escaped.
- The text-transform modifiers in `TextExtension` are not display values: `excerpt`, `truncate_words`, `reading_time` and `pluralize` return unescaped text that the template escapes, and `nl2p` and `highlight_text` return markup by design. Don't add escaping to them. `nl2p` and `highlight_text` keep any HTML in their input active, so their input must be trusted markup or already escaped (`|escape` before the modifier).
- Known gap on `master`: `xo_get_config` and `get_session_data` print their value unescaped on direct output. Treat the rule above as binding for new and changed code, and don't flag these two as regressions in unrelated PRs.
- These functions return or assign the finished markup with its dynamic parts escaped, not a raw value: `createButton`, `renderFormErrors`, `displayError`, `embedPdf`, `generateMetaTags`, `renderBreadcrumbs`, `renderPagination`, `renderQrCode`, `renderAlert`, `xoAvatar`, `xoRenderMenu`, `generateCsrfToken`. Templates print the assigned variable with `nofilter`; don't escape it again. When you add such a function, add it to this list and mark its docblock "exception to the raw-in-assign contract".
- Exceptions with mixed output: `flushCss` and `flushJs` print markup but assign the raw entry arrays; `socialShare` assigns the share URL for a single `platform` and markup for the full bar; `xoRenderBlock` passes through the content the block provides.
- Values in another format are built with that format's encoder (`http_build_query`/`urlencode`, `json_encode`, `fputcsv`), then HTML-escaped when printed into the page: `generate_url` and `array_to_csv` direct output use `ENT_QUOTES`, `pretty_print_json` uses `ENT_NOQUOTES` for `<pre>` text. `assign` still stores the raw value. Only a standalone document the function builds whole, such as the sitemap XML, uses its own escaping (`ENT_XML1`) instead.
- Block handlers receive `$content` already rendered by Smarty. Don't re-escape it.
- Validate externally supplied URLs for `href`, `src` or `action` with `AbstractExtension::isSafeUrl()` before escaping. URLs the library builds itself, such as the QR code `data:` URI, follow their own construction rules.
- Use `mb_*` functions on user text before escaping; a byte-cut multibyte character becomes invalid UTF-8.

## Smarty Caching

Functions and blocks are registered non-cacheable; modifiers are not. A non-cacheable function does not make the tags that read its `assign` variable non-cacheable: a later `<{if $var}>` is compiled into the cache and served to every visitor. When the value depends on the user, wrap the call and every reader in `<{nocache}>…<{/nocache}>`.

## Optional Integrations

- XOOPS may be absent at runtime. Check XOOPS objects with `instanceof` (`$xoopsUser instanceof \XoopsUser`), read config with `?? null`, and accept optional injected collaborators (e.g. `TokenExtension(?\XoopsSecurity $security = null)`) so tests run without a XOOPS install.
- Ray functions are no-ops when Ray is not installed; keep it that way.
- QR codes are generated locally (`chillerlan/php-qrcode`). The external fallback is used only when explicitly requested.

## Testing Guidelines

- Test classes extend `\PHPUnit\Framework\TestCase` in `Xoops\SmartyExtensions\Test\`, organized by extension under `tests/Unit/Extension/`, with separate classes for larger features (pagination, QR).
- Tests must be isolated: no XOOPS install, no network. Use the stubs in `tests/stubs/`.
- For escaping regressions, assert the exact escaped output where practical, not just the absence of a tag.
- Seeded randomness uses `\Random\Randomizer` with `\Random\Engine\Mt19937`, never `mt_srand()`/`mt_rand()` (SonarCloud flags them).
- Use `try/finally` for temp file cleanup.

## Static Analysis

PHPStan runs at level `max` over `src/` with `phpstan-baseline.neon`. New code must not add errors. `reportUnmatchedIgnoredErrors` is off because the Smarty 4 and 5 legs resolve types differently. Fix the code instead of adding baseline entries.

## CI Pipeline

- **GitHub Actions:** PHP 8.2 (plus lowest deps), 8.3, 8.4, 8.5. Each run: `composer validate`, lint (non-blocking), analyse, test.
- **Coverage:** PHP 8.3 with Xdebug, uploaded to Codecov.
- **Also:** SonarCloud (automatic analysis), Scrutinizer, CodeRabbit. The `sonarcloud.yml` and `qodana.yml` workflow jobs are guarded to `XOOPS/xmf` and skip here.

## Pull Request Checklist

1. `composer lint`, `composer analyse` and `composer test` pass.
2. New plugins have PHPDoc and unit tests in `tests/Unit/Extension/`.
3. Output follows the escaping contract above.
4. User-facing changes are documented in `CHANGELOG.md` under `[Unreleased]`; template-author-visible changes also update `docs/TUTORIAL.md` (see `.github/CONTRIBUTING.md`).
