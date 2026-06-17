<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Test\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xoops\SmartyExtensions\Extension\FormatExtension;
use Xoops\SmartyExtensions\Test\Stubs\TemplateStub;

#[CoversClass(FormatExtension::class)]
final class FormatExtensionTest extends TestCase
{
    private FormatExtension $ext;

    protected function setUp(): void
    {
        $this->ext = new FormatExtension();
    }

    #[Test]
    public function getFunctionsReturnsTwoEntries(): void
    {
        $functions = $this->ext->getFunctions();
        $this->assertCount(2, $functions);
        $this->assertArrayHasKey('datetime_diff', $functions);
        $this->assertArrayHasKey('get_current_year', $functions);
    }

    #[Test]
    public function getModifiersReturnsSevenEntries(): void
    {
        $modifiers = $this->ext->getModifiers();
        $this->assertCount(7, $modifiers);
        $this->assertArrayHasKey('format_date', $modifiers);
        $this->assertArrayHasKey('relative_time', $modifiers);
        $this->assertArrayHasKey('format_currency', $modifiers);
        $this->assertArrayHasKey('number_format', $modifiers);
        $this->assertArrayHasKey('bytes_format', $modifiers);
        $this->assertArrayHasKey('format_phone_number', $modifiers);
        $this->assertArrayHasKey('gravatar', $modifiers);
    }

    #[Test]
    public function formatDateFormatsKnownDate(): void
    {
        $this->assertSame('Mar 20, 2026', $this->ext->formatDate('2026-03-20', 'M d, Y'));
    }

    #[Test]
    public function formatDateFormatsTimestamp(): void
    {
        // 2024-01-01 00:00:00 UTC
        $this->assertSame('2024-01-01', $this->ext->formatDate(1704067200, 'Y-m-d'));
    }

    #[Test]
    public function formatDateReturnsOriginalOnInvalidInput(): void
    {
        $this->assertSame('not-a-date', $this->ext->formatDate('not-a-date'));
    }

    #[Test]
    public function formatDatePassesThroughPreformattedString(): void
    {
        // Already-formatted display strings (e.g. XOOPS formatTimestamp output, which
        // is non-ISO) are returned unchanged rather than re-parsed and reformatted.
        $this->assertSame('2026/03/20 14:30', $this->ext->formatDate('2026/03/20 14:30', 'Y-m-d'));
        $this->assertSame('March 20, 2026', $this->ext->formatDate('March 20, 2026'));
        // An ISO-looking prefix with trailing text is NOT a clean timestamp — pass through.
        $this->assertSame('2026-03-20 14:30 EST', $this->ext->formatDate('2026-03-20 14:30 EST', 'Y-m-d'));
    }

    #[Test]
    public function relativeTimePassesThroughPreformattedString(): void
    {
        $this->assertSame('20 mars 2026', $this->ext->relativeTime('20 mars 2026'));
    }

    #[Test]
    public function bytesFormatConvertsOneMegabyte(): void
    {
        $this->assertSame('1 MB', $this->ext->bytesFormat(1048576));
    }

    #[Test]
    public function bytesFormatWithPrecision(): void
    {
        $this->assertSame('1.5 MB', $this->ext->bytesFormat(1572864, 1));
    }

    #[Test]
    public function bytesFormatReturnsZeroForNegative(): void
    {
        $this->assertSame('0 B', $this->ext->bytesFormat(-100));
    }

    #[Test]
    public function numberFormatWithDefaults(): void
    {
        $this->assertSame('1,234.50', $this->ext->numberFormat(1234.5));
    }

    #[Test]
    public function numberFormatWithZeroDecimals(): void
    {
        $this->assertSame('1,235', $this->ext->numberFormat(1234.5, 0));
    }

    #[Test]
    public function numberFormatWithCustomSeparators(): void
    {
        $this->assertSame('1.234,50', $this->ext->numberFormat(1234.5, 2, ',', '.'));
    }

    #[Test]
    public function gravatarReturnsGravatarUrl(): void
    {
        $url = $this->ext->gravatar('test@example.com');
        $this->assertStringContainsString('gravatar.com', $url);
        $this->assertStringContainsString('?s=64', $url);
    }

