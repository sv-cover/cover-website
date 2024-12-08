<?php

namespace App\Tests\Markup;

use App\Markup\Markup;
use function App\Legacy\init;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class VideoTest extends KernelTestCase
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

    public function testVideo(): void
    {
        $videoUrl = 'https://archive.org/download/BigBuckBunny_124/Content/big_buck_bunny_720p_surround.mp4';
        $thumbnailUrl = 'https://www.svcover.nl/images/cover_logo.png';

        // Test base tag
        $result = $this->render("[video=$videoUrl]");

        $this->assertThat($result, new CC\CrawlerSelectorExists('video'));
        $this->assertEquals($videoUrl, $result->filter('video')->attr('src'));

        // Test thumbnail
        $result = $this->render("[video=$videoUrl thumbnail=$thumbnailUrl]");

        $this->assertThat($result, new CC\CrawlerSelectorExists('video'));
        $this->assertEquals($thumbnailUrl, $result->filter('video')->attr('poster'));
    }
}
