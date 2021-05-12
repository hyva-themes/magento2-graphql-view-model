<?php declare(strict_types=1);
/**
 * Hyvä Themes - https://hyva.io
 * Copyright © Hyvä Themes. All rights reserved.
 * See LICENSE.md for license details
 */

namespace Hyva\GraphqlViewModel\ViewModel;

use Magento\Framework\Event\ManagerInterface as EventManager;
use PHPUnit\Framework\TestCase;

class GraphqlViewModelTest extends TestCase
{
    public function testReturnsQueryString(): void
    {
        $query = '{
  __type(name: "Customer") {
    name
  }
}';
        $dummyEventDispatcher = $this->createMock(EventManager::class);
        $sut = new GraphqlViewModel($dummyEventDispatcher);
        $this->assertSame($query, trim($sut->query('test', $query)));
    }
}
