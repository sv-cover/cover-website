<?php

namespace App\Tests\Markup;

use App\Markup\Markup;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class QuoteTest extends KernelTestCase
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

    public function testQuote(): void
    {
        // Test base tag
        $result = $this->render('[quote]Hello![/quote]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('blockquote'));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('blockquote', 'Hello!'));

        // Test labelled tag
        $result = $this->render('[quote=Julius Caesar]I came, I saw, I doomscrolled[/quote]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('blockquote'));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('blockquote', 'I came, I saw, I doomscrolled'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('p'));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('p', 'Julius Caesar'));
    }
}
