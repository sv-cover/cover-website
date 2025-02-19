<?php

namespace App\Tests\Markup;

use App\Markup\Markup;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class HeadingTest extends KernelTestCase
{
    protected Markup $markup;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->markup = static::getContainer()->get(Markup::class);
    }

    protected function render(string $markup, int $offset = 0): Crawler
    {
        return new Crawler($this->markup->render($markup, ['heading_offset' => $offset]));
    }

    /**
     * [h1] should be hidden by HiddenTag. But include it here for good measure
     */
    public function testH1(): void
    {
        $result = $this->render("[h1]Text[/h1]");

        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('h1')));
    }

    public function testH2(): void
    {
        $result = $this->render("[h2]Text[/h2]");

        $this->assertThat($result, new CC\CrawlerSelectorExists('h2'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('h2', 'Text'));
    }

    public function testH3(): void
    {
        $result = $this->render("[h3]Text[/h3]");

        $this->assertThat($result, new CC\CrawlerSelectorExists('h3'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('h3', 'Text'));
    }

    public function testH4(): void
    {
        $result = $this->render("[h4]Text[/h4]");

        $this->assertThat($result, new CC\CrawlerSelectorExists('h4'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('h4', 'Text'));
    }

    public function testH5(): void
    {
        $result = $this->render("[h5]Text[/h5]");

        $this->assertThat($result, new CC\CrawlerSelectorExists('h5'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('h5', 'Text'));
    }

    public function testH6(): void
    {
        $result = $this->render("[h6]Text[/h6]");

        $this->assertThat($result, new CC\CrawlerSelectorExists('h6'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('h6', 'Text'));
    }

    /**
     * [h7] can't exist, but what if a bug makes it exit?
     */
    public function testH7(): void
    {
        $text = "[h7]Text[/h7]";
        $result = $this->render($text);

        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('h7')));
        $this->assertThat($result, new CC\CrawlerSelectorExists('p'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p', $text));
    }

    public function testClass(): void
    {
        $result = $this->render("[h2.title.is-2]Text[/h2]");

        $this->assertStringContainsString('title', $result->filter('h2')->attr('class'));
        // class can contain number
        $this->assertStringContainsString('is-2', $result->filter('h2')->attr('class'));
    }

    public function testOffset(): void
    {
        // Does positive offset work?
        $result = $this->render("[h2]Text[/h2]", 1);
        $this->assertThat($result, new CC\CrawlerSelectorExists('h3'), 'Heading offset 1 should change h2 into h3.');
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('h3', 'Text'), 'Heading offset 1 should change h2 into h3.');

        // Does positive offset max out at h6?
        $result = $this->render("[h2]Text[/h2]", 5);
        $this->assertThat($result, new CC\CrawlerSelectorExists('h6'), 'Positive heading offset should max out at h6.');
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('h6', 'Text'), 'Positive heading offset should max out at h6.');

        // Does negative offset work?
        $result = $this->render("[h2]Text[/h2]", -1);
        $this->assertThat($result, new CC\CrawlerSelectorExists('h1'), 'Heading offset -1 should change h2 into h1.');
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('h1', 'Text'), 'Heading offset -1 should change h2 into h1.');

        // Does negative offset max out at h1?
        $result = $this->render("[h2]Text[/h2]", -2);
        $this->assertThat($result, new CC\CrawlerSelectorExists('h1'), 'Positive heading offset should max out at h1.');
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('h1', 'Text'), 'Positive heading offset should max out at h1.');
    }

    public function testNesting(): void
    {
        // Make sure that markup inside headings is rendered.
        $result = $this->render("[h2]Text [b]bold[/b] more text[/h2]");
        $this->assertThat($result, new CC\CrawlerSelectorExists('h2 > strong'), 'Markup inside a heading should be rendered.');

        // Make sure that headings are not wrapped in paragraphs
        $result = $this->render(<<<END
            Paragraph

            [h2]Text[/h2]

            Another paragraph
        END);
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('p > h2')), 'Headings cannot exist inside a paragraph.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('p + h2'), 'Headings can be a sibling of paragraph.');

        // Make sure that paragraphs don't exist in headings
        $result = $this->render(<<<END
            [h2]
                Paragraph

                Another paragraph
            [/h2]
        END);
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('h2 > p')), 'Paragraphs cannot exist inside headings.');


        $result = $this->render('[h2]Text [h3]Other[/h3][/h2]');
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('h2 > h3')), 'Headings cannot be nested.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('h2 + h3'), 'Headings cannot be nested.');
    }
}
