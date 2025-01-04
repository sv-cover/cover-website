<?php

namespace App\Tests\Markup;

use App\DataModel\DataModelSession;
use App\Markup\Markup;
use App\Legacy\Authentication\ConstantSessionProvider;
use App\Service\Authentication;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class MembersOnlyTest extends KernelTestCase
{
    protected Authentication $auth;
    protected Markup $markup;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->markup = static::getContainer()->get(Markup::class);
        $this->auth = static::getContainer()->get(Authentication::class);
    }

    protected function render(string $markup): Crawler
    {
        return new Crawler($this->markup->render($markup));
    }

    protected function login(): void
    {
        $_SERVER['REMOTE_ADDR'] = '0.0.0.0';
        $model = static::getContainer()->get(DataModelSession::class);
        $session = $model->create(1, 'test');
        $auth = new ConstantSessionProvider($session);
        $this->auth->setAuth($auth);
    }

    protected function logout(): void
    {
        $this->auth->setAuth(null);
    }

    public function testPublic(): void
    {
        $this->logout();

        // TODO SFY: legacy requirement
        $_SERVER['REQUEST_URI'] = 'example.com';

        // Test plain tag
        $result = $this->render('[membersonly]Text[/membersonly]');
        $this->assertStringNotContainsString('Text', $result->text(''), 'Content must not be rendered when not logged in.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('a[href*=login]'), 'Login link must be rendered when not logged in');

        // Test call to action
        $result = $this->render('[membersonly=Call to action]Text[/membersonly]');
        $this->assertStringNotContainsString('Text', $result->text(''), 'Content must not be rendered when not logged in.');
        $this->assertStringContainsString('Call to action', $result->text(), 'Call to action must be rendered if provided, when not logged in.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('a[href*=login]'), 'Login link must be rendered when not logged in');
    }

    public function testLoggedIn(): void
    {
        $this->login();

        $result = $this->render('[membersonly]Text[/membersonly]');

        $this->assertStringContainsString('Text', $result->text(), 'Content must be rendered when logged out.');
    }
}
