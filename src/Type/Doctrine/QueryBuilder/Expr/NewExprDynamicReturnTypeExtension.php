<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query_Builder\Expr;

use function class_exists;
use Php_Parser\Node\Expr\Static_Call;
use Php_Parser\Node\Name;
use Php_Stan\Analyser\Scope;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Reflection\Reflection_Provider;
use Php_Stan\Rules\Doctrine\ORM\Dynamic_Query_Builder_Argument_Exception;
use Php_Stan\Should_Not_Happen_Exception;
use Php_Stan\Type\Doctrine\Arguments_Processor;
use Php_Stan\Type\Dynamic_Static_Method_Return_Type_Extension;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\Type;
class New_Expr_Dynamic_Return_Type_Extension implements Dynamic_Static_Method_Return_Type_Extension
{
    private Arguments_Processor $arguments_processor;
    /** @var class-string */
    private string $class;
    private Reflection_Provider $reflection_provider;
    /**
     * @param class-string $class
     */
    public function __construct(Arguments_Processor $arguments_processor, string $class, Reflection_Provider $reflection_provider)
    {
        $this->arguments_processor = $arguments_processor;
        $this->class = $class;
        $this->reflection_provider = $reflection_provider;
    }
    public function get_class(): string
    {
        return $this->class;
    }
    public function is_static_method_supported(Method_Reflection $method_reflection): bool
    {
        return $method_reflection->get_name() === '__construct';
    }
    public function get_type_from_static_method_call(Method_Reflection $method_reflection, Static_Call $method_call, Scope $scope): Type
    {
        if (!$method_call->class instanceof Name) {
            throw new Should_Not_Happen_Exception();
        }
        $class_name = $scope->resolve_name($method_call->class);
        if (!$this->reflection_provider->has_class($class_name)) {
            return new Object_Type($class_name);
        }
        if (!class_exists($class_name)) {
            return new Object_Type($class_name);
        }
        try {
            $expr_object = new $class_name(...$this->arguments_processor->process_args($scope, $method_reflection->get_name(), $method_call->get_args()));
        } catch (Dynamic_Query_Builder_Argument_Exception $e) {
            return new Object_Type($this->reflection_provider->get_class_name($class_name));
        }
        return new Expr_Type($class_name, $expr_object);
    }
}