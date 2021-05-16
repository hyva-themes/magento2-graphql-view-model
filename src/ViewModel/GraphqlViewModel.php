<?php declare(strict_types=1);
/**
 * Hyvä Themes - https://hyva.io
 * Copyright © Hyvä Themes. All rights reserved.
 * See LICENSE.md for license details
 */

namespace Hyva\GraphqlViewModel\ViewModel;

use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class GraphqlViewModel implements ArgumentInterface
{
    /**
     * @var EventManager
     */
    private $eventManager;

    public function __construct(EventManager $eventManager)
    {
        $this->eventManager = $eventManager;
    }

    public function query(string $name, string $query): string
    {
        $container = new DataObject(['query' => $query]);
        $params    = ['gql_container' => $container, 'name' => $name];
        $this->eventManager->dispatch('hyva_graphql_query_before_render', $params);

        return $container->getData('query');
    }
}
