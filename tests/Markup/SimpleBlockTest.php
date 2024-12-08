<?php

namespace App\Tests\Markup;

use App\Markup\Markup;
use function App\Legacy\init;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class SimpleBlockTest extends KernelTestCase
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

    public function testBox(): void
    {
        $result = $this->render('[box]Text[/box]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('.box > p'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('.box > p', 'Text'));
    }

    public function testCenter(): void
    {
        $result = $this->render('[center]Text[/center]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('.has-text-centered > p'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('.has-text-centered > p', 'Text'));
    }
}
