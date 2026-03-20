<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\AST;
use function is_array;
class Query_Aggregate_Function_Detector_Tree_Walker extends Query\Tree_Walker_Adapter
{
    public const HINT_HAS_AGGREGATE_FUNCTION = self::class . '::HINT_HAS_AGGREGATE_FUNCTION';
    public function walk_select_statement(AST\Select_Statement $select_statement): void
    {
        $this->walk_node($select_statement->select_clause);
    }
    /**
     * @param mixed $node
     */
    public function walk_node($node): void
    {
        if (!$node instanceof AST\Node) {
            return;
        }
        if ($node instanceof AST\Subselect) {
            return;
        }
        if ($this->is_aggregate_function($node)) {
            $this->mark_aggregate_function_found();
            return;
        }
        foreach ((array) $node as $property) {
            if ($property instanceof AST\Node) {
                $this->walk_node($property);
            }
            if (is_array($property)) {
                foreach ($property as $property_value) {
                    $this->walk_node($property_value);
                }
            }
            if ($this->was_aggregate_function_found()) {
                return;
            }
        }
    }
    private function is_aggregate_function(AST\Node $node): bool
    {
        return $node instanceof AST\Functions\Avg_Function || $node instanceof AST\Functions\Count_Function || $node instanceof AST\Functions\Max_Function || $node instanceof AST\Functions\Min_Function || $node instanceof AST\Functions\Sum_Function || $node instanceof AST\Aggregate_Expression;
    }
    private function mark_aggregate_function_found(): void
    {
        $this->_get_query()->set_hint(self::HINT_HAS_AGGREGATE_FUNCTION, true);
    }
    private function was_aggregate_function_found(): bool
    {
        return $this->_get_query()->has_hint(self::HINT_HAS_AGGREGATE_FUNCTION);
    }
}