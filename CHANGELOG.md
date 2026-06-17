# Changelog

All notable changes to `xoops/smartyextensions` are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [Unreleased]

## [1.3.0 Beta2] — 2026-06-16

### Added

- **`render_breadcrumbs`** (S2) — also accepts the XOOPS-native list of records
  `[ ['link' => url, 'title' => label], … ]` (as produced by `$xoBreadCrumb`; `url`/`label`
  keys are accepted as aliases), in addition to the original `url => label` map. The last crumb,
  and any crumb with an empty link, renders as the non-linked active page.
- **`translate`** (S4) — optional default argument: `<{"_MI_X"|translate:'Latest news'}>` returns
  the default when the constant is undefined, letting it replace the `<{$smarty.const.X|default:'…'}>`
  idiom (which otherwise leaks the literal constant name).

### Changed

- **`format_date` / `relative_time`** (S3) — only numeric Unix timestamps and ISO-8601-style strings
  (`YYYY-MM-DD[ T]HH:MM[:SS]`) are parsed and reformatted; any other string is treated as an
  already-formatted display value (e.g. XOOPS `formatTimestamp` output) and returned **unchanged**
  rather than re-parsed and corrupted.

### Infrastructure

- **`phpstan-baseline.neon`** — deduplicated: the prefer-lowest/prefer-stable matrix baseline was a raw
  concatenation of both legs (every common error stored twice, ~317 entries); it is now a block-deduplicated
  union (163 entries, roughly half the size). No source or behaviour change.

## [1.3.0 Beta1] — 2026-06-16

### Added

- **`render_pagination`** — data-driven input mode: accepts `XoopsPageNav`'s native `total`, `limit`, and
  `start` (offset) values and computes the page count and current page itself, with a `{start}` URL
  placeholder for offset-based links. The existing `totalPages` / `currentPage` + `{page}` mode is retained
  for backward compatibility. Standard XOOPS list pages (which paginate through `XoopsPageNav`) can now adopt
  the plugin without restructuring their controllers.
- **`render_pagination`** — opt-in `window` parameter: when greater than `0`, renders the first and last
  pages plus the current page ± `window` neighbours with ellipses instead of every page link (default `0`
  preserves the show-all behaviour), making the plugin a practical replacement for `XoopsPageNav` on large
  result sets. A single-page gap is rendered as the page number rather than an ellipsis (`1 2 3`, not `1 … 3`).
- **`RenderPaginationTest`** / **`RenderQrCodeTest`** — cover the data-driven mode, `{start}` offset links, the
  backward-compatible page mode, single-page suppression (incl. clearing an `assign`ed variable), assign mode,
  href escaping, the windowed ellipsis behaviour, and local-vs-external QR output with size clamping.

### Changed

- **`render_qr_code`** — now generates the QR code locally via `chillerlan/php-qrcode` (v5/v6 API: inline
  SVG data-URI, no external request, no GD requirement). **The external service is no longer used by default**:
  a failed/absent local generator now yields no image rather than silently sending the QR payload to a third
  party. Pass `externalFallback=true` to opt back into the external API. The `size` is clamped to 32–1024 px.
  The library is loaded from the host project's autoloader, or once from the plugin's own bundled `vendor/`
  when the host does not autoload it.
- **`render_pagination`** — clears the `assign`ed template variable on a single-page early return, so stale
  pagination from a previous loop iteration is not left behind.
- **`composer.json`** — `chillerlan/php-qrcode` (`^5.0 || ^6.0`) promoted to `require`. **Behaviour change:**
  consumers using only the non-QR plugins now also pull this dependency; it makes local QR the default.

### Fixed

- **`SecurityExtensionTest`**: Correct stale `sanitizeFilename` assertion — `image<>|?.jpg` now correctly expects `image.jpg` (dot preserved by allowlist) not `imagejpg`. Test renamed to `sanitizeFilenameRemovesSpecialCharsButPreservesDot` with inline pipeline walkthrough.

### Documentation

