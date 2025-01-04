<?php

namespace App\Tests\Markup;

use App\Markup\Markup;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class CodeTest extends KernelTestCase
{
    protected Markup $markup;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->markup = static::getContainer()->get(Markup::class);
    }

    protected function render(string $markup): Crawler
    {
        return new Crawler($this->markup->render($markup));
    }

    public function testCode(): void
    {
        $result = $this->render("[code]Text[/code]");

        $this->assertThat($result, new CC\CrawlerSelectorExists('pre'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('pre', 'Text'));
    }

    public function testNesting(): void
    {
        // Make sure that markup inside code is not rendered.
        $result = $this->render("[code]Text [b]bold[/b] more text[/code]");
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('strong')), 'Markup inside code should not be rendered.');

        $result = $this->render(<<<END
            Paragraph

            [code]Text[/code]

            Another paragraph
        END);
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('p > pre')), 'Code cannot exist inside a paragraph.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('p + pre'), 'Code can be a sibling of paragraph.');

        $result = $this->render(<<<END
            [code]
                Paragraph

                Another paragraph
            [/code]
        END);
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('pre > p')), 'Paragraphs cannot exist inside code.');
    }

    public function testEscape(): void
    {
        $result = $this->render(<<<END
            [code]
                <script>alert('text')</script>
            [/code]
        END);
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('pre > script')), 'HTML inside code must be escaped.');
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('pre', "<script>alert('text')</script>"), 'HTML inside code must be escaped.');
    }

    public function testTrim(): void
    {
        $result = $this->render(<<<END
            [code]
                Text
            [/code]
        END);
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('pre', 'Text'), 'Code contents should be trimmed.');
    }
}
