<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query;

use function array_key_last;
use function count;
use function is_int;
use Php_Stan\Type\Constant\Constant_Array_Type_Builder;
use Php_Stan\Type\Constant\Constant_Integer_Type;
use Php_Stan\Type\Constant\Constant_String_Type;
use Php_Stan\Type\Null_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
use Php_Stan\Type\Void_Type;
/**
 * QueryResultTypeBuilder helps building the result type of a query
 *
 * Like Doctrine\ORM\Query\ResultSetMapping, but for static typing concerns
 */
final class Query_Result_Type_Builder
{
    private bool $select_query = false;
    /**
     * Whether the result is an array shape or a single entity or NEW object
     *
     */
    private bool $is_shape = false;
    /**
     * Map from selected entity aliases to entity types
     *
     * Example: "e" is an entity alias in "SELECT e FROM Entity e"
     *
     * @var array<array-key,Type>
     */
    private array $entities = [];
    /**
     * Map from selected entity alias to result alias
     *
     * Example: "hello" is a result alias in "SELECT e AS hello FROM Entity e"
     *
     * @var array<array-key,string>
     */
    private array $entity_result_aliases = [];
    /**
     * Map from selected scalar result alias to scalar type
     *
     * @var array<array-key,Type>
     */
    private array $scalars = [];
    /**
     * Map from selected NEW objcet result alias to NEW object type
     *
     * @var array<array-key,Type>
     */
    private array $new_objects = [];
    private Type $indexed_by;
    public function __construct()
    {
        $this->indexed_by = new Null_Type();
    }
    public function set_select_query(): void
    {
        $this->select_query = true;
    }
    public function is_select_query(): bool
    {
        return $this->select_query;
    }
    public function add_entity(string $entity_alias, Type $type, ?string $result_alias): void
    {
        $this->entities[$entity_alias] = $type;
        if ($result_alias === null) {
            return;
        }
        $this->entity_result_aliases[$entity_alias] = $result_alias;
        $this->is_shape = true;
    }
    /**
     * @return array<array-key,Type>
     */
    public function get_entities(): array
    {
        return $this->entities;
    }
    /**
     * @param array-key $alias
     */
    public function add_scalar($alias, Type $type): void
    {
        $this->scalars[$alias] = $type;
        $this->is_shape = true;
    }
    /**
     * @return array<int,Type>
     */
    public function get_scalars(): array
    {
        return $this->scalars;
    }
    /**
     * @param array-key $alias
     */
    public function add_new_object($alias, Type $type): void
    {
        $this->new_objects[$alias] = $type;
        if (count($this->new_objects) <= 1) {
            return;
        }
        $this->is_shape = true;
    }
    /**
     * @return array<int,Type>
     */
    public function get_new_objects(): array
    {
        return $this->new_objects;
    }
    public function get_result_type(): Type
    {
        // There are a few special cases here, depending on what is selected:
        //
        // - Just one entity:
        //   - Without alias: Result is the entity
        //   - With an alias: array{alias: entity}
        //
        // - One NEW object, with any entities:
        //   - Result is the NEW object (entities are ignored, alias is ignored)
        //
        // - NEW objects and/or scalars:
        //   - Result is an array shape with one element per NEW object and
        //     scalar. Keys are the aliases or an incremental numeric index
        //     (scalars start at 1, NEW objects are 0). NEW objects can shadow
        //     scalars.
        //
        // - Multiple arbitrarily joint entities:
        //   - Without aliases: Result is an alternation of the entities,
        //     like Entity1|Entity2|EntityN
        //   - With aliases: Result is an alternation of array shapes,
        //     like array{alias: Entity1}|array{alias: Entity2}|...
        //
        // - One or more entities plus scalars and NEW objects:
        //   - Result is an alternation of intersections of the shapes described
        //     in  "Multiple arbitrarily joint entities" and "NEW objects and/or
        //     scalars". Entities without a alias are at offset 0 in the shape.
        // We use Void for non-select queries. This is used as a marker by the
        // DynamicReturnTypeExtension for Query::getResult() and variants.
        if (!$this->select_query) {
            return new Void_Type();
        }
        // If there is a single NEW object and no scalars, the result is the
        // NEW object. This ignores any entity.
        // https://github.com/doctrine/orm/blob/v2.7.3/lib/Doctrine/ORM/Internal/Hydration/ObjectHydrator.php#L566-L570
        if (count($this->new_objects) === 1 && count($this->scalars) === 0) {
            foreach ($this->new_objects as $new_objects) {
                return $new_objects;
            }
        }
        if (count($this->entities) === 0) {
            $builder = Constant_Array_Type_Builder::create_empty();
            $this->add_non_entities_to_shape_result($builder);
            return $builder->get_array();
        }
        $alternatives = [];
        $last_entity_alias = array_key_last($this->entities);
        foreach ($this->entities as $entity_alias => $entity_type) {
            if (!$this->is_shape) {
                $alternatives[] = $entity_type;
                continue;
            }
            $result_alias = $this->entity_result_aliases[$entity_alias] ?? 0;
            $offset_type = $this->resolve_offset_type($result_alias);
            $builder = Constant_Array_Type_Builder::create_empty();
            $builder->set_offset_value_type($offset_type, $entity_type);
            if ($entity_alias === $last_entity_alias) {
                $this->add_non_entities_to_shape_result($builder);
            }
            $alternatives[] = $builder->get_array();
        }
        return Type_Combinator::union(...$alternatives);
    }
    private function add_non_entities_to_shape_result(Constant_Array_Type_Builder $builder): void
    {
        foreach ($this->scalars as $alias => $scalar_type) {
            $offset_type = $this->resolve_offset_type($alias);
            $builder->set_offset_value_type($offset_type, $scalar_type);
        }
        foreach ($this->new_objects as $alias => $new_object_type) {
            $offset_type = $this->resolve_offset_type($alias);
            $builder->set_offset_value_type($offset_type, $new_object_type);
        }
    }
    /**
     * @param array-key $alias
     */
    private function resolve_offset_type($alias): Type
    {
        if (is_int($alias)) {
            return new Constant_Integer_Type($alias);
        }
        return new Constant_String_Type($alias);
    }
    public function set_indexed_by(Type $type): void
    {
        $this->indexed_by = $type;
    }
    public function get_index_type(): Type
    {
        if (!$this->select_query) {
            return new Void_Type();
        }
        return $this->indexed_by;
    }
}