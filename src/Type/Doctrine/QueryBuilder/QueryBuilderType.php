<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query_Builder;

use function md5;
use Php_Parser\Node\Expr\Method_Call;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\Type;
use function substr;
use function uniqid;
/** @api */
abstract class Query_Builder_Type extends Object_Type
{
    /** @var array<string, MethodCall> */
    private array $method_calls = [];
    final public function __construct(string $class_name, ?Type $subtracted_type = null)
    {
        parent::__construct($class_name, $subtracted_type);
    }
    /**
     * @return array<string, MethodCall>
     */
    public function get_method_calls(): array
    {
        return $this->method_calls;
    }
    public function append(Method_Call $method_call): self
    {
        $object = new static($this->get_class_name());
        $object->method_calls = $this->method_calls;
        $object->method_calls[substr(md5(uniqid()), 0, 10)] = $method_call;
        return $object;
    }
}