- **`AbstractExtension`**: Add class-level PHPDoc defining three output-safety contracts — the raw-in-assign default, the HTML-generating exception, and the block handler `$content` contract — giving the three subclass exception notes a canonical definition to reference.
- **`FormExtension`**: `validateForm` gains `@return ''` literal-type PHPDoc and an in-code comment at the no-assign return site. `createButton`, `renderFormErrors`, and `displayError` each gain an explicit "exception to the raw-in-assign contract" note with `nofilter` usage examples.
- **`TUTORIAL.md`**: Add **Recipe: Form Validation Pattern** — canonical end-to-end POST form with CSRF, mandatory `assign` on `validate_form`, `$_POST` raw-value safety note, GET-vs-POST CSRF contrast, and a multibyte character table explaining why `mb_strlen` matters for non-Latin input. TOC updated with nested link to the recipe.

### Infrastructure

- **`phpstan.neon`**: Fix `scanDirectories` path (`stubs/` → `tests/stubs/`); remove stale XMF `ignoreErrors` rule for `SerializableTrait.php`.
- **`phpstan-baseline.neon`**: Create missing file (absence caused `composer analyse` to abort before scanning any source).
- **`Makefile`**: Add `install`, `test`, `analyse`, `lint`, `fix`, `baseline`, and `ci` targets. `baseline` help text documents the first-checkout workflow.
- **`scripts/setup.ps1`**: PowerShell equivalent of `make install` for Windows developers without Git Bash or WSL.
- **`scripts/ci.ps1`**: PowerShell equivalent of `make ci`; PHPCS is advisory-only (matching `ci.yml`), PHPStan and PHPUnit are hard gates; accepts `-SkipInstall` flag.
- **`scripts/baseline.ps1`**: PowerShell equivalent of `make baseline` with documented when-to-use and when-not-to-use guidance.
- **`.github/CONTRIBUTING.md`**: Full rewrite with concrete setup steps for Unix (`make install && make ci`) and Windows (`.\scripts\setup.ps1 && .\scripts\ci.ps1`), first-run baseline workflow, and updated PR checklist referencing `tests/Unit/` and PHPUnit attribute syntax.

---

## [1.2.0] — 2026-04-02

### Bug Fixes

- **`FormExtension`**: Replace `strlen()` with `mb_strlen($value, 'UTF-8')` in `min_length` and `max_length` validation rules. Multibyte characters (CJK, Arabic, emoji) now count as single characters rather than multiple bytes.
- **`SecurityExtension`**: `sanitizeFilename()` now calls `basename()` before the character allowlist, blocking directory traversal (`../../etc/passwd` → `passwd`). Leading dots are stripped afterward (`'.htaccess'` → `'htaccess'`).
- **`NavigationExtension`**: `generateUrl()` was erroneously storing an HTML-escaped URL in the template variable when `assign` was set. Fixed to store the raw URL; HTML escaping is now applied only to direct output.
- **`XoopsCoreExtension`**: `xoModuleUrl()` had the same assign/escape inversion as `generateUrl()`. Fixed to store raw URL in assign and escape only on direct output.
- **`DataExtension`**: `getReferrer()` was storing an HTML-escaped referrer in the template variable when `assign` was set. Fixed to store the raw value.

### Added

- **`AssetExtension`** — deferred CSS/JS asset queuing with deduplication, last-write-wins attribute merging, and strict URL scheme validation (`javascript:`, `data:`, and their HTML entity-encoded variants are blocked).
- **`RayDebugExtension`** — zero-dependency Spatie Ray integration. All functions and the modifier silently no-op when Ray is unavailable.
- **`XoopsCoreExtension`** — XOOPS globals wrapped as Smarty functions: `xo_get_config`, `xo_get_current_user`, `xo_get_module_info`, `xo_get_notifications`, `xo_module_url`, `xo_render_block`, `xo_render_menu`, `xo_avatar`, `xo_debug`. Plus the `translate` modifier for XOOPS language constants.
- **`FormExtension`** — complete form toolkit: `form_open` (with automatic XOOPS CSRF injection for POST), `form_close`, `form_input`, `create_button`, `render_form_errors`, `validate_form`, `validate_email`, `display_error`.

