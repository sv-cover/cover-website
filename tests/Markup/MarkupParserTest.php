<?php

namespace App\Tests\Markup;

use App\Markup\Markup;
use function App\Legacy\init;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class MarkupParserTest extends KernelTestCase
{
    protected Markup $markup;

    protected function setUp(): void
    {
        self::bootKernel();
        init(self::$kernel);
        $this->markup = static::getContainer()->get(Markup::class);
    }

    protected function render(string $markup): Crawler
    {
        return new Crawler($this->markup->render($markup));
    }

    public function testEscape(): void
    {
        // Ensure HTML tags are properly escaped
        $result = $this->render(<<<END
            Paragraph with [i]italics[/i] in markup tags.

            <script>alert('test')</script>

            Another paragraph with <strong>bold text</strong> in HTML tags.
        END);

        $this->assertThat($result, new CC\CrawlerSelectorExists('em'), 'Markup should be rendered not.');
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('script')), 'HTML tags should not be rendered.');
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('strong')), 'Inline HTML tags should not be rendered.');

        // Ensure HTML character reference/entities are not escaped
        // NB: skip crawler as it replaces entities by their unicode characters.
        $result = $this->markup->render('Test &nbsp; &amp; ampersands!');

        $this->assertStringContainsString('&nbsp;', $result, 'HTML entities must not be escaped');
        $this->assertStringContainsString('&amp;', $result, 'HTML entities must not be escaped');
    }

    public function testNewlines(): void
    {
        $result = $this->render(<<<END
            Paragraph with
            newlines!
        END);
        $this->assertThat($result, new CC\CrawlerSelectorExists('br'), 'Newlines should be replace with <br> tag.');

        $result = $this->render(<<<END
            Paragraph [b]with
            newlines[/b] inside inline tags!
        END);
        $this->assertThat($result, new CC\CrawlerSelectorExists('strong > br'), 'Newlines in inline tags should be replace with <br> tag.');
    }

    public function testParagraphs(): void
    {
        $result = $this->render('Text');
        $this->assertThat($result, new CC\CrawlerSelectorExists('p'), 'Any text should be wrapped in a paragraph.');
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p', 'Text'));

        $result = $this->render(<<<END
            Paragraph 1.

            Paragraph 2.

            Multiline
            Paragraph 3.

            Paragraph 4.
        END);
        $this->assertThat($result, new CC\CrawlerSelectorExists('p:nth-child(1)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('p:nth-child(2)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('p:nth-child(3)'), 'Linebreaks can exist inside paragraphs');
        $this->assertThat($result, new CC\CrawlerSelectorExists('p:nth-child(4)'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p:nth-child(1)', 'Paragraph 1.'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p:nth-child(2)', 'Paragraph 2.'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p:nth-child(3)', 'Multiline Paragraph 3.'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p:nth-child(4)', 'Paragraph 4.'));

        $result = $this->render(<<<END
            [b]Paragraph 1.[/b]

            [i]Paragraph 2.[/i]

            [s]Paragraph 3.[/s]
        END);

        $this->assertThat($result, new CC\CrawlerSelectorExists('p:nth-child(1) > strong'), 'Paragraphs can be made up of one single tag.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('p:nth-child(2) > em'), 'Paragraphs can be made up of one single tag.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('p:nth-child(3) > s'), 'Paragraphs can be made up of one single tag.');

        $result = $this->render(<<<END
            [s][b][url=https://example.com][center]Link[/center][/url][/b][/s]
        END);
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('s')), 'Empty paragraphs should be cleaned');
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('strong')), 'Empty paragraphs should be cleaned');
        $this->assertThat($result, new CC\CrawlerSelectorExists('a > .has-text-centered'));

        $result = $this->render(<<<END
            Paragraph 1.

            [s][b][url=https://example.com]Paragraph 2.[/url][/b][/s]

            [b][center]Paragraph 3.[/center][/b]

            [s][b][url=https://example.com][center]Paragraph 4.[/center][/url][/b][/s]

            [url=https://example.com][b][center]Paragraph 5.[/center][/b][/url]

            [b]Paragraph 6.

            Contains linebreaks.[/b]

            [center]Paragraph 7 contains [b]bold[/b].[/center]

            [center][b]Paragraph 8.[/b][/center]

            [center]
            Paragraph 9.

            Paragraph 10.
            [/center]

            Paragraph 1.
        END);

        $this->assertThat($result, new CC\CrawlerSelectorExists('p:nth-child(1)'), 'Paragraphs should be rendered at the start.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('p:nth-child(2) > s > strong > a'), 'Inline content should be wrapped in a paragraph.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('.has-text-centered:nth-child(3) > p'), 'Block content cannot exist inside inline content.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('a:nth-child(4) > .has-text-centered > p', 'Url containing block content cannot exist inside inline content.'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('a:nth-child(5) > .has-text-centered > p', 'Block content cannot exist inside inline content.'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('p:nth-child(6) > strong'), 'Inline content should be wrapped in a paragraph.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('p:nth-child(6) > strong > br:nth-of-type(1)'), 'All linebreaks inside inline content should be converted to <br>.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('p:nth-child(6) > strong > br:nth-of-type(2)'), 'All linebreaks inside inline content should be converted to <br>.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('.has-text-centered:nth-child(7) > p > strong'), 'Content inside block content should be wrapped in a paragraph and can contain inline content.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('.has-text-centered:nth-child(8) > p > strong'), 'Inline content inside block content should be wrapped in a paragraph.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('.has-text-centered:nth-child(9) > p:nth-child(1)'), 'Block content can contain paragraphs.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('.has-text-centered:nth-child(9) > p:nth-child(2)'), 'Block content can contain multiple paragraphs.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('p:nth-child(10)'), 'Paragraphs should be rendered at the end.');
    }


    public function testTags(): void
    {
        $result = $this->render('[b]Text[/b]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('strong'), 'Markup can contain one single tag.');

        $result = $this->render('[b]Text[/b] and other text');
        $this->assertThat($result, new CC\CrawlerSelectorExists('strong'), 'Markup can start with a tag.');

        $result = $this->render('Text and [b]bold text[/b]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('strong'), 'Markup can end with a tag.');

        $result = $this->render('Text, [b]bold text[/b] and other text');
        $this->assertThat($result, new CC\CrawlerSelectorExists('strong'), 'Markup can contain a tag.');

        $result = $this->render('[b]Bold[/b] [i]italics[/i]');
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p', 'Bold italics'), 'Spaces between inline tags should be maintained.');

        $result = $this->render('[s][b]Nested bold[/b][/s]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('s > strong'), 'Tags can be nested.');

        $result = $this->render('[s]Text [b]Nested bold[/b][/s]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('s > strong'), 'Tags can be nested with sibling content.');

        $result = $this->render('[s][b]Nested bold[/b] text[/s]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('s > strong'), 'Tags can be nested with sibling content.');

        $result = $this->render('[s][b]Nested bold[/b] [i]text[/i][/s]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('s > strong + em'), 'Tags can be nested with sibling tags.');

        $result = $this->render('[s][b]Nested bold[/b] [i]text[/i] and content[/s]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('s > strong + em'), 'Tags can be nested with sibling tags and content.');
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p > s', 'Nested bold text and content'), 'Tags can be nested with sibling tags and content.');

        $result = $this->render('[b][b]Nested bold[/b] text[/b]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('strong > strong'), 'The same tags can be nested.');
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p > strong', 'Nested bold text'), 'A nested child should not close its parent.');
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p > strong > strong', 'Nested bold'));
    }
}
