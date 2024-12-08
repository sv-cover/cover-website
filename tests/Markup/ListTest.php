<?php

namespace App\Tests\Markup;

use App\Markup\Markup;
use function App\Legacy\init;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class ListTest extends KernelTestCase
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

    public function testOrdered(): void
    {
        // Test base tag
        $result = $this->render(<<<END
            [ol]
                [li]item 1[/li]
                [li]item 2[/li]
                [li]item 3[/li]
            [/ol]
        END);

        $this->assertThat($result, new CC\CrawlerSelectorExists('ol'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('ol > li:nth-child(1)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('ol > li:nth-child(2)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('ol > li:nth-child(3)'));
    }

    public function testUnordered(): void
    {
        // Test base tag
        $result = $this->render(<<<END
            [ul]
                [li]item 1[/li]
                [li]item 2[/li]
                [li]item 3[/li]
            [/ul]
        END);

        $this->assertThat($result, new CC\CrawlerSelectorExists('ul'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('ul > li:nth-child(1)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('ul > li:nth-child(2)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('ul > li:nth-child(3)'));
    }

    public function testMalformed(): void
    {
        // Test base tag
        $result = $this->render(<<<END
            [ul]
                item 1
                [li]item 2[/li]
                [li]item 3[/li]
                item 4
                [li]item 5[/li]
                item 6
            [/ul]
        END);

        $this->assertThat($result, new CC\CrawlerSelectorExists('ul'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('ul > li:nth-child(1)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('ul > li:nth-child(2)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('ul > li:nth-child(3)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('ul > li:nth-child(4)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('ul > li:nth-child(5)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('ul > li:nth-child(6)'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('ul > li:nth-child(1)', 'item 1'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('ul > li:nth-child(2)', 'item 2'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('ul > li:nth-child(3)', 'item 3'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('ul > li:nth-child(4)', 'item 4'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('ul > li:nth-child(5)', 'item 5'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('ul > li:nth-child(6)', 'item 6'));
    }

    public function testNesting(): void
    {
        // Make sure that markup inside headings is rendered.
        $result = $this->render("[ul][li]Text [b]bold[/b] more text[/li][/ul]");
        $this->assertThat($result, new CC\CrawlerSelectorExists('ul > li strong'), 'Markup inside a list item should be rendered.');

        // Make sure that headings are not wrapped in paragraphs
        $result = $this->render(<<<END
            Paragraph

            [ul][li]item 1[/li][/ul]

            Another paragraph
        END);
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('p > ul')), 'Lists cannot exist inside a paragraph.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('p + ul'), 'Lists can be a sibling of paragraph.');

        // Make sure that paragraphs don't exist in headings
        $result = $this->render(<<<END
            [ul]
                [li]
                    Paragraph

                    Another paragraph
                [/li]
            [/ul]
        END);
        $this->assertThat($result, new CC\CrawlerSelectorExists('ul > li > p'), 'Paragraphs can exist inside list items.');
    }
}
