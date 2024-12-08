<?php

namespace App\Tests\Markup;

use App\Markup\Markup;
use App\Legacy\Authentication\ConstantSessionProvider;
use App\Service\Authentication;
use App\Service\Database;
use function App\Legacy\init;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class PublicOnlyTest extends KernelTestCase
{
    protected Authentication $auth;
    protected Database $db;
    protected Markup $markup;

    protected function setUp(): void
    {
        self::bootKernel();
        init(self::$kernel);
        $this->markup = static::getContainer()->get(Markup::class);
        $this->auth = static::getContainer()->get(Authentication::class);
        $this->db = static::getContainer()->get(Database::class);
    }

    protected function render(string $markup): Crawler
    {
        return new Crawler($this->markup->render($markup));
    }

    protected function login(): void
    {
        // TODO SFY: legacy requirement
        $_SERVER['REMOTE_ADDR'] = '0.0.0.0';
        $session = $this->db->getModel('DataModelSession')->create(1, 'test');
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

        $result = $this->render('[publiconly]Text[/publiconly]');

        $this->assertStringContainsString('Text', $result->text(), 'Content must be rendered when not logged in.');
    }

    public function testLoggedIn(): void
    {
        $this->login();

        $result = $this->render('[publiconly]Text[/publiconly]');

        $this->assertStringNotContainsString('Text', $result->text(''), 'Content must not be rendered when logged in.');
    }
}
