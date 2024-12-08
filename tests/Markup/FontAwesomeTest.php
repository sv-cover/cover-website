<?php

namespace App\Tests\Markup;

use App\Markup\Markup;
use function App\Legacy\init;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class FontAwesomeTest extends KernelTestCase
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

    public function testFontAwesome(): void
    {
        // Test base tag
        $result = $this->render('[fontawesome icon="fa-phone"]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('i'));
        $this->assertStringContainsString('fa-phone', $result->filter('i')->attr('class'));
        $this->assertEquals('true', $result->filter('i')->attr('aria-hidden'));

        // Test labelled tag
        $result = $this->render('[fontawesome icon="fa-phone" label="Phone"]');
        $this->assertStringContainsString('fa-phone', $result->filter('i')->attr('class'));
        $this->assertEquals(null, $result->filter('i')->attr('aria-hidden'));
        $this->assertEquals('Phone', $result->filter('i')->attr('aria-label'));
    }
}
