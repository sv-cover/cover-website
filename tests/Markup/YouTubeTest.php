<?php

namespace App\Tests\Markup;

use App\Markup\Markup;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class YouTubeTest extends KernelTestCase
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

    public function testYouTube(): void
    {
        $videoId = 'dQw4w9WgXcQ';

        // Test base tag
        $result = $this->render("[youtube=$videoId]");

        $this->assertThat($result, new CC\CrawlerSelectorExists('iframe'));
        $this->assertStringContainsString($videoId, $result->filter('iframe')->attr('src'));
    }
}
