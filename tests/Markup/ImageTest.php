<?php

namespace App\Tests\Markup;

use App\Markup\Markup;
use function App\Legacy\init;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class ImageTest extends KernelTestCase
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

    public function testImage(): void
    {
        $imageUrl = 'https://www.svcover.nl/images/cover_logo.png';

        // Test base tag
        $result = $this->render("[img=$imageUrl]");

        $this->assertThat($result, new CC\CrawlerSelectorExists('img'));
        $this->assertEquals($imageUrl, $result->filter('img')->attr('src'));

        // Test class tag
        $result = $this->render("[img.is-rounded.is-2=$imageUrl]");
        $this->assertThat($result, new CC\CrawlerSelectorExists('img'));
        $this->assertEquals($imageUrl, $result->filter('img')->attr('src'));
        $this->assertStringContainsString('is-rounded', $result->filter('img')->attr('class'));
        // class can contain number
        $this->assertStringContainsString('is-2', $result->filter('img')->attr('class'));
    }
}
