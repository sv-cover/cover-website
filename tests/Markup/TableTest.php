<?php

namespace App\Tests\Markup;

use App\Markup\Markup;
use function App\Legacy\init;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class TableTest extends KernelTestCase
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

    public function testTable(): void
    {
        // Test base tag
        // Test empty cell on last row
        $result = $this->render(<<<END
            [table]
            || Text 1,1 || Text 1,2 || Text 1,3 ||
            || Text 2,1 || Text 2,2 || Text 2,3 ||
            || Text 3,1 || || Text 3,3 ||
            [/table]
        END);

        $this->assertThat($result, new CC\CrawlerSelectorExists('table'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(1)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(2)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(3)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(1) > td:nth-child(1)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(1) > td:nth-child(2)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(1) > td:nth-child(3)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(2) > td:nth-child(1)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(2) > td:nth-child(2)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(2) > td:nth-child(3)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(3) > td:nth-child(1)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(3) > td:nth-child(2)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(3) > td:nth-child(3)'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('table > tr:nth-child(1) > td:nth-child(1)', 'Text 1,1'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('table > tr:nth-child(1) > td:nth-child(2)', 'Text 1,2'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('table > tr:nth-child(1) > td:nth-child(3)', 'Text 1,3'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('table > tr:nth-child(2) > td:nth-child(1)', 'Text 2,1'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('table > tr:nth-child(2) > td:nth-child(2)', 'Text 2,2'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('table > tr:nth-child(2) > td:nth-child(3)', 'Text 2,3'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('table > tr:nth-child(3) > td:nth-child(1)', 'Text 3,1'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('table > tr:nth-child(3) > td:nth-child(2)', ''));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('table > tr:nth-child(3) > td:nth-child(3)', 'Text 3,3'));
    }

    public function testHeadings(): void
    {
        // Test variations of valid heading separators
        $result = $this->render(<<<END
            [table]
            |^ Text 1,1 |^ Text 1,2 ^^ Text 1,3 ^^
            ^^ Text 2,1 || Text 2,2 || Text 2,3 ||
            |^ Text 3,1 || Text 3,2 || Text 3,3 ||
            [/table]
        END);

        $this->assertThat($result, new CC\CrawlerSelectorExists('table'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(1)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(2)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(3)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(1) > th:nth-child(1)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(1) > th:nth-child(2)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(1) > th:nth-child(3)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(2) > th:nth-child(1)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(2) > td:nth-child(2)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(2) > td:nth-child(3)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(3) > th:nth-child(1)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(3) > td:nth-child(2)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(3) > td:nth-child(3)'));
    }

    public function testMalformed(): void
    {
        // Test malformed table syntax
        // NB: just because it doesn't break, doesn't mean the behaviour should make sense.
        $result = $this->render(<<<END
            [table]
            || Text 1,1 || Text 1,2 || Text 1,3 ||
            || Text 2,1 || Text 2,2 ||
            || Text 3,1 || Text 3,2 || Text 3,3 ||
            Text 4,1 ||
            || Text 5,1 || Text 5,2 || Text 5,3 ||
            Text 6,1
            || Text 6,2 || Text 6,3 ||
            || Text 7,1
            || Text 7,2 || Text 7,3 ||
            [/table]
        END);

        $this->assertThat($result, new CC\CrawlerSelectorExists('table'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(1)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(2)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(3)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(4)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(5)'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(2) > td:nth-child(3)'), 'Table rows are not balanced.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(1) > td:nth-child(1)'), 'Missing opening tag not handled.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(1) > td:nth-child(1)'), 'Missing opening tag not handled.');
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('table > tr:nth-child(4) > td:nth-child(1)', 'Text 4,1'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('table > tr:nth-child(6) > td:nth-child(1)', 'Text 6,1'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('table > tr:nth-child(6) > td:nth-child(2)', 'Text 6,2'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('table > tr:nth-child(6) > td:nth-child(3)', 'Text 6,3'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('table > tr:nth-child(7) > td:nth-child(1)', 'Text 7,1'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('table > tr:nth-child(7) > td:nth-child(2)', 'Text 7,2'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('table > tr:nth-child(7) > td:nth-child(3)', 'Text 7,3'));
    }

    public function testNesting(): void
    {
        // Make sure that markup inside cells is rendered.

        $result = $this->render(<<<END
            [table]
            || Text 1,1 || Text 1,2 ||
            || Text [b]bold[/b] 2,1 || Text 2,2 ||
            [/table]
        END);

        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(2) > td:nth-child(1) strong'), 'Markup inside table cells should be rendered.');

        $result = $this->render(<<<END
            [table]
            || Text 1,1 || Text 1,2 ||
            ||
                Paragraph

                Another paragraph
            || Text 2,2 ||
            [/table]
        END);

        $this->assertThat($result, new CC\CrawlerSelectorExists('table > tr:nth-child(2) > td:nth-child(1) > p + p'), 'Paragraphs can exist inside table cells.');
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('table > tr:nth-child(2) > td:nth-child(2)', 'Text 2,2'), 'Paragraphs inside table cells should not affect parsing of neighbouring cells.');
    }
}
