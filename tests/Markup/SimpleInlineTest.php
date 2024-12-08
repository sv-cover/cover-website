<?php

namespace App\Tests\Markup;

use App\Markup\Markup;
use function App\Legacy\init;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class SimpleInlineTest extends KernelTestCase
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

    public function testI(): void
    {
        $result = $this->render('[i]Text[/i]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('p > em'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p > em', 'Text'));
    }

    public function testB(): void
    {
        $result = $this->render('[b]Text[/b]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('p > strong'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p > strong', 'Text'));
    }

    public function testU(): void
    {
        $result = $this->render('[u]Text[/u]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('p > .is-underlined'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p > .is-underlined', 'Text'));
    }

    public function testS(): void
    {
        $result = $this->render('[s]Text[/s]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('p > s'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p > s', 'Text'));
    }

    public function testSmall(): void
    {
        $result = $this->render('[small]Text[/small]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('p > small'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p > small', 'Text'));
    }

    public function testHl(): void
    {
        $result = $this->render('[hl]Text[/hl]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('p > mark'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p > mark', 'Text'));
    }
}
