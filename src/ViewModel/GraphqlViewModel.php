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
use function array_merge as merge;

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

    /**
     * Dispatch event with query or mutation string to allow changing the query in event observers.
     *
     * The event name is 'hyva_graphql_render_before_' with the query identifier as a event suffix.
     * To change the query, use the following code to fetch the query string.
     *     $query = $observer->getData('gql_container')->getData('query')
     *
     * Then apply any required changes, and set it back on the event container:
     *     $observer->getData('gql_container')->setData('query', $query)
     *
     * To modify the query the utility class \Hyva\GraphqlViewModel\Model\GraphqlQueryEditor might be useful.
     *
     * @param string $queryIdentifier
     * @param string $query
     * @param mixed[] $eventParams
     * @return string
     */
    public function query(string $queryIdentifier, string $query, array $eventParams = []): string
    {
        $container = new DataObject(['query' => $query]);
        $params = merge($eventParams, ['gql_container' => $container]);
        $this->eventManager->dispatch('hyva_graphql_render_before_' . $queryIdentifier, $params);

        return $container->getData('query');
    }
}
