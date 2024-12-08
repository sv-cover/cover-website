<?php

namespace App\Tests\Markup;

use App\Markup\Markup;
use function App\Legacy\init;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class HiddenTagTest extends KernelTestCase
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

    public function testHiddenTag(): void
    {
        // Test valid syntax
        $result = $this->render("[h1]Text [b]Bold Text[/b][/h1]");

        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('h1')));
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('b')));

        // Test malformed syntax
        $result = $this->render("[h1]Text [b]Bold[/h1] Text[/b]");

        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('h1')));
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('b')));

        // Test [samenvatting] and [prive] for good measure
        $result = $this->render("[samenvatting]Text [b]Bold Text[/b][/samenvatting]");
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('b')));

        $result = $this->render("[prive]Text [b]Bold Text[/b][/prive]");
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('b')));
    }
}
