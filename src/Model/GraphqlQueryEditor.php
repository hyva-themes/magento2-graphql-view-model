<?php declare(strict_types=1);
/**
 * Hyvä Themes - https://hyva.io
 * Copyright © Hyvä Themes. All rights reserved.
 * See LICENSE.md for license details
 */

namespace Hyva\GraphqlViewModel\Model;

use function array_keys as keys;
use function array_slice as slice;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\StringValueNode;

class GraphqlQueryEditor
{
    /**
     * Returns the fist operations node since Magento only supports single operation GraphQL requests.
     *
     * @param DocumentNode $ast
     * @return OperationDefinitionNode|null
     */
    private function getFirstOperationNode(DocumentNode $ast): ?OperationDefinitionNode
    {
        foreach ($ast->definitions as $definition) {
            if ($definition->kind === NodeKind::OPERATION_DEFINITION) {
                return $definition;
            }
        }
        return null;
    }

    /**
     * Add GraphQL query or mutation field at given path.
     *
     * Example:
     * $query = $editor->addFieldIn($query, ['products', 'items', 'small_image'], 'url_webp');
     *
     * @param string $query
     * @param array $path
     * @param string $field
     * @return string
     */
    public function addFieldIn(string $query, array $path, string $field): string
    {
        $ast       = \GraphQL\Language\Parser::parse(new \GraphQL\Language\Source($query));
        $operation = $this->getFirstOperationNode($ast);
        $target    = $this->getFieldSelection($operation, $path);
        foreach (preg_split('/\s+/', $field) as $new) {
            if (!$this->findNodeSelectionByName($target, $new)) {
                $this->createNodeSelectionIn($target, $new);
            }
        }

        return \GraphQL\Language\Printer::doPrint($ast);
    }

    /**
     * Add or set argument to value at given path for GraphQL query or mutation.
     *
     * Examples:
     * $query = $editor->addArgumentIn($query, ['products', 'filter', 'name'], 'match', 'Tank');
     * $query = $editor->addArgumentIn($query, ['products'], 'pageSize', 2);
     *
     * @param string $query
     * @param array $path
     * @param string $key
     * @param string|int|float|bool|null $value
     * @return string
     */
    public function addArgumentIn(string $query, array $path, string $key, $value): string
    {
        $ast                = \GraphQL\Language\Parser::parse(new \GraphQL\Language\Source($query));
        $operationFieldName = array_shift($path); // e.g. products
        $argumentName       = array_shift($path); // e.g. filter
        $operationField     = $this->getFieldSelection($this->getFirstOperationNode($ast), [$operationFieldName]);
        $argument           = $argumentName ? $this->getArgument($operationField, $argumentName) : $operationField;
        $target             = $this->getArgumentField($argument, $path);
        $this->setArgumentFieldIn($target, $key, $value);

        return \GraphQL\Language\Printer::doPrint($ast);
    }

    private function getArgument(FieldNode $target, string $name): ArgumentNode
    {
        return $this->findArgumentByName($target, $name) ?? $this->createArgument($target, $name);
    }

    private function findArgumentByName(FieldNode $target, string $name): ?ArgumentNode
    {
        foreach ($target->arguments as $argument) {
            if ($argument->name->value === $name) {
                return $argument;
            }
        }
        return null;
    }

    private function createArgument(FieldNode $target, string $name): ArgumentNode
    {
        $argument = new ArgumentNode([
            'name'  => new NameNode(['value' => $name]),
            'value' => new ObjectValueNode(['fields' => new NodeList([])]),
        ]);

        $target->arguments[$this->nextIndex($target->arguments)] = $argument;
        return $argument;
    }

    /**
     * @param ObjectFieldNode|ArgumentNode $node
     * @param string[] $path
     * @return ObjectFieldNode|FieldNode
     */
    private function getArgumentField(Node $node, array $path): Node
    {
        if (empty($path)) {
            return $node;
        }
        $field = $this->findObjectFieldByName($node, $path[0]) ?? $this->createObjectFieldIn($node, $path[0]);

        return $this->getArgumentField($field, slice($path, 1));
    }

    /**
     * @param FieldNode|OperationDefinitionNode $node
     * @param string[] $path
     * @return Node
     */
    private function getFieldSelection(Node $node, array $path): SelectionNode
    {
        if (empty($path)) {
            return $node;
        }

        $field = $this->findNodeSelectionByName($node, $path[0]) ?? $this->createNodeSelectionIn($node, $path[0]);

        return $this->getFieldSelection($field, slice($path, 1));
    }

    /**
     * @param ObjectFieldNode|ArgumentNode $node
     * @param string $name
     * @return ObjectFieldNode|null
     */
    private function findObjectFieldByName(Node $node, string $name): ?ObjectFieldNode
    {
        $children = $node->value->fields ?? [];
        foreach ($children as $field) {
            if ($field->name && $field->name->value == $name) {
                return $field;
            }
        }
        return null;
    }

