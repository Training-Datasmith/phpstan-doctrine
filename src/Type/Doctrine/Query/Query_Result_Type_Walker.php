<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query;

use function array_key_exists;
use function array_map;
use function array_values;
use function assert;
use Backed_Enum;
use function class_exists;
use function count;
use Doctrine\DBAL\Types\Enum_Type as DbalEnumType;
use Doctrine\DBAL\Types\String_Type as DbalStringType;
use Doctrine\DBAL\Types\Type as DbalType;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Mapping\Class_Metadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\AST;
use Doctrine\ORM\Query\AST\Typed_Expression;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\Parser_Result;
use Doctrine\ORM\Query\Sql_Walker;
use function get_class;
use function gettype;
use function in_array;
use function is_array;
use function is_int;
use function is_numeric;
use function is_object;
use function is_string;
use PDO;
use Php_Stan\Doctrine\Driver\Driver_Detector;
use Php_Stan\Php\Php_Version;
use Php_Stan\Should_Not_Happen_Exception;
use Php_Stan\Trinary_Logic;
use Php_Stan\Type\Accessory\Accessory_Lowercase_String_Type;
use Php_Stan\Type\Accessory\Accessory_Numeric_String_Type;
use Php_Stan\Type\Accessory\Accessory_Uppercase_String_Type;
use Php_Stan\Type\Array_Type;
use Php_Stan\Type\Boolean_Type;
use Php_Stan\Type\Constant\Constant_Boolean_Type;
use Php_Stan\Type\Constant\Constant_Float_Type;
use Php_Stan\Type\Constant\Constant_Integer_Type;
use Php_Stan\Type\Constant\Constant_String_Type;
use Php_Stan\Type\Constant_Type_Helper;
use Php_Stan\Type\Doctrine\Descriptor_Not_Registered_Exception;
use Php_Stan\Type\Doctrine\Descriptor_Registry;
use Php_Stan\Type\Doctrine\Descriptors\Doctrine_Type_Driver_Aware_Descriptor;
use Php_Stan\Type\Float_Type;
use Php_Stan\Type\Integer_Range_Type;
use Php_Stan\Type\Integer_Type;
use Php_Stan\Type\Intersection_Type;
use Php_Stan\Type\Mixed_Type;
use Php_Stan\Type\Never_Type;
use Php_Stan\Type\Null_Type;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\String_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
use Php_Stan\Type\Type_Traverser;
use Php_Stan\Type\Type_Utils;
use Php_Stan\Type\Union_Type;
use function serialize;
use function sprintf;
use function stripos;
use function strpos;
use function strtolower;
use function strtoupper;
use function unserialize;
/**
 * QueryResultTypeWalker is a TreeWalker that uses a QueryResultTypeBuilder to build the result type of a Query
 *
 * It extends SqlkWalker because AST\Node::dispatch() accepts SqlWalker only
 *
 * @phpstan-import-type QueryComponent from Parser
 */