### Infrastructure

- **`phpunit.xml.dist`**: Fix incorrect bootstrap path (`vendor/autoload.php` → `tests/bootstrap.php`) and non-existent test directory (`./tests/unit` → `./tests/Unit`).
- **`composer.json`**: Restore truncated file; add `autoload-dev` PSR-4 mapping for `Xoops\\SmartyExtensions\\Test\\`; add `scripts` (test, analyse, lint, fix).
- **`.gitattributes`**: Remove `/docs export-ignore` so `TUTORIAL.md` ships in Composer dist packages.
- **Tests restructured** into `tests/Unit/` hierarchy following industry standards (`tests/Unit/Extension/`, `tests/Unit/Adapter/`). Old flat layout removed.
- **`tests/stubs/XoopsStubs.php`**: Expanded with `XoopsUser::getVar()`, `XoopsModule`, and `xoops_getHandler()` for `XoopsCoreExtensionTest`.
- **New test classes added**:
  - `tests/Unit/Extension/FormExtensionTest.php` — 26 tests covering all 8 functions including multibyte validation.
  - `tests/Unit/Extension/XoopsCoreExtensionTest.php` — 22 tests covering all 9 functions and the `translate` modifier.
  - `tests/Unit/Extension/DataExtensionTest.php` — updated to verify raw-URL assign contract in `getReferrer`.
  - `tests/Unit/Extension/NavigationExtensionTest.php` — updated to assert raw URL in assign for `generateUrl`.
  - `tests/Unit/Extension/SecurityExtensionTest.php` — updated to reflect `basename()`-based sanitizeFilename behavior.
  - `tests/Unit/Extension/TextExtensionTest.php` — added multibyte `excerpt` test.
- **`README.md`**: Full rewrite from single-line stub to comprehensive reference documentation.

---

## [1.1.0] — 2026-01-15

### Added

- Initial public release of the extension package.
- `AbstractExtension` base class with `register()`, `getModifiers()`, `getFunctions()`, `getBlockHandlers()`.
- `ExtensionRegistry` with Smarty 4/5 auto-detection.
- `Smarty5Adapter` wrapping `AbstractExtension` for Smarty 5's `addExtension()` API.
- `TextExtension`: `excerpt`, `truncate_words`, `nl2p`, `highlight_text`, `reading_time`, `pluralize`, `extract_hashtags`.
- `FormatExtension`: `format_date`, `relative_time`, `format_currency`, `number_format`, `bytes_format`, `format_phone_number`, `gravatar`, `datetime_diff`, `get_current_year`.
- `NavigationExtension`: `generate_url`, `generate_canonical_url`, `url_segment`, `social_share`, `render_breadcrumbs`, `render_pagination`, `render_qr_code`, `render_alert`, `parse_url`, `strip_protocol`, `slugify`, `youtube_id`, `linkify`.
- `SecurityExtension`: `sanitize_string`, `sanitize_url`, `sanitize_filename`, `sanitize_string_for_xml`, `mask_email`, `obfuscate_text`, `hash_string`, `generate_csrf_token`, `validate_csrf_token`, `has_user_permission`, `is_user_logged_in`, `user_has_role`, `xo_permission` block.
- `DataExtension`: `array_filter`, `array_sort`, `pretty_print_json`, `get_file_size`, `get_mime_type`, `is_image`, `strip_html_comments`, `array_to_csv`, `base64_encode_file`, `embed_pdf`, `generate_xml_sitemap`, `generate_meta_tags`, `get_referrer`, `get_session_data`.
- Initial test suite and `TemplateStub` / `SmartyExtensionBase` stubs.

---

[Unreleased]: https://github.com/mambax7/smartyextensions/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/mambax7/smartyextensions/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/mambax7/smartyextensions/releases/tag/v1.1.0