    /**
     * @param FieldNode|OperationDefinitionNode $node
     * @param string $name
     * @return Node|null
     */
    private function findNodeSelectionByName(Node $node, string $name): ?Node
    {
        $children = is_object($node->selectionSet) ? $node->selectionSet->selections : [];
        foreach ($children as $field) {
            if ($this->isFieldNodeMatch($field, $name) || $this->isFragmentMatch($field, $name)) {
                return $field;
            }
        }
        return null;
    }

    private function isFieldNodeMatch(Node $node, string $name): bool
    {
        return $node instanceof FieldNode &&
            $node->name->value === $name;
    }

    private function isFragmentMatch(Node $node, string $name): bool
    {
        return $node instanceof InlineFragmentNode &&
            $this->isFragmentIdentifier($name) &&
            $node->typeCondition->name->value === substr($name, 7);
    }

    private function isFragmentIdentifier(string $name): bool
    {
        return substr($name, 0, 7) === '... on ';
    }

    private function createNodeSelectionIn(Node $node, string $name): Node
    {
        return $this->isFragmentIdentifier($name)
            ? $this->createFragmentSelectionIn($node, $name)
            : $this->createFieldSelectionIn($node, $name);
    }

    /**
     * @param FieldNode|OperationDefinitionNode $node
     * @param string $name
     * @return FieldNode
     */
    private function createFieldSelectionIn(Node $node, string $name): FieldNode
    {
        $field = new FieldNode([
            'name'         => new NameNode(['value' => $name]),
            'arguments'    => new NodeList([]),
            'directives'   => new NodeList([]),
            'selectionSet' => new SelectionSetNode(['selections' => new NodeList([])]),
        ]);

        $node->selectionSet->selections[$this->nextIndex($node->selectionSet->selections)] = $field;

        return $field;
    }

    private function createFragmentSelectionIn(Node $node, string $name): InlineFragmentNode
    {
        $fragment = new InlineFragmentNode([
            'typeCondition' => new NamedTypeNode(['name' => new NameNode(['value' => substr($name, 7)])]),
            'directives'    => new NodeList([]),
            'selectionSet'  => new SelectionSetNode(['selections' => new NodeList([])]),
        ]);

        $node->selectionSet->selections[$this->nextIndex($node->selectionSet->selections)] = $fragment;

        return $fragment;
    }

    /**
     * @param ObjectFieldNode $node
     * @param string $name
     * @return ObjectFieldNode
     */
    private function createObjectFieldIn(Node $node, string $name): ObjectFieldNode
    {
        $field = new ObjectFieldNode([
            'name'  => new NameNode(['value' => $name]),
            'value' => new ObjectValueNode(['fields' => new NodeList([])]),
        ]);

        $node->value->fields[$this->nextIndex($node->value->fields)] = $field;

        return $field;
    }

    /**
     * @param ObjectFieldNode|FieldNode $target
     * @param string $key
     * @param string|int|bool|float|null $value
     */
    private function setArgumentFieldIn(Node $target, string $key, $value): void
    {
        /** @see \GraphQL\Language\AST\ValueNode */
        $types = [
            'string'  => StringValueNode::class,
            'integer' => IntValueNode::class,
            'double'  => FloatValueNode::class,
            'NULL'    => NullValueNode::class,
            'boolean' => BooleanValueNode::class,
        ];
        $type  = gettype($value);
        if (!isset($types[$type])) {
            throw new \RuntimeException(sprintf('Unable to set GraphQL argument value type "%s"', $type));
        }

        $valueArg = in_array($type, ['integer', 'double'], true)
            ? (string) $value
            : $value;

        $valueInstance        = $this->getArgumentValueContainerFor($target, $key);
        $valueInstance->value = new $types[$type](['value' => $valueArg]);
    }

    private function getArgumentValueContainerFor(Node $target, string $name): Node
    {
        if ($target instanceof ObjectFieldNode || $target instanceof ArgumentNode) {
            $valueInstance = $this->findObjectFieldByName($target, $name) ?? $this->createObjectFieldIn($target, $name);
        } elseif ($target instanceof FieldNode) {
            $valueInstance = $this->findArgumentByName($target, $name) ?? $this->createArgument($target, $name);
        } else {
            throw new \RuntimeException(sprintf('Unsupported target for argument value: "%s"', get_class($target)));
        }

        return $valueInstance;
    }

    /**
     * @param array|\Iterator $array
     * @return int
     */
    private function nextIndex($array): int
    {
        $keys = keys(is_array($array) ? $array : iterator_to_array($array));
        return empty($keys)
            ? 0
            : max($keys) + 1;
    }
}
