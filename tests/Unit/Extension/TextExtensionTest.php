<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Test\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xoops\SmartyExtensions\Extension\TextExtension;

#[CoversClass(TextExtension::class)]
final class TextExtensionTest extends TestCase
{
    private TextExtension $ext;

    protected function setUp(): void
    {
        $this->ext = new TextExtension();
    }

    #[Test]
    public function getModifiersReturnsAllSevenModifiers(): void
    {
        $modifiers = $this->ext->getModifiers();
        $this->assertCount(7, $modifiers);
        $this->assertArrayHasKey('excerpt', $modifiers);
        $this->assertArrayHasKey('truncate_words', $modifiers);
        $this->assertArrayHasKey('nl2p', $modifiers);
        $this->assertArrayHasKey('highlight_text', $modifiers);
        $this->assertArrayHasKey('reading_time', $modifiers);
        $this->assertArrayHasKey('pluralize', $modifiers);
        $this->assertArrayHasKey('extract_hashtags', $modifiers);
    }

    #[Test]
    public function excerptReturnsFullTextWhenShorterThanLimit(): void
    {
        $this->assertSame('Hello', $this->ext->excerpt('Hello', 100));
    }

    #[Test]
    public function excerptTruncatesAtWordBoundary(): void
    {
        $result = $this->ext->excerpt('The quick brown fox jumps over the lazy dog', 20);
        $this->assertSame('The quick brown...', $result);
    }

    #[Test]
    public function excerptUsesCustomEnding(): void
    {
        $result = $this->ext->excerpt('The quick brown fox jumps', 15, ' [more]');
        $this->assertStringEndsWith(' [more]', $result);
    }

    #[Test]
    public function excerptHandlesMultibyteCorrectly(): void
    {
        // Japanese string: 5 chars × 3 bytes each = 15 bytes; mb_strlen = 5
        $text = 'あいうえおかきくけこ'; // 10 multibyte chars
        $result = $this->ext->excerpt($text, 5);
        $this->assertSame('あい...', $result);
    }

    #[Test]
    public function truncateWordsReturnsFullTextWhenUnderLimit(): void
    {
        $this->assertSame('Hello world', $this->ext->truncateWords('Hello world', 5));
    }

    #[Test]
    public function truncateWordsCutsAtWordLimit(): void
    {
        $this->assertSame('one two three...', $this->ext->truncateWords('one two three four five', 3));
    }

    #[Test]
    public function nl2pConvertsDoubleNewlinesToParagraphs(): void
    {
        $result = $this->ext->nl2p("First paragraph\n\nSecond paragraph");
        $this->assertStringContainsString('<p>First paragraph</p>', $result);
        $this->assertStringContainsString('<p>Second paragraph</p>', $result);
    }

    #[Test]
    public function nl2pConvertsSingleNewlinesToBr(): void
    {
        $result = $this->ext->nl2p("Line one\nLine two");
        $this->assertStringContainsString('<br>', $result);
    }

    #[Test]
    public function nl2pReturnsEmptyForEmptyInput(): void
    {
        $this->assertSame('', $this->ext->nl2p(''));
    }

    #[Test]
    public function highlightTextWrapsMatchInSpan(): void
    {
        $result = $this->ext->highlightText('Hello World', 'World');
        $this->assertSame('Hello <span class="highlight">World</span>', $result);
    }

    #[Test]
    public function highlightTextReturnsUnchangedForEmptyTerm(): void
    {
        $this->assertSame('Hello', $this->ext->highlightText('Hello', ''));
    }

    #[Test]
    public function readingTimeReturnsMinRead(): void
    {
        $text = \str_repeat('word ', 400);
        $this->assertSame('2 min read', $this->ext->readingTime($text));
    }

    #[Test]
    public function pluralizeSingular(): void
    {
        $this->assertSame('comment', $this->ext->pluralize(1, 'comment'));
    }

    #[Test]
    public function pluralizePluralDefault(): void
    {
        $this->assertSame('comments', $this->ext->pluralize(2, 'comment'));
    }

    #[Test]
    public function pluralizePluralCustom(): void
    {
        $this->assertSame('children', $this->ext->pluralize(3, 'child', 'children'));
    }

    #[Test]
    public function extractHashtagsFindsAllTags(): void
    {
        $this->assertSame(['xoops', 'php'], $this->ext->extractHashtags('Hello #xoops and #php'));
    }

    #[Test]
    public function extractHashtagsReturnsEmptyForNoTags(): void
    {
        $this->assertSame([], $this->ext->extractHashtags('No tags here'));
    }

    #[Test]
    public function highlightTextKeepsBackreferencesInClassLiteral(): void
    {
        // Pre-fix, "$1" expanded to the quoted match and opened an onmouseover attribute
        $result = $this->ext->highlightText('say " onmouseover=alert(1) x', '" onmouseover=alert(1) x', '$1');
        $this->assertStringNotContainsString('class="" onmouseover', $result);
        $this->assertStringStartsWith('say <span class="highlight">', $result);
    }

    #[Test]
    public function highlightTextKeepsValidCustomClass(): void
    {
        $this->assertSame('a <span class="search-match">b</span>', $this->ext->highlightText('a b', 'b', 'search-match'));
    }

    #[Test]
    public function highlightTextFallsBackForOverlongClass(): void
    {
        $result = $this->ext->highlightText('aaa', 'a', str_repeat('x', 65));
        $this->assertSame(3, substr_count($result, 'class="highlight"'));
    }
}