    #[Test]
    public function gravatarUsesCustomSizeAndDefault(): void
    {
        $url = $this->ext->gravatar('test@example.com', 128, 'retro');
        $this->assertStringContainsString('?s=128', $url);
        $this->assertStringContainsString('d=retro', $url);
    }

    #[Test]
    public function gravatarHashesEmailCorrectly(): void
    {
        $expected = \md5('test@example.com');
        $url = $this->ext->gravatar('Test@Example.com');
        $this->assertStringContainsString($expected, $url);
    }

    #[Test]
    public function formatPhoneNumberFormatsTenDigits(): void
    {
        $this->assertSame('(555) 123-4567', $this->ext->formatPhoneNumber('5551234567'));
    }

    #[Test]
    public function formatPhoneNumberFormatsElevenDigitsWithCountryCode(): void
    {
        $this->assertSame('+1 (555) 123-4567', $this->ext->formatPhoneNumber('15551234567'));
    }

    #[Test]
    public function formatPhoneNumberStripsNonDigits(): void
    {
        $this->assertSame('(555) 123-4567', $this->ext->formatPhoneNumber('(555) 123-4567'));
    }

    #[Test]
    public function formatPhoneNumberReturnsOriginalForOddLength(): void
    {
        $this->assertSame('12345', $this->ext->formatPhoneNumber('12345'));
    }

    #[Test]
    public function relativeTimeReturnsJustNowForCurrentTime(): void
    {
        $this->assertSame('Just now', $this->ext->relativeTime(\time()));
    }

    #[Test]
    public function relativeTimeReturnsOriginalOnInvalidInput(): void
    {
        $this->assertSame('not-a-date', $this->ext->relativeTime('not-a-date'));
    }

    #[Test]
    public function relativeTimeReturnsFutureForFutureDates(): void
    {
        $futureTimestamp = \time() + 86400 * 3; // 3 days from now
        $result = $this->ext->relativeTime($futureTimestamp);
        $this->assertStringContainsString('from now', $result);
        $this->assertStringNotContainsString('ago', $result);
    }

    #[Test]
    public function relativeTimeReturnsPastForPastDates(): void
    {
        $pastTimestamp = \time() - 86400 * 3; // 3 days ago
        $result = $this->ext->relativeTime($pastTimestamp);
        $this->assertStringContainsString('ago', $result);
        $this->assertStringNotContainsString('from now', $result);
    }

    #[Test]
    public function datetimeDiffCalculatesDifference(): void
    {
        $tpl = $this->createMock(TemplateStub::class);
        $result = $this->ext->datetimeDiff(
            ['start' => '2024-01-01', 'end' => '2026-03-20'],
            $tpl,
        );
        $this->assertStringContainsString('2 years', $result);
    }

    #[Test]
    public function datetimeDiffReturnsEmptyForMissingParams(): void
    {
        $tpl = $this->createMock(TemplateStub::class);
        $this->assertSame('', $this->ext->datetimeDiff([], $tpl));
    }

    #[Test]
    public function datetimeDiffAssignsToTemplate(): void
    {
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('myVar', $this->isType('string'));

        $result = $this->ext->datetimeDiff(
            ['start' => '2024-01-01', 'end' => '2024-06-15', 'assign' => 'myVar'],
            $tpl,
        );
        $this->assertSame('', $result);
    }

    #[Test]
    public function getCurrentYearReturnsCurrentYear(): void
    {
        $tpl = $this->createMock(TemplateStub::class);
        $result = $this->ext->getCurrentYear([], $tpl);
        $this->assertSame(\date('Y'), $result);
    }

    #[Test]
    public function getCurrentYearAssignsToTemplate(): void
    {
        $tpl = $this->createMock(TemplateStub::class);
        $tpl->expects($this->once())
            ->method('assign')
            ->with('year', \date('Y'));

        $result = $this->ext->getCurrentYear(['assign' => 'year'], $tpl);
        $this->assertSame('', $result);
    }

    #[Test]
    public function formatCurrencyFallsBackWithoutIntl(): void
    {
        // Exercises the fallback path if intl is not loaded,
        // or the ICU path if it is — either way, it returns a string.
        $result = $this->ext->formatCurrency(1234.56);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('1,234', $result);
    }
}
