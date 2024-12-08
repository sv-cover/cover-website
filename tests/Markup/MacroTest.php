<?php

namespace App\Tests\Markup;

use App\Markup\Markup;
use function App\Legacy\init;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class MacroTest extends KernelTestCase
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

    public function testCommittee(): void
    {
        $router = static::getContainer()->get(UrlGeneratorInterface::class);

        // Test simple case
        $result = $this->render('[[committee(ActiviTee)]]');

        $activiteeUrl = $router->generate('committees.single', ['slug' => 'activitee']);
        $this->assertThat($result, new CC\CrawlerSelectorExists('p > a'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p > a', 'ActiviTee'));
        $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('p > a', 'href', $activiteeUrl));

        // Test non-alphanumeric names
        $result = $this->render('[[committee(AC/DCee)]]');

        $acdceeUrl = $router->generate('committees.single', ['slug' => 'acdcee']);
        $this->assertThat($result, new CC\CrawlerSelectorExists('p > a'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p > a', 'AC/DCee'));
        $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('p > a', 'href', $acdceeUrl));

        // What if we provide a slug?
        $result = $this->render('[[committee(webcie)]]');

        $webcieUrl = $router->generate('committees.single', ['slug' => 'webcie']);
        $this->assertThat($result, new CC\CrawlerSelectorExists('p > a'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p > a', 'AC/DCee Admins'));
        $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('p > a', 'href', $webcieUrl));

        // What if the committee doesn't exist??
        $result = $this->render('[[committee(NonExisTee)]]');

        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('p > a')));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p', 'NonExisTee'));
    }

    public function testCommissie(): void
    {
        // It's just an alias, only need to test it works.
        $result = $this->render('[[commissie(ActiviTee)]]');

        $router = static::getContainer()->get(UrlGeneratorInterface::class);
        $activiteeUrl = $router->generate('committees.single', ['slug' => 'activitee']);
        $this->assertThat($result, new CC\CrawlerSelectorExists('p > a'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p > a', 'ActiviTee'));
        $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('p > a', 'href', $activiteeUrl));
    }

    public function testMalformed(): void
    {
        $result = $this->render('[[nonExistentMacro()]]');
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p', '[[nonExistentMacro()]]'));
    }
}
