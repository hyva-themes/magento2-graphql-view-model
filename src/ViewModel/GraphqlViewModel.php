<?php declare(strict_types=1);
/**
 * Hyvä Themes - https://hyva.io
 * Copyright © Hyvä Themes. All rights reserved.
 * See LICENSE.md for license details
 */

namespace Hyva\GraphqlViewModel\ViewModel;

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
        return $this->toString($this->toAst($name, $query));
    }

    private function toAst(string $name, string $query): \GraphQL\Language\AST\DocumentNode
    {
        $source = new \GraphQL\Language\Source($query);
        $ast    = \GraphQL\Language\Parser::parse($source);

        $this->eventManager->dispatch('hyva_graphql_query_before_render', ['query' => $ast, 'name' => $name]);

        return $ast;
    }

    private function toString(\GraphQL\Language\AST\DocumentNode $ast): string
    {
        return \GraphQL\Language\Printer::doPrint($ast);
    }
}