class Query_Result_Type_Walker extends Sql_Walker
{
    private const HINT_TYPE_MAPPING = self::class . '::HINT_TYPE_MAPPING';
    private const HINT_DESCRIPTOR_REGISTRY = self::class . '::HINT_DESCRIPTOR_REGISTRY';
    private const HINT_PHP_VERSION = self::class . '::HINT_PHP_VERSION';
    private const HINT_DRIVER_DETECTOR = self::class . '::HINT_DRIVER_DETECTOR';
    /**
     * Counter for generating unique scalar result.
     *
     */
    private int $scalar_result_counter = 1;
    /**
     * Counter for generating indexes.
     *
     */
    private int $new_object_counter = 0;
    /** @var Query<mixed> */
    private Query $query;
    private Entity_Manager_Interface $em;
    private Php_Version $php_version;
    /** @var DriverDetector::*|null */
    private ?string $driver_type;
    /** @var array<mixed> */
    private array $driver_options;
    /**
     * Map of all components/classes that appear in the DQL query.
     *
     * @var array<array-key,QueryComponent> $queryComponents
     */
    private array $query_components;
    /** @var array<array-key,bool> */
    private array $nullable_query_components;
    private Query_Result_Type_Builder $type_builder;
    private Descriptor_Registry $descriptor_registry;
    private bool $has_aggregate_function;
    private bool $has_group_by_clause;
    /**
     * @param Query<mixed> $query
     */
    public static function walk(Query $query, Query_Result_Type_Builder $type_builder, Descriptor_Registry $descriptor_registry, Php_Version $php_version, Driver_Detector $driver_detector): void
    {
        $query->set_hint(Query::HINT_CUSTOM_OUTPUT_WALKER, self::class);
        $query->set_hint(Query::HINT_CUSTOM_TREE_WALKERS, [Query_Aggregate_Function_Detector_Tree_Walker::class]);
        $query->set_hint(self::HINT_TYPE_MAPPING, $type_builder);
        $query->set_hint(self::HINT_DESCRIPTOR_REGISTRY, $descriptor_registry);
        $query->set_hint(self::HINT_PHP_VERSION, $php_version);
        $query->set_hint(self::HINT_DRIVER_DETECTOR, $driver_detector);
        $parser = new Parser($query);
        $parser->parse();
    }
    /**
     * {@inheritDoc}
     *
     * @param Query<mixed> $query
     * @param ParserResult $parserResult
     * @param array<QueryComponent> $queryComponents
     */
    public function __construct($query, $parser_result, array $query_components)
    {
        $this->query = $query;
        $this->em = $query->get_entity_manager();
        $this->query_components = $query_components;
        $this->nullable_query_components = [];
        $this->has_aggregate_function = $query->has_hint(Query_Aggregate_Function_Detector_Tree_Walker::HINT_HAS_AGGREGATE_FUNCTION);
        $this->has_group_by_clause = false;
        // The object is instantiated by Doctrine\ORM\Query\Parser, so receiving
        // dependencies through the constructor is not an option. Instead, we
        // receive the dependencies via query hints.
        $type_builder = $this->query->get_hint(self::HINT_TYPE_MAPPING);
        if (!$type_builder instanceof Query_Result_Type_Builder) {
            throw new Should_Not_Happen_Exception(sprintf('Expected the query hint %s to contain a %s, but got a %s', self::HINT_TYPE_MAPPING, Query_Result_Type_Builder::class, is_object($type_builder) ? get_class($type_builder) : gettype($type_builder)));
        }
        $this->type_builder = $type_builder;
        $descriptor_registry = $this->query->get_hint(self::HINT_DESCRIPTOR_REGISTRY);
        if (!$descriptor_registry instanceof Descriptor_Registry) {
            throw new Should_Not_Happen_Exception(sprintf('Expected the query hint %s to contain a %s, but got a %s', self::HINT_DESCRIPTOR_REGISTRY, Descriptor_Registry::class, is_object($descriptor_registry) ? get_class($descriptor_registry) : gettype($descriptor_registry)));
        }
        $this->descriptor_registry = $descriptor_registry;
        $php_version = $this->query->get_hint(self::HINT_PHP_VERSION);
        if (!$php_version instanceof Php_Version) {
            // @phpstan-ignore-line ignore bc promise
            throw new Should_Not_Happen_Exception(sprintf('Expected the query hint %s to contain a %s, but got a %s', self::HINT_PHP_VERSION, Php_Version::class, is_object($php_version) ? get_class($php_version) : gettype($php_version)));
        }
        $this->php_version = $php_version;
        $driver_detector = $this->query->get_hint(self::HINT_DRIVER_DETECTOR);
        if (!$driver_detector instanceof Driver_Detector) {
            throw new Should_Not_Happen_Exception(sprintf('Expected the query hint %s to contain a %s, but got a %s', self::HINT_DRIVER_DETECTOR, Driver_Detector::class, is_object($driver_detector) ? get_class($driver_detector) : gettype($driver_detector)));
        }
        $connection = $this->em->get_connection();
        $this->driver_type = $driver_detector->detect($connection);
        $this->driver_options = $driver_detector->detect_driver_options($connection);
        parent::__construct($query, $parser_result, $query_components);
    }
    public function walk_select_statement(AST\Select_Statement $AST): string
    {
        $this->type_builder->set_select_query();
        $this->has_group_by_clause = $AST->group_by_clause !== null;
        $this->walk_from_clause($AST->from_clause);
        foreach ($AST->select_clause->select_expressions as $select_expression) {
            assert($select_expression instanceof AST\Node);
            $select_expression->dispatch($this);
        }
        return '';
    }
    public function walk_update_statement(AST\Update_Statement $AST): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    public function walk_delete_statement(AST\Delete_Statement $AST): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param string $identVariable
     */
    public function walk_entity_identification_variable($ident_variable): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param string      $identificationVariable
     * @param string|null $fieldName
     */
    public function walk_identification_variable($identification_variable, $field_name = null): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\PathExpression $pathExpr
     */
    public function walk_path_expression($path_expr): string
    {
        $field_name = $path_expr->field;
        $dql_alias = $path_expr->identification_variable;
        $q_comp = $this->query_components[$dql_alias];
        assert(property_exists($q_comp, 'metadata'));
        /** @var ClassMetadata<object> $class */
        $class = $q_comp['metadata'];
        assert($field_name !== null);
        switch ($path_expr->type) {
            case AST\Path_Expression::TYPE_STATE_FIELD:
                [$type_name, $enum_type, $enum_values] = $this->get_type_of_field($class, $field_name);
                $nullable = $this->is_query_component_nullable($dql_alias) || $class->is_nullable($field_name) || $this->has_aggregate_without_group_by();
                $field_type = $this->resolve_database_internal_type($type_name, $enum_type, $enum_values, $nullable);
                return $this->marshal_type($field_type);
            case AST\Path_Expression::TYPE_SINGLE_VALUED_ASSOCIATION:
                if (isset($class->association_mappings[$field_name]['inherited'])) {
                    /** @var class-string $newClassName */
                    $new_class_name = $class->association_mappings[$field_name]['inherited'];
                    $class = $this->em->get_class_metadata($new_class_name);
                }
                $assoc = $class->association_mappings[$field_name];
                if (!$assoc['isOwningSide'] || !isset($assoc['joinColumns']) || count($assoc['joinColumns']) !== 1) {
                    throw new Should_Not_Happen_Exception();
                }
                $join_column = $assoc['joinColumns'][0];
                /** @var class-string $assocClassName */
                $assoc_class_name = $assoc['targetEntity'];
                $target_class = $this->em->get_class_metadata($assoc_class_name);
                $identifier_field_names = $target_class->get_identifier_field_names();
                if (count($identifier_field_names) !== 1) {
                    throw new Should_Not_Happen_Exception();
                }
                $target_field_name = $identifier_field_names[0];
                [$type_name, $enum_type, $enum_values] = $this->get_type_of_field($target_class, $target_field_name);
                $nullable = ($join_column['nullable'] ?? true) || $this->has_aggregate_without_group_by();
                $field_type = $this->resolve_database_internal_type($type_name, $enum_type, $enum_values, $nullable);
                return $this->marshal_type($field_type);
            default:
                throw new Should_Not_Happen_Exception();
        }
    }
    /**
     * @param AST\SelectClause $selectClause
     */
    public function walk_select_clause($select_clause): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\FromClause $fromClause
     */
    public function walk_from_clause($from_clause): string
    {
        foreach ($from_clause->identification_variable_declarations as $identification_variable_decl) {
            assert($identification_variable_decl instanceof AST\Node);
            $identification_variable_decl->dispatch($this);
        }
        return '';
    }
    /**
     * @param AST\IdentificationVariableDeclaration $identificationVariableDecl
     */
    public function walk_identification_variable_declaration($identification_variable_decl): string
    {
        if ($identification_variable_decl->index_by !== null) {
            $identification_variable_decl->index_by->dispatch($this);
        }
        foreach ($identification_variable_decl->joins as $join) {
            assert($join instanceof AST\Node);
            $join->dispatch($this);
        }
        return '';
    }
    /**
     * @param AST\IndexBy $indexBy
     */
    public function walk_index_by($index_by): void
    {
        $type = $this->unmarshal_type($index_by->single_valued_path_expression->dispatch($this));
        $this->type_builder->set_indexed_by($type);
    }
    /**
     * @param AST\RangeVariableDeclaration $rangeVariableDeclaration
     */
    public function walk_range_variable_declaration($range_variable_declaration): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\JoinAssociationDeclaration 								  $joinAssociationDeclaration
     * @param int                            								  $joinType
     * @param AST\ConditionalExpression|AST\Phase2OptimizableConditional|null $condExpr
     */
    public function walk_join_association_declaration($join_association_declaration, $join_type = AST\Join::JOIN_TYPE_INNER, $cond_expr = null): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\Functions\FunctionNode $function
     */
    public function walk_function($function): string
    {
        switch (true) {
            case $function instanceof AST\Functions\Avg_Function:
                return $this->marshal_type($this->infer_avg_function($function));
            case $function instanceof AST\Functions\Max_Function:
            case $function instanceof AST\Functions\Min_Function:
                //                       mysql      sqlite   pdo_pgsql   pgsql
                //	col_float =>         float       float      string   float
                //  col_decimal =>       string  int|float      string  string
                //  col_int =>           int           int         int     int
                //  col_bigint =>        int           int         int     int
                //
                //	MIN(col_float) =>    float        float    string    float
                //  MIN(col_decimal) =>  string   int|float    string   string
                //  MIN(col_int) =>      int            int      int       int
                //  MIN(col_bigint) =>   int            int      int       int
                $expr_type = $this->unmarshal_type($function->get_sql($this));
                $expr_type = $this->generalize_constant_type($expr_type, $this->has_aggregate_without_group_by());
                return $this->marshal_type($expr_type);
            // retains underlying type
            case $function instanceof AST\Functions\Sum_Function:
                return $this->marshal_type($this->infer_sum_function($function));
            case $function instanceof AST\Functions\Count_Function:
                return $this->marshal_type(Integer_Range_Type::from_interval(0, null));
            case $function instanceof AST\Functions\Abs_Function:
                //                       mysql      sqlite     pdo_pgsql     pgsql
                //	col_float =>         float       float        string     float
                //  col_decimal =>       string  int|float        string    string
                //  col_int =>           int           int           int       int
                //  col_bigint =>        int           int           int       int
                //
                //  ABS(col_float) =>    float       float        string     float
                //  ABS(col_decimal) =>  string  int|float        string    string
                //  ABS(col_int) =>      int           int           int       int
                //  ABS(col_bigint) =>   int           int           int       int
                //  ABS(col_string) =>   float        float            x         x
                $expr_type = $this->unmarshal_type($this->walk_simple_arithmetic_expression($function->simple_arithmetic_expression));
                $expr_type = $this->cast_string_literal_for_float_expression($expr_type);
                $expr_type = $this->generalize_constant_type($expr_type, false);
                $expr_type_no_null = Type_Combinator::remove_null($expr_type);
                $nullable = $this->can_be_null($expr_type);
                if ($expr_type_no_null->is_integer()->yes()) {
                    $non_negative_int = $this->create_non_negative_integer($nullable);
                    return $this->marshal_type($non_negative_int);
                }
                if ($this->contains_only_numeric_types($expr_type_no_null)) {
                    return $this->marshal_type($expr_type);
                    // retains underlying type
                }
                return $this->marshal_type(new Mixed_Type());
            case $function instanceof AST\Functions\Bit_And_Function:
            case $function instanceof AST\Functions\Bit_Or_Function:
                $first_expr_type = $this->unmarshal_type($function->first_arithmetic->dispatch($this));
                $second_expr_type = $this->unmarshal_type($function->second_arithmetic->dispatch($this));
                $type = Integer_Range_Type::from_interval(0, null);
                if ($this->can_be_null($first_expr_type) || $this->can_be_null($second_expr_type)) {
                    $type = Type_Combinator::add_null($type);
                }
                return $this->marshal_type($type);
            case $function instanceof AST\Functions\Concat_Function:
                $has_null = false;
                foreach ($function->concat_expressions as $expr) {
                    $type = $this->unmarshal_type($expr->dispatch($this));
                    $has_null = $has_null || $this->can_be_null($type);
                }
                $type = new String_Type();
                if ($has_null) {
                    $type = Type_Combinator::add_null($type);
                }
                return $this->marshal_type($type);
            case $function instanceof AST\Functions\Current_Date_Function:
            case $function instanceof AST\Functions\Current_Time_Function:
            case $function instanceof AST\Functions\Current_Timestamp_Function:
                return $this->marshal_type(new String_Type());
            case $function instanceof AST\Functions\Date_Add_Function:
            case $function instanceof AST\Functions\Date_Sub_Function:
                $date_expr_type = $this->unmarshal_type($function->first_date_expression->dispatch($this));
                $interval_expr_type = $this->unmarshal_type($function->interval_expression->dispatch($this));
                $type = new String_Type();
                if ($this->can_be_null($date_expr_type) || $this->can_be_null($interval_expr_type)) {
                    $type = Type_Combinator::add_null($type);
                }
                return $this->marshal_type($type);
            case $function instanceof AST\Functions\Date_Diff_Function:
                $date1expr_type = $this->unmarshal_type($function->date1->dispatch($this));
                $date2expr_type = $this->unmarshal_type($function->date2->dispatch($this));
                if ($this->driver_type === Driver_Detector::SQLITE3 || $this->driver_type === Driver_Detector::PDO_SQLITE) {
                    $type = new Float_Type();
                } else {
                    $type = new Integer_Type();
                }
                if ($this->can_be_null($date1expr_type) || $this->can_be_null($date2expr_type)) {
                    $type = Type_Combinator::add_null($type);
                }
                return $this->marshal_type($type);
            case $function instanceof AST\Functions\Length_Function:
                $string_primary_type = $this->unmarshal_type($function->string_primary->dispatch($this));
                $type = Integer_Range_Type::from_interval(0, null);
                if ($this->can_be_null($string_primary_type)) {
                    $type = Type_Combinator::add_null($type);
                }
                return $this->marshal_type($type);
            case $function instanceof AST\Functions\Locate_Function:
                $first_expr_type = $this->unmarshal_type($this->walk_string_primary($function->first_string_primary));
                $second_expr_type = $this->unmarshal_type($this->walk_string_primary($function->second_string_primary));
                $type = Integer_Range_Type::from_interval(0, null);
                if ($this->can_be_null($first_expr_type) || $this->can_be_null($second_expr_type)) {
                    $type = Type_Combinator::add_null($type);
                }
                return $this->marshal_type($type);
            case $function instanceof AST\Functions\Lower_Function:
            case $function instanceof AST\Functions\Trim_Function:
            case $function instanceof AST\Functions\Upper_Function:
                $string_primary_type = $this->unmarshal_type($function->string_primary->dispatch($this));
                $type = new String_Type();
                if ($this->can_be_null($string_primary_type)) {
                    $type = Type_Combinator::add_null($type);
                }
                return $this->marshal_type($type);
            case $function instanceof AST\Functions\Mod_Function:
                $first_expr_type = $this->unmarshal_type($this->walk_simple_arithmetic_expression($function->first_simple_arithmetic_expression));
                $second_expr_type = $this->unmarshal_type($this->walk_simple_arithmetic_expression($function->second_simple_arithmetic_expression));
                $union = Type_Combinator::union($first_expr_type, $second_expr_type);
                $union_no_null = Type_Combinator::remove_null($union);
                if (!$union_no_null->is_integer()->yes()) {
                    return $this->marshal_type(new Mixed_Type());
                    // dont try to deal with non-integer chaos
                }
                $type = Integer_Range_Type::from_interval(0, null);
                if ($this->can_be_null($first_expr_type) || $this->can_be_null($second_expr_type)) {
                    $type = Type_Combinator::add_null($type);
                }
                $is_pg_sql = $this->driver_type === Driver_Detector::PGSQL || $this->driver_type === Driver_Detector::PDO_PGSQL;
                $may_be_zero = !(new Constant_Integer_Type(0))->is_super_type_of($second_expr_type)->no();
                if (!$is_pg_sql && $may_be_zero) {
                    // MOD(x, 0) returns NULL in non-strict platforms, fails in postgre
                    $type = Type_Combinator::add_null($type);
                }
                return $this->marshal_type($this->generalize_constant_type($type, false));
            case $function instanceof AST\Functions\Sqrt_Function:
                //                       mysql      sqlite       pdo_pgsql  pgsql
                //	col_float =>         float      float        string     float
                //  col_decimal =>       string     float|int    string     string
                //  col_int =>           int        int          int        int
                //  col_bigint =>        int        int          int        int
                //
                //  SQRT(col_float) =>   float      float        string     float
                //  SQRT(col_decimal) => float      float        string     string
                //  SQRT(col_int) =>     float      float        string     float
                //  SQRT(col_bigint) =>  float      float        string     float
                $expr_type = $this->unmarshal_type($this->walk_simple_arithmetic_expression($function->simple_arithmetic_expression));
                $expr_type_no_null = Type_Combinator::remove_null($expr_type);
                if (!$this->contains_only_numeric_types($expr_type_no_null)) {
                    return $this->marshal_type(new Mixed_Type());
                    // dont try to deal with non-numeric args
                }
                if ($this->driver_type === Driver_Detector::MYSQLI || $this->driver_type === Driver_Detector::PDO_MYSQL || $this->driver_type === Driver_Detector::SQLITE3 || $this->driver_type === Driver_Detector::PDO_SQLITE) {
                    $type = new Float_Type();
                    $cannot_be_negative = $expr_type->is_smaller_than(new Constant_Integer_Type(0), $this->php_version)->no();
                    $can_be_negative = !$cannot_be_negative;
                    if ($can_be_negative) {
                        $type = Type_Combinator::add_null($type);
                    }
                } elseif ($this->driver_type === Driver_Detector::PGSQL || $this->driver_type === Driver_Detector::PDO_PGSQL) {
                    $casted_expr_type = $this->cast_string_literal_for_numeric_expression($expr_type_no_null);
                    if ($casted_expr_type->is_integer()->yes() || $casted_expr_type->is_float()->yes()) {
                        $type = $this->create_float(false);
                    } elseif ($casted_expr_type->is_numeric_string()->yes()) {
                        $type = $this->create_numeric_string(false, $casted_expr_type->is_lowercase_string()->yes(), $casted_expr_type->is_uppercase_string()->yes());
                    } else {
                        $type = Type_Combinator::union($this->create_float(false), $this->create_numeric_string(false, false, true));
                    }
                } else {
                    $type = new Mixed_Type();
                }
                if ($this->can_be_null($expr_type)) {
                    $type = Type_Combinator::add_null($type);
                }
                return $this->marshal_type($type);
            case $function instanceof AST\Functions\Substring_Function:
                $string_type = $this->unmarshal_type($function->string_primary->dispatch($this));
                $first_expr_type = $this->unmarshal_type($this->walk_simple_arithmetic_expression($function->first_simple_arithmetic_expression));
                if ($function->second_simple_arithmetic_expression !== null) {
                    $second_expr_type = $this->unmarshal_type($this->walk_simple_arithmetic_expression($function->second_simple_arithmetic_expression));
                } else {
                    $second_expr_type = new Integer_Type();
                }
                $type = new String_Type();
                if ($this->can_be_null($string_type) || $this->can_be_null($first_expr_type) || $this->can_be_null($second_expr_type)) {
                    $type = Type_Combinator::add_null($type);
                }
                return $this->marshal_type($type);
            case $function instanceof AST\Functions\Identity_Function:
                $dql_alias = $function->path_expression->identification_variable;
                $assoc_field = $function->path_expression->field;
                assert(is_string($assoc_field));
                $query_comp = $this->query_components[$dql_alias];
                assert(property_exists($query_comp, 'metadata'));
                $class = $query_comp['metadata'];
                $assoc = $class->association_mappings[$assoc_field];
                /** @var class-string $assocClassName */
                $assoc_class_name = $assoc['targetEntity'];
                $target_class = $this->em->get_class_metadata($assoc_class_name);
                if ($function->field_mapping === null) {
                    $identifier_field_names = $target_class->get_identifier_field_names();
                    if (count($identifier_field_names) === 0) {
                        throw new Should_Not_Happen_Exception();
                    }
                    $target_field_name = $identifier_field_names[0];
                } else {
                    $target_field_name = $function->field_mapping;
                }
                $field_mapping = $target_class->field_mappings[$target_field_name] ?? null;
                if ($field_mapping === null) {
                    return $this->marshal_type(new Mixed_Type());
                }
                [$type_name, $enum_type, $enum_values] = $this->get_type_of_field($target_class, $target_field_name);
                if (!isset($assoc['joinColumns'])) {
                    return $this->marshal_type(new Mixed_Type());
                }
                $join_column = null;
                foreach ($assoc['joinColumns'] as $item) {
                    if ($item['referencedColumnName'] === $field_mapping['columnName']) {
                        $join_column = $item;
                        break;
                    }
                }
                if ($join_column === null) {
                    return $this->marshal_type(new Mixed_Type());
                }
                $nullable = ($join_column['nullable'] ?? true) || $this->is_query_component_nullable($dql_alias) || $this->has_aggregate_without_group_by();
                $field_type = $this->resolve_database_internal_type($type_name, $enum_type, $enum_values, $nullable);
                return $this->marshal_type($field_type);
            default:
                return $this->marshal_type(new Mixed_Type());
        }
    }
    private function infer_avg_function(AST\Functions\Avg_Function $function): Type
    {
        //                       mysql      sqlite   pdo_pgsql    pgsql
        //	col_float =>         float       float      string    float
        //  col_decimal =>       string  int|float      string   string
        //  col_int =>           int           int         int      int
        //  col_bigint =>        int           int         int      int
        //
        //  AVG(col_float) =>    float       float      string    float
        //  AVG(col_decimal) =>  string      float      string   string
        //  AVG(col_int) =>      string      float      string   string
        //  AVG(col_bigint) =>   string      float      string   string
        $expr_type = $this->unmarshal_type($function->get_sql($this));
        $expr_type_no_null = Type_Combinator::remove_null($expr_type);
        $nullable = $this->can_be_null($expr_type) || $this->has_aggregate_without_group_by();
        if ($this->driver_type === Driver_Detector::SQLITE3 || $this->driver_type === Driver_Detector::PDO_SQLITE) {
            return $this->create_float($nullable);
        }
        if ($this->driver_type === Driver_Detector::PDO_MYSQL || $this->driver_type === Driver_Detector::MYSQLI) {
            if ($expr_type_no_null->is_integer()->yes()) {
                return $this->create_numeric_string($nullable, true, true);
            }
            if ($expr_type_no_null->is_string()->yes() && !$expr_type_no_null->is_numeric_string()->yes()) {
                return $this->create_float($nullable);
            }
            return $this->generalize_constant_type($expr_type, $nullable);
        }
        if ($this->driver_type === Driver_Detector::PGSQL || $this->driver_type === Driver_Detector::PDO_PGSQL) {
            if ($expr_type_no_null->is_integer()->yes()) {
                return $this->create_numeric_string($nullable, true, true);
            }
            return $this->generalize_constant_type($expr_type, $nullable);
        }
        return new Mixed_Type();
    }
    private function infer_sum_function(AST\Functions\Sum_Function $function): Type
    {
        //                       mysql      sqlite   pdo_pgsql     pgsql
        //  col_float =>         float       float     string      float
        //  col_decimal =>       string  int|float     string     string
        //  col_int =>           int           int        int        int
        //  col_bigint =>        int           int        int        int
        //
        //  SUM(col_float) =>    float        float    string      float
        //  SUM(col_decimal) =>  string   int|float    string     string
        //  SUM(col_int) =>      string         int       int        int
        //  SUM(col_bigint) =>   string         int    string     string
        $expr_type = $this->unmarshal_type($function->get_sql($this));
        $expr_type_no_null = Type_Combinator::remove_null($expr_type);
        $nullable = $this->can_be_null($expr_type) || $this->has_aggregate_without_group_by();
        if ($this->driver_type === Driver_Detector::SQLITE3 || $this->driver_type === Driver_Detector::PDO_SQLITE) {
            if ($expr_type_no_null->is_string()->yes() && !$expr_type_no_null->is_numeric_string()->yes()) {
                return $this->create_float($nullable);
            }
            return $this->generalize_constant_type($expr_type, $nullable);
        }
        if ($this->driver_type === Driver_Detector::PDO_MYSQL || $this->driver_type === Driver_Detector::MYSQLI) {
            if ($expr_type_no_null->is_integer()->yes()) {
                return $this->create_numeric_string($nullable, true, true);
            }
            if ($expr_type_no_null->is_string()->yes() && !$expr_type_no_null->is_numeric_string()->yes()) {
                return $this->create_float($nullable);
            }
            return $this->generalize_constant_type($expr_type, $nullable);
        }
        if ($this->driver_type === Driver_Detector::PGSQL || $this->driver_type === Driver_Detector::PDO_PGSQL) {
            if ($expr_type_no_null->is_integer()->yes()) {
                return Type_Combinator::union($this->create_integer($nullable), $this->create_numeric_string($nullable, true, true));
            }
            return $this->generalize_constant_type($expr_type, $nullable);
        }
        return new Mixed_Type();
    }
    private function create_float(bool $nullable): Type
    {
        $float = new Float_Type();
        return $nullable ? Type_Combinator::add_null($float) : $float;
    }
    private function create_float_or_int(bool $nullable): Type
    {
        $union = Type_Combinator::union(new Float_Type(), new Integer_Type());
        return $nullable ? Type_Combinator::add_null($union) : $union;
    }
    private function create_integer(bool $nullable): Type
    {
        $integer = new Integer_Type();
        return $nullable ? Type_Combinator::add_null($integer) : $integer;
    }
    private function create_non_negative_integer(bool $nullable): Type
    {
        $integer = Integer_Range_Type::from_interval(0, null);
        return $nullable ? Type_Combinator::add_null($integer) : $integer;
    }
    private function create_numeric_string(bool $nullable, bool $lowercase = false, bool $uppercase = false): Type
    {
        $types = [new String_Type(), new Accessory_Numeric_String_Type()];
        if ($lowercase) {
            $types[] = new Accessory_Lowercase_String_Type();
        }
        if ($uppercase) {
            $types[] = new Accessory_Uppercase_String_Type();
        }
        $numeric_string = new Intersection_Type($types);
        return $nullable ? Type_Combinator::add_null($numeric_string) : $numeric_string;
    }
    private function create_string(bool $nullable, bool $lowercase = false, bool $uppercase = false): Type
    {
        if ($lowercase || $uppercase) {
            $types = [new String_Type()];
            if ($lowercase) {
                $types[] = new Accessory_Lowercase_String_Type();
            }
            if ($uppercase) {
                $types[] = new Accessory_Uppercase_String_Type();
            }
            $string = new Intersection_Type($types);
        } else {
            $string = new String_Type();
        }
        return $nullable ? Type_Combinator::add_null($string) : $string;
    }
    private function contains_only_numeric_types(Type ...$checked_types): bool
    {
        foreach ($checked_types as $checked_type) {
            if (!$this->contains_only_types($checked_type, [new Integer_Type(), new Float_Type(), $this->create_numeric_string(false)])) {
                return false;
            }
        }
        return true;
    }
    /**
     * @param list<Type> $allowedTypes
     */
    private function contains_only_types(Type $checked_type, array $allowed_types): bool
    {
        $allowed_type = Type_Combinator::union(...$allowed_types);
        return $allowed_type->is_super_type_of($checked_type)->yes();
    }
    /**
     * E.g. to ensure SUM(1) is inferred as int, not 1
     */
    private function generalize_constant_type(Type $type, bool $make_nullable): Type
    {
        $contains_null = $this->can_be_null($type);
        $type_no_null = Type_Combinator::remove_null($type);
        if (!$type_no_null->is_constant_scalar_value()->yes()) {
            $result = $type;
        } elseif ($type_no_null->is_integer()->yes()) {
            $result = $this->create_integer($contains_null);
        } elseif ($type_no_null->is_float()->yes()) {
            $result = $this->create_float($contains_null);
        } elseif ($type_no_null->is_numeric_string()->yes()) {
            $result = $this->create_numeric_string($contains_null, $type_no_null->is_lowercase_string()->yes(), $type_no_null->is_uppercase_string()->yes());
        } elseif ($type_no_null->is_string()->yes()) {
            $result = $this->create_string($contains_null, $type_no_null->is_lowercase_string()->yes(), $type_no_null->is_uppercase_string()->yes());
        } else {
            $result = $type;
        }
        return $make_nullable ? Type_Combinator::add_null($result) : $result;
    }
    /**
     * @param AST\OrderByClause $orderByClause
     */
    public function walk_order_by_clause($order_by_clause): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\OrderByItem $orderByItem
     */
    public function walk_order_by_item($order_by_item): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\HavingClause $havingClause
     */
    public function walk_having_clause($having_clause): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\Join $join
     */
    public function walk_join($join): string
    {
        $join_type = $join->join_type;
        $join_declaration = $join->join_association_declaration;
        switch (true) {
            case $join_declaration instanceof AST\Range_Variable_Declaration:
            case $join_declaration instanceof AST\Join_Association_Declaration:
                $dql_alias = $join_declaration->alias_identification_variable;
                $this->nullable_query_components[$dql_alias] = $join_type === AST\Join::JOIN_TYPE_LEFT || $join_type === AST\Join::JOIN_TYPE_LEFTOUTER;
                break;
        }
        return '';
    }
    /**
     * @param AST\CoalesceExpression $coalesceExpression
     */
    public function walk_coalesce_expression($coalesce_expression): string
    {
        $raw_types = [];
        $expression_types = [];
        $all_types_contain_null = true;
        foreach ($coalesce_expression->scalar_expressions as $expression) {
            if (!$expression instanceof AST\Node) {
                $expression_types[] = new Mixed_Type();
                continue;
            }
            $raw_type = $this->unmarshal_type($expression->dispatch($this));
            $raw_types[] = $raw_type;
            $all_types_contain_null = $all_types_contain_null && $this->can_be_null($raw_type);
            // Some drivers manipulate the types, lets avoid false positives by generalizing constant types
            // e.g. sqlsrv: "COALESCE returns the data type of value with the highest precedence"
            // e.g. mysql: COALESCE(1, 'foo') === '1' (undocumented? https://gist.github.com/jrunning/4535434)
            $expression_types[] = $this->generalize_constant_type($raw_type, false);
        }
        $generalized_union = Type_Combinator::union(...$expression_types);
        if (!$all_types_contain_null) {
            $generalized_union = Type_Combinator::remove_null($generalized_union);
        }
        if ($this->driver_type === Driver_Detector::MYSQLI || $this->driver_type === Driver_Detector::PDO_MYSQL) {
            return $this->marshal_type($this->infer_coalesce_for_my_sql($raw_types, $generalized_union));
        }
        return $this->marshal_type($generalized_union);
    }
    /**
     * @param list<Type> $rawTypes
     */
    private function infer_coalesce_for_my_sql(array $raw_types, Type $original_result): Type
    {
        $contains_string = false;
        $contains_float = false;
        $all_is_numeric_excluding_literal_string = true;
        foreach ($raw_types as $raw_type) {
            $raw_type_no_null = Type_Combinator::remove_null($raw_type);
            $is_literal_string = $raw_type_no_null instanceof Dql_Constant_String_Type && $raw_type_no_null->get_origin_literal_type() === AST\Literal::STRING;
            if (!$this->contains_only_numeric_types($raw_type_no_null) || $is_literal_string) {
                $all_is_numeric_excluding_literal_string = false;
            }
            if ($raw_type_no_null->is_string()->yes()) {
                $contains_string = true;
            }
            if (!$raw_type_no_null->is_float()->yes()) {
                continue;
            }
            $contains_float = true;
        }
        if ($contains_float && $all_is_numeric_excluding_literal_string) {
            return $this->simple_floatify($original_result);
        }
        if ($contains_string) {
            return $this->simple_stringify($original_result);
        }
        return $original_result;
    }
    /**
     * @param AST\NullIfExpression $nullIfExpression
     */
    public function walk_null_if_expression($null_if_expression): string
    {
        $first_expression = $null_if_expression->first_expression;
        if (!$first_expression instanceof AST\Node) {
            return $this->marshal_type(new Mixed_Type());
        }
        $first_type = $this->unmarshal_type($first_expression->dispatch($this));
        // NULLIF() returns the first expression or NULL
        $type = Type_Combinator::add_null($first_type);
        return $this->marshal_type($type);
    }
    public function walk_general_case_expression(AST\General_Case_Expression $general_case_expression): string
    {
        $when_clauses = $general_case_expression->when_clauses;
        $else_scalar_expression = $general_case_expression->else_scalar_expression;
        $types = [];
        foreach ($when_clauses as $clause) {
            if (!$clause instanceof AST\When_Clause) {
                $types[] = new Mixed_Type();
                continue;
            }
            $then_scalar_expression = $clause->then_scalar_expression;
            if (!$then_scalar_expression instanceof AST\Node) {
                $types[] = new Mixed_Type();
                continue;
            }
            $types[] = $this->unmarshal_type($then_scalar_expression->dispatch($this));
        }
        if ($else_scalar_expression instanceof AST\Node) {
            $types[] = $this->unmarshal_type($else_scalar_expression->dispatch($this));
        }
        $type = Type_Combinator::union(...$types);
        return $this->marshal_type($type);
    }
    /**
     * @param AST\SimpleCaseExpression $simpleCaseExpression
     */
    public function walk_simple_case_expression($simple_case_expression): string
    {
        $when_clauses = $simple_case_expression->simple_when_clauses;
        $else_scalar_expression = $simple_case_expression->else_scalar_expression;
        $types = [];
        foreach ($when_clauses as $clause) {
            if (!$clause instanceof AST\Simple_When_Clause) {
                $types[] = new Mixed_Type();
                continue;
            }
            $then_scalar_expression = $clause->then_scalar_expression;
            if (!$then_scalar_expression instanceof AST\Node) {
                $types[] = new Mixed_Type();
                continue;
            }
            $types[] = $this->unmarshal_type($then_scalar_expression->dispatch($this));
        }
        if ($else_scalar_expression instanceof AST\Node) {
            $types[] = $this->unmarshal_type($else_scalar_expression->dispatch($this));
        }
        $type = Type_Combinator::union(...$types);
        return $this->marshal_type($type);
    }
    /**
     * @param AST\SelectExpression $selectExpression
     */
    public function walk_select_expression($select_expression): string
    {
        $expr = $select_expression->expression;
        $hidden = $select_expression->hidden_alias_result_variable;
        if ($hidden) {
            return '';
        }
        if (is_string($expr)) {
            $dql_alias = $expr;
            $query_comp = $this->query_components[$dql_alias];
            assert(property_exists($query_comp, 'metadata'));
            $class = $query_comp['metadata'];
            $result_alias = $select_expression->field_identification_variable ?? $dql_alias;
            assert(array_key_exists('parent', $query_comp));
            if ($query_comp['parent'] !== null) {
                return '';
            }
            $type = new Object_Type($class->name);
            if ($this->is_query_component_nullable($dql_alias) || $this->has_aggregate_without_group_by()) {
                $type = Type_Combinator::add_null($type);
            }
            $this->type_builder->add_entity($result_alias, $type, $select_expression->field_identification_variable);
            return '';
        }
        if ($expr instanceof AST\Path_Expression) {
            assert($expr->type === AST\Path_Expression::TYPE_STATE_FIELD);
            $field_name = $expr->field;
            assert($field_name !== null);
            $result_alias = $select_expression->field_identification_variable ?? $field_name;
            $dql_alias = $expr->identification_variable;
            $q_comp = $this->query_components[$dql_alias];
            assert(property_exists($q_comp, 'metadata'));
            $class = $q_comp['metadata'];
            [$type_name, $enum_type, $enum_values] = $this->get_type_of_field($class, $field_name);
            $nullable = $this->is_query_component_nullable($dql_alias) || $class->is_nullable($field_name) || $this->has_aggregate_without_group_by();
            $type = $this->resolve_doctrine_type($type_name, $enum_type, $enum_values, $nullable);
            $this->type_builder->add_scalar($result_alias, $type);
            return '';
        }
        if ($expr instanceof AST\New_Object_Expression) {
            $result_alias = $select_expression->field_identification_variable ?? $this->new_object_counter++;
            $type = $this->unmarshal_type($this->walk_new_object($expr));
            $this->type_builder->add_new_object($result_alias, $type);
            return '';
        }
        if ($expr instanceof AST\Node) {
            $result_alias = $select_expression->field_identification_variable ?? $this->scalar_result_counter++;
            $type = $this->unmarshal_type($expr->dispatch($this));
            if ($expr instanceof Typed_Expression && !$expr->get_return_type() instanceof Dbal_String_Type && !$expr->get_return_type() instanceof Dbal_Enum_Type) {
                $dbal_type_name = Dbal_Type::get_type_registry()->lookup_name($expr->get_return_type());
                $type = Type_Combinator::intersect(
                    // e.g. count is typed as int, but we infer int<0, max>
                    $type,
                    $this->resolve_doctrine_type($dbal_type_name, null, null, Type_Combinator::contains_null($type))
                );
                if ($this->has_aggregate_without_group_by() && !$expr instanceof AST\Functions\Count_Function) {
                    $type = Type_Combinator::add_null($type);
                }
            } else {
                // Expressions default to Doctrine's StringType, whose
                // convertToPHPValue() is a no-op. So the actual type depends on
                // the driver and PHP version.
                $type = Type_Traverser::map($type, function (Type $type, callable $traverse): Type {
                    if ($type instanceof Union_Type || $type instanceof Intersection_Type) {
                        return $traverse($type);
                    }
                    if ($type instanceof Integer_Type) {
                        $stringify = $this->should_stringify_expressions($type);
                        if ($stringify->yes()) {
                            return $type->to_string();
                        }
                        if ($stringify->maybe()) {
                            return Type_Combinator::union($type->to_string(), $type);
                        }
                        return $type;
                    }
                    if ($type instanceof Float_Type) {
                        $stringify = $this->should_stringify_expressions($type);
                        // e.g. 1.0 on sqlite results to '1' with pdo_stringify on PHP 8.1, but '1.0' on PHP 8.0 with no setup
                        // so we relax constant types and return just numeric-string to avoid those issues
                        $stringified_float = $this->create_numeric_string(false, false, true);
                        if ($stringify->yes()) {
                            return $stringified_float;
                        }
                        if ($stringify->maybe()) {
                            return Type_Combinator::union($stringified_float, $type);
                        }
                        return $type;
                    }
                    if ($type instanceof Boolean_Type) {
                        $stringify = $this->should_stringify_expressions($type);
                        if ($stringify->yes()) {
                            return $type->to_integer()->to_string();
                        }
                        if ($stringify->maybe()) {
                            return Type_Combinator::union($type->to_integer()->to_string(), $type);
                        }
                        return $type;
                    }
                    return $traverse($type);
                });
                if (!$this->is_supported_driver()) {
                    $type = new Mixed_Type();
                    // avoid guessing for unsupported drivers, there are too many differences
                }
            }
            $this->type_builder->add_scalar($result_alias, $type);
            return '';
        }
        return '';
    }
    /**
     * @param AST\QuantifiedExpression $qExpr
     */
    public function walk_quantified_expression($q_expr): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\Subselect $subselect
     */
    public function walk_subselect($subselect): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\SubselectFromClause $subselectFromClause
     */
    public function walk_subselect_from_clause($subselect_from_clause): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\SimpleSelectClause $simpleSelectClause
     */
    public function walk_simple_select_clause($simple_select_clause): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    public function walk_parenthesis_expression(AST\Parenthesis_Expression $parenthesis_expression): string
    {
        return $parenthesis_expression->expression->dispatch($this);
    }
    /**
     * @param AST\NewObjectExpression $newObjectExpression
     * @param string|null             $newObjectResultAlias
     */
    public function walk_new_object($new_object_expression, $new_object_result_alias = null): string
    {
        for ($i = 0; $i < count($new_object_expression->args); $i++) {
            $this->scalar_result_counter++;
        }
        $type = new Object_Type($new_object_expression->class_name);
        return $this->marshal_type($type);
    }
    /**
     * @param AST\SimpleSelectExpression $simpleSelectExpression
     */
    public function walk_simple_select_expression($simple_select_expression): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\AggregateExpression $aggExpression
     */
    public function walk_aggregate_expression($agg_expression): string
    {
        switch (strtoupper($agg_expression->function_name)) {
            case 'AVG':
            case 'SUM':
                $type = $this->unmarshal_type($this->walk_simple_arithmetic_expression($agg_expression->path_expression));
                $type = $this->cast_string_literal_for_numeric_expression($type);
                return $this->marshal_type($type);
            case 'MAX':
            case 'MIN':
                return $this->walk_simple_arithmetic_expression($agg_expression->path_expression);
            case 'COUNT':
                return $this->marshal_type(Integer_Range_Type::from_interval(0, null));
            default:
                return $this->marshal_type(new Mixed_Type());
        }
    }
    private function cast_string_literal_for_float_expression(Type $type): Type
    {
        if (!$type instanceof Dql_Constant_String_Type || $type->get_origin_literal_type() !== AST\Literal::STRING) {
            return $type;
        }
        $value = $type->get_value();
        if (is_numeric($value)) {
            return new Constant_Float_Type((float) $value);
        }
        return $type;
    }
    /**
     * Numeric strings are kept as strings in literal usage, but casted to numeric value once used in numeric expression
     *  - SELECT '1'     => '1'
     *  - SELECT 1 * '1' => 1
     */
    private function cast_string_literal_for_numeric_expression(Type $type): Type
    {
        if (!$type instanceof Dql_Constant_String_Type || $type->get_origin_literal_type() !== AST\Literal::STRING) {
            return $type;
        }
        $is_mysql = $this->driver_type === Driver_Detector::MYSQLI || $this->driver_type === Driver_Detector::PDO_MYSQL;
        $value = $type->get_value();
        if (is_numeric($value)) {
            if (strpos($value, '.') === false && strpos($value, 'e') === false && !$is_mysql) {
                return new Constant_Integer_Type((int) $value);
            }
            return new Constant_Float_Type((float) $value);
        }
        return $type;
    }
    /**
     * @param AST\GroupByClause $groupByClause
     */
    public function walk_group_by_clause($group_by_clause): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\PathExpression|string $groupByItem
     */
    public function walk_group_by_item($group_by_item): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    public function walk_delete_clause(AST\Delete_Clause $delete_clause): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\UpdateClause $updateClause
     */
    public function walk_update_clause($update_clause): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\UpdateItem $updateItem
     */
    public function walk_update_item($update_item): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\WhereClause|null $whereClause
     */
    public function walk_where_clause($where_clause): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\ConditionalExpression|AST\Phase2OptimizableConditional $condExpr
     */
    public function walk_conditional_expression($cond_expr): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\ConditionalTerm|AST\ConditionalPrimary|AST\ConditionalFactor $condTerm
     */
    public function walk_conditional_term($cond_term): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\ConditionalFactor|AST\ConditionalPrimary $factor
     */
    public function walk_conditional_factor($factor): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\ConditionalPrimary $primary
     */
    public function walk_conditional_primary($primary): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\ExistsExpression $existsExpr
     */
    public function walk_exists_expression($exists_expr): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\CollectionMemberExpression $collMemberExpr
     */
    public function walk_collection_member_expression($coll_member_expr): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\EmptyCollectionComparisonExpression $emptyCollCompExpr
     */
    public function walk_empty_collection_comparison_expression($empty_coll_comp_expr): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\NullComparisonExpression $nullCompExpr
     */
    public function walk_null_comparison_expression($null_comp_expr): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param mixed $inExpr
     */
    public function walk_in_expression($in_expr): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\InstanceOfExpression $instanceOfExpr
     */
    public function walk_instance_of_expression($instance_of_expr): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param mixed $inParam
     */
    public function walk_in_parameter($in_param): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\Literal $literal
     */
    public function walk_literal($literal): string
    {
        switch ($literal->type) {
            case AST\Literal::STRING:
                $value = $literal->value;
                assert(is_string($value));
                $type = new Dql_Constant_String_Type($value, $literal->type);
                break;
            case AST\Literal::BOOLEAN:
                $value = strtolower($literal->value) === 'true';
                if ($this->driver_type === Driver_Detector::PDO_PGSQL || $this->driver_type === Driver_Detector::PGSQL) {
                    $type = new Constant_Boolean_Type($value);
                } else {
                    $type = new Constant_Integer_Type($value ? 1 : 0);
                }
                break;
            case AST\Literal::NUMERIC:
                $value = $literal->value;
                assert(is_int($value) || is_string($value));
                // ensured in parser
                if (is_int($value) || strpos($value, '.') === false && strpos($value, 'e') === false) {
                    $type = new Constant_Integer_Type((int) $value);
                } else if ($this->driver_type === Driver_Detector::PDO_MYSQL || $this->driver_type === Driver_Detector::MYSQLI) {
                    // both pdo_mysql and mysqli hydrates decimal literal (e.g. 123.4) as string no matter the configuration (e.g. PDO::ATTR_STRINGIFY_FETCHES being false) and PHP version
                    // the only way to force float is to use float literal with scientific notation (e.g. 123.4e0)
                    // https://dev.mysql.com/doc/refman/8.0/en/number-literals.html
                    if (stripos($value, 'e') !== false) {
                        $type = new Constant_Float_Type((float) $value);
                    } else {
                        $type = new Dql_Constant_String_Type($value, $literal->type);
                    }
                } elseif ($this->driver_type === Driver_Detector::PGSQL || $this->driver_type === Driver_Detector::PDO_PGSQL) {
                    if (stripos($value, 'e') !== false) {
                        $type = new Dql_Constant_String_Type((string) (float) $value, $literal->type);
                    } else {
                        $type = new Dql_Constant_String_Type($value, $literal->type);
                    }
                } else {
                    $type = new Constant_Float_Type((float) $value);
                }
                break;
            default:
                $type = new Mixed_Type();
                break;
        }
        return $this->marshal_type($type);
    }
    /**
     * @param AST\BetweenExpression $betweenExpr
     */
    public function walk_between_expression($between_expr): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\LikeExpression $likeExpr
     */
    public function walk_like_expression($like_expr): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\PathExpression $stateFieldPathExpression
     */
    public function walk_state_field_path_expression($state_field_path_expression): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\ComparisonExpression $compExpr
     */
    public function walk_comparison_expression($comp_expr): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\InputParameter $inputParam
     */
    public function walk_input_parameter($input_param): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\ArithmeticExpression $arithmeticExpr
     */
    public function walk_arithmetic_expression($arithmetic_expr): string
    {
        if ($arithmetic_expr->simple_arithmetic_expression !== null) {
            return $this->walk_simple_arithmetic_expression($arithmetic_expr->simple_arithmetic_expression);
        }
        if ($arithmetic_expr->subselect !== null) {
            return $arithmetic_expr->subselect->dispatch($this);
        }
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param AST\Node|string $simpleArithmeticExpr
     */
    public function walk_simple_arithmetic_expression($simple_arithmetic_expr): string
    {
        if (!$simple_arithmetic_expr instanceof AST\Simple_Arithmetic_Expression) {
            return $this->walk_arithmetic_term($simple_arithmetic_expr);
        }
        $types = [];
        foreach ($simple_arithmetic_expr->arithmetic_terms as $term) {
            if (!$term instanceof AST\Node) {
                // Skip '+' or '-'
                continue;
            }
            $types[] = $this->cast_string_literal_for_numeric_expression($this->unmarshal_type($this->walk_arithmetic_primary($term)));
        }
        return $this->marshal_type($this->infer_plus_minus_times_type($types));
    }
    /**
     * @param mixed $term
     */
    public function walk_arithmetic_term($term): string
    {
        if (!$term instanceof AST\Arithmetic_Term) {
            return $this->walk_arithmetic_factor($term);
        }
        $types = [];
        $operators = [];
        foreach ($term->arithmetic_factors as $factor) {
            if (!$factor instanceof AST\Node) {
                assert(is_string($factor));
                $operators[$factor] = $factor;
                continue;
                // Skip '*' or '/'
            }
            $types[] = $this->cast_string_literal_for_numeric_expression($this->unmarshal_type($this->walk_arithmetic_primary($factor)));
        }
        if (array_values($operators) === ['*']) {
            return $this->marshal_type($this->infer_plus_minus_times_type($types));
        }
        return $this->marshal_type($this->infer_division_type($types));
    }
    /**
     * @param list<Type> $termTypes
     */
    private function infer_plus_minus_times_type(array $term_types): Type
    {
        //                             mysql        sqlite     pdo_pgsql  pgsql
        // col_float                   float        float      string     float
        // col_decimal                 string       float|int  string     string
        // col_int                     int          int        int        int
        // col_bigint                  int          int        int        int
        // col_bool                    int          int        bool       bool
        //
        // col_int + col_int           int          int        int        int
        // col_int + col_float         float        float      string     float
        // col_float + col_float       float        float      string     float
        // col_float + col_decimal     float        float      string     float
        // col_int + col_decimal       string       float|int  string     string
        // col_decimal + col_decimal   string       float|int  string     string
        // col_string + col_string     float        int        x          x
        // col_int + col_string        float        int        x          x
        // col_bool + col_bool         int          int        x          x
        // col_int + col_bool          int          int        x          x
        // col_float + col_string      float        float      x          x
        // col_decimal + col_string    float        float|int  x          x
        // col_float + col_bool        float        float      x          x
        // col_decimal + col_bool      string       float|int  x          x
        $types = [];
        $types_no_null = [];
        foreach ($term_types as $term_type) {
            $generalized_type = $this->generalize_constant_type($term_type, false);
            $types[] = $generalized_type;
            $types_no_null[] = Type_Combinator::remove_null($generalized_type);
        }
        $union = Type_Combinator::union(...$types);
        $nullable = $this->can_be_null($union);
        $union_without_null = Type_Combinator::remove_null($union);
        if ($union_without_null->is_integer()->yes()) {
            return $this->create_integer($nullable);
        }
        if ($this->driver_type === Driver_Detector::SQLITE3 || $this->driver_type === Driver_Detector::PDO_SQLITE) {
            if (!$this->contains_only_numeric_types(...$types_no_null)) {
                return new Mixed_Type();
            }
            foreach ($types_no_null as $type_no_null) {
                if ($type_no_null->is_float()->yes()) {
                    return $this->create_float($nullable);
                }
            }
            return $this->create_float_or_int($nullable);
        }
        if ($this->driver_type === Driver_Detector::MYSQLI || $this->driver_type === Driver_Detector::PDO_MYSQL || $this->driver_type === Driver_Detector::PGSQL || $this->driver_type === Driver_Detector::PDO_PGSQL) {
            if ($this->contains_only_types($union_without_null, [new Integer_Type(), new Float_Type()])) {
                return $this->create_float($nullable);
            }
            if ($this->contains_only_types($union_without_null, [new Integer_Type(), $this->create_numeric_string(false)])) {
                return $this->create_numeric_string($nullable, $union_without_null->to_string()->is_lowercase_string()->yes(), $union_without_null->to_string()->is_uppercase_string()->yes());
            }
            if ($this->contains_only_numeric_types($union_without_null)) {
                return $this->create_float($nullable);
            }
        }
        return new Mixed_Type();
    }
    /**
     * @param list<Type> $termTypes
     */
    private function infer_division_type(array $term_types): Type
    {
        //                            mysql      sqlite    pdo_pgsql     pgsql
        // col_float =>               float      float     string        float
        // col_decimal =>             string     float|int string        string
        // col_int =>                 int        int       int           int
        // col_bigint =>              int        int       int           int
        //
        // col_int / col_int          string     int       int           int
        // col_int / col_float        float      float     string        float
        // col_float / col_float      float      float     string        float
        // col_float / col_decimal    float      float     string        float
        // col_int / col_decimal      string     float|int string        string
        // col_decimal / col_decimal  string     float|int string        string
        // col_string / col_string    null       null      x             x
        // col_int / col_string       null       null      x             x
        // col_bool / col_bool        string     int       x             x
        // col_int / col_bool         string     int       x             x
        // col_float / col_string     null       null      x             x
        // col_decimal / col_string   null       null      x             x
        // col_float / col_bool       float      float     x             x
        // col_decimal / col_bool     string     float     x             x
        $types = [];
        $types_no_null = [];
        foreach ($term_types as $term_type) {
            $generalized_type = $this->generalize_constant_type($term_type, false);
            $types[] = $generalized_type;
            $types_no_null[] = Type_Combinator::remove_null($generalized_type);
        }
        $union = Type_Combinator::union(...$types);
        $nullable = $this->can_be_null($union);
        $union_without_null = Type_Combinator::remove_null($union);
        if ($union_without_null->is_integer()->yes()) {
            if ($this->driver_type === Driver_Detector::MYSQLI || $this->driver_type === Driver_Detector::PDO_MYSQL) {
                return $this->create_numeric_string($nullable, true, true);
            }
            if ($this->driver_type === Driver_Detector::PDO_PGSQL || $this->driver_type === Driver_Detector::PGSQL || $this->driver_type === Driver_Detector::SQLITE3 || $this->driver_type === Driver_Detector::PDO_SQLITE) {
                return $this->create_integer($nullable);
            }
            return new Mixed_Type();
        }
        if ($this->driver_type === Driver_Detector::SQLITE3 || $this->driver_type === Driver_Detector::PDO_SQLITE) {
            if (!$this->contains_only_numeric_types(...$types_no_null)) {
                return new Mixed_Type();
            }
            foreach ($types_no_null as $type_no_null) {
                if ($type_no_null->is_float()->yes()) {
                    return $this->create_float($nullable);
                }
            }
            return $this->create_float_or_int($nullable);
        }
        if ($this->driver_type === Driver_Detector::MYSQLI || $this->driver_type === Driver_Detector::PDO_MYSQL || $this->driver_type === Driver_Detector::PGSQL || $this->driver_type === Driver_Detector::PDO_PGSQL) {
            if ($this->contains_only_types($union_without_null, [new Integer_Type(), new Float_Type()])) {
                return $this->create_float($nullable);
            }
            if ($this->contains_only_types($union_without_null, [new Integer_Type(), $this->create_numeric_string(false)])) {
                return $this->create_numeric_string($nullable, $union_without_null->to_string()->is_lowercase_string()->yes(), $union_without_null->to_string()->is_uppercase_string()->yes());
            }
            if ($this->contains_only_types($union_without_null, [new Float_Type(), $this->create_numeric_string(false)])) {
                return $this->create_float($nullable);
            }
            if ($this->contains_only_numeric_types($union_without_null)) {
                return $this->create_float($nullable);
            }
        }
        return new Mixed_Type();
    }
    /**
     * @param mixed $factor
     */
    public function walk_arithmetic_factor($factor): string
    {
        if (!$factor instanceof AST\Arithmetic_Factor) {
            return $this->walk_arithmetic_primary($factor);
        }
        $primary = $factor->arithmetic_primary;
        $type = $this->unmarshal_type($this->walk_arithmetic_primary($primary));
        if ($type instanceof Constant_Integer_Type && $factor->sign === false) {
            $type = new Constant_Integer_Type($type->get_value() * -1);
        } elseif ($type instanceof Integer_Range_Type && $factor->sign === false) {
            $type = Integer_Range_Type::from_interval($type->get_max() === null ? null : $type->get_max() * -1, $type->get_min() === null ? null : $type->get_min() * -1);
        } elseif ($type instanceof Constant_Float_Type && $factor->sign === false) {
            $type = new Constant_Float_Type($type->get_value() * -1);
        }
        return $this->marshal_type($type);
    }
    /**
     * @param mixed $primary
     */
    public function walk_arithmetic_primary($primary): string
    {
        // ResultVariable (TODO)
        if (is_string($primary)) {
            return $this->marshal_type(new Mixed_Type());
        }
        if ($primary instanceof AST\Node) {
            return $primary->dispatch($this);
        }
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param mixed $stringPrimary
     */
    public function walk_string_primary($string_primary): string
    {
        if ($string_primary instanceof AST\Node) {
            return $string_primary->dispatch($this);
        }
        return $this->marshal_type(new Mixed_Type());
    }
    /**
     * @param string $resultVariable
     */
    public function walk_result_variable($result_variable): string
    {
        return $this->marshal_type(new Mixed_Type());
    }
    private function unmarshal_type(string $marshalled_type): Type
    {
        $type = unserialize($marshalled_type);
        assert($type instanceof Type);
        return $type;
    }
    private function marshal_type(Type $type): string
    {
        // TreeWalker methods are supposed to return string, so we need to
        // marshal the types in strings
        return serialize($type);
    }
    private function is_query_component_nullable(string $dql_alias): bool
    {
        return $this->nullable_query_components[$dql_alias] ?? false;
    }
    /**
     * @param ClassMetadata<object> $class
     * @return array{string, ?class-string<BackedEnum>, ?list<string>} Doctrine type name, enum type of field, enum values
     */
    private function get_type_of_field(Class_Metadata $class, string $field_name): array
    {
        assert(isset($class->field_mappings[$field_name]));
        $metadata = $class->field_mappings[$field_name];
        /** @var string $type */
        $type = $metadata['type'];
        /** @var class-string<BackedEnum>|null $enumType */
        $enum_type = $metadata['enumType'] ?? null;
        if (!is_string($enum_type) || !class_exists($enum_type)) {
            $enum_type = null;
        }
        return [$type, $enum_type, $this->detect_enum_values($type, $metadata)];
    }
    /**
     * @param mixed $metadata
     *
     * @return list<string>|null
     */
    private function detect_enum_values(string $type_name, array $metadata): ?array
    {
        if ($type_name !== 'enum') {
            return null;
        }
        $values = $metadata['options']['values'] ?? [];
        if (!is_array($values) || count($values) === 0) {
            return null;
        }
        foreach ($values as $value) {
            if (!is_string($value)) {
                return null;
            }
        }
        return array_values($values);
    }
    /**
     * @param ?class-string<BackedEnum> $enumType
     * @param ?list<string> $enumValues
     */
    private function resolve_doctrine_type(string $type_name, ?string $enum_type = null, ?array $enum_values = null, bool $nullable = false): Type
    {
        try {
            $type = $this->descriptor_registry->get($type_name)->get_writable_to_property_type();
            if ($enum_type !== null) {
                if ($type->is_array()->no()) {
                    $type = new Object_Type($enum_type);
                } else {
                    $type = Type_Combinator::intersect(new Array_Type($type->get_iterable_key_type(), new Object_Type($enum_type)), ...Type_Utils::get_accessory_types($type));
                }
            }
            if ($enum_values !== null) {
                $enum_values_type = Type_Combinator::union(...array_map(static fn(string $value): \Php_Stan\Type\Constant\Constant_String_Type => new Constant_String_Type($value), $enum_values));
                $type = Type_Combinator::intersect($enum_values_type, $type);
            }
            if ($type instanceof Never_Type) {
                $type = new Mixed_Type();
            }
        } catch (Descriptor_Not_Registered_Exception $e) {
            if ($enum_type !== null) {
                $type = new Object_Type($enum_type);
            } else {
                $type = new Mixed_Type();
            }
        }
        if ($nullable) {
            return Type_Combinator::add_null($type);
        }
        return $type;
    }
    /**
     * @param ?class-string<BackedEnum> $enumType
     * @param ?list<string> $enumValues
     */
    private function resolve_database_internal_type(string $type_name, ?string $enum_type = null, ?array $enum_values = null, bool $nullable = false): Type
    {
        try {
            $descriptor = $this->descriptor_registry->get($type_name);
            $type = $descriptor instanceof Doctrine_Type_Driver_Aware_Descriptor ? $descriptor->get_database_internal_type_for_driver($this->em->get_connection()) : $descriptor->get_database_internal_type();
        } catch (Descriptor_Not_Registered_Exception $e) {
            $type = new Mixed_Type();
        }
        if ($enum_type !== null) {
            $enum_types = array_map(static fn(\Backed_Enum $enum_type): \Php_Stan\Type\Type => Constant_Type_Helper::get_type_from_value($enum_type->value), $enum_type::cases());
            $enum_type = Type_Combinator::union(...$enum_types);
            $enum_type = Type_Combinator::union($enum_type, $enum_type->to_string());
            $type = Type_Combinator::intersect($enum_type, $type);
        }
        if ($enum_values !== null) {
            $enum_values_type = Type_Combinator::union(...array_map(static fn(string $value): \Php_Stan\Type\Constant\Constant_String_Type => new Constant_String_Type($value), $enum_values));
            $type = Type_Combinator::intersect($enum_values_type, $type);
        }
        if ($nullable) {
            return Type_Combinator::add_null($type);
        }
        return $type;
    }
    private function can_be_null(Type $type): bool
    {
        return !$type->is_super_type_of(new Null_Type())->no();
    }
    /**
     * Returns whether the query has aggregate function and no group by clause
     *
     * Queries with aggregate functions and no group by clause always have
     * exactly 1 group. This implies that they return exactly 1 row, and that
     * all column can have a null value.
     *
     * c.f. SQL92, section 7.9, General Rules
     */
    private function has_aggregate_without_group_by(): bool
    {
        return $this->has_aggregate_function && !$this->has_group_by_clause;
    }
    /**
     * See analysis: https://github.com/janedbal/php-database-drivers-fetch-test
     *
     * Notable 8.1 changes:
     * - pdo_mysql: https://github.com/php/php-src/commit/c18b1aea289e8ed6edb3f6e6a135018976a034c6
     * - pdo_sqlite: https://github.com/php/php-src/commit/438b025a28cda2935613af412fc13702883dd3a2
     * - pdo_pgsql: https://github.com/php/php-src/commit/737195c3ae6ac53b9501cfc39cc80fd462909c82
     *
     * Notable 8.4 changes:
     * - pdo_pgsql: https://github.com/php/php-src/commit/6d10a6989897e9089d62edf939344437128e93ad
     *
     * @param IntegerType|FloatType|BooleanType $type
     */
    private function should_stringify_expressions(Type $type): Trinary_Logic
    {
        if (in_array($this->driver_type, [Driver_Detector::PDO_MYSQL, Driver_Detector::PDO_PGSQL, Driver_Detector::PDO_SQLITE], true)) {
            $stringify_fetches = isset($this->driver_options[PDO::ATTR_STRINGIFY_FETCHES]) && (bool) $this->driver_options[PDO::ATTR_STRINGIFY_FETCHES];
            if ($this->driver_type === Driver_Detector::PDO_MYSQL) {
                $emulated_prepares = isset($this->driver_options[PDO::ATTR_EMULATE_PREPARES]) ? (bool) $this->driver_options[PDO::ATTR_EMULATE_PREPARES] : true;
                if ($stringify_fetches) {
                    return Trinary_Logic::create_yes();
                }
                if ($this->php_version->get_version_id() >= 80100) {
                    return Trinary_Logic::create_no();
                }
                if ($emulated_prepares) {
                    return Trinary_Logic::create_yes();
                }
                return Trinary_Logic::create_no();
            }
            if ($this->driver_type === Driver_Detector::PDO_SQLITE) {
                if ($stringify_fetches) {
                    return Trinary_Logic::create_yes();
                }
                if ($this->php_version->get_version_id() >= 80100) {
                    return Trinary_Logic::create_no();
                }
                return Trinary_Logic::create_yes();
            }
            if ($this->driver_type === Driver_Detector::PDO_PGSQL) {
                // @phpstan-ignore-line always true, but keep it readable
                if ($type->is_boolean()->yes()) {
                    if ($this->php_version->get_version_id() >= 80100) {
                        return Trinary_Logic::create_from_boolean($stringify_fetches);
                    }
                    return Trinary_Logic::create_no();
                }
                if ($type->is_float()->yes()) {
                    if ($this->php_version->get_version_id() >= 80400) {
                        return Trinary_Logic::create_from_boolean($stringify_fetches);
                    }
                    return Trinary_Logic::create_yes();
                }
                return Trinary_Logic::create_from_boolean($stringify_fetches);
            }
        }
        if ($this->driver_type === Driver_Detector::PGSQL || $this->driver_type === Driver_Detector::SQLITE3 || $this->driver_type === Driver_Detector::MYSQLI) {
            return Trinary_Logic::create_no();
        }
        return Trinary_Logic::create_maybe();
    }
    private function is_supported_driver(): bool
    {
        return in_array($this->driver_type, [Driver_Detector::MYSQLI, Driver_Detector::PDO_MYSQL, Driver_Detector::PGSQL, Driver_Detector::PDO_PGSQL, Driver_Detector::SQLITE3, Driver_Detector::PDO_SQLITE], true);
    }
    private function simple_stringify(Type $type): Type
    {
        return Type_Traverser::map($type, static function (Type $type, callable $traverse): Type {
            if ($type instanceof Union_Type || $type instanceof Intersection_Type) {
                return $traverse($type);
            }
            if ($type instanceof Integer_Type || $type instanceof Float_Type || $type instanceof Boolean_Type) {
                return $type->to_string();
            }
            return $traverse($type);
        });
    }
    private function simple_floatify(Type $type): Type
    {
        return Type_Traverser::map($type, static function (Type $type, callable $traverse): Type {
            if ($type instanceof Union_Type || $type instanceof Intersection_Type) {
                return $traverse($type);
            }
            if ($type instanceof Integer_Type || $type instanceof Boolean_Type || $type instanceof String_Type) {
                return $type->to_float();
            }
            return $traverse($type);
        });
    }
}