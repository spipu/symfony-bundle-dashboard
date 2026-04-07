<?php

declare(strict_types=1);

namespace Spipu\DashboardBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Spipu\CoreBundle\Tests\WebTestCase;
use Spipu\DashboardBundle\Service\DashboardControllerService;
use Spipu\UiBundle\Tests\UiWebTestCaseTrait;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(DashboardControllerService::class)]
class UserInterfaceTest extends WebTestCase
{
    use UiWebTestCaseTrait;

    public function testMain(): void
    {
        $client = static::createClient();

        $this->adminLogin($client, 'Admin');

        $crawler = $client->clickLink('Dashboard');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertSame("Dashboard", $crawler->filter('h1')->text());
    }
}
