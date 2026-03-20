<?php

declare (strict_types=1);
namespace Php_Stan\Reflection\Doctrine;

use Php_Stan\Reflection\Class_Member_Reflection;
use Php_Stan\Reflection\Class_Reflection;
use Php_Stan\Reflection\Function_Variant;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Should_Not_Happen_Exception;
use Php_Stan\Trinary_Logic;
use Php_Stan\Type\Array_Type;
use Php_Stan\Type\Generic\Template_Type_Map;
use Php_Stan\Type\Integer_Type;
use Php_Stan\Type\Mixed_Type;
use Php_Stan\Type\Null_Type;
use Php_Stan\Type\String_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Union_Type;
use function strpos;
class Magic_Repository_Method_Reflection implements Method_Reflection
{
    private Class_Reflection $declaring_class;
    private string $name;
    private Type $type;
    public function __construct(Class_Reflection $declaring_class, string $name, Type $type)
    {
        $this->declaring_class = $declaring_class;
        $this->name = $name;
        $this->type = $type;
    }
    public function get_declaring_class(): Class_Reflection
    {
        return $this->declaring_class;
    }
    public function is_static(): bool
    {
        return false;
    }
    public function is_private(): bool
    {
        return false;
    }
    public function is_public(): bool
    {
        return true;
    }
    public function get_doc_comment(): ?string
    {
        return null;
    }
    public function get_name(): string
    {
        return $this->name;
    }
    public function get_prototype(): Class_Member_Reflection
    {
        return $this;
    }
    public function get_variants(): array
    {
        if (strpos($this->name, 'findBy') === 0) {
            $arguments = [new Dummy_Parameter('argument', new Mixed_Type(), false, null, false, null), new Dummy_Parameter('orderBy', new Union_Type([new Array_Type(new String_Type(), new String_Type()), new Null_Type()]), true, null, false, null), new Dummy_Parameter('limit', new Union_Type([new Integer_Type(), new Null_Type()]), true, null, false, null), new Dummy_Parameter('offset', new Union_Type([new Integer_Type(), new Null_Type()]), true, null, false, null)];
        } elseif (strpos($this->name, 'findOneBy') === 0) {
            $arguments = [new Dummy_Parameter('argument', new Mixed_Type(), false, null, false, null), new Dummy_Parameter('orderBy', new Union_Type([new Array_Type(new String_Type(), new String_Type()), new Null_Type()]), true, null, false, null)];
        } elseif (strpos($this->name, 'countBy') === 0) {
            $arguments = [new Dummy_Parameter('argument', new Mixed_Type(), false, null, false, null)];
        } else {
            throw new Should_Not_Happen_Exception();
        }
        return [new Function_Variant(Template_Type_Map::create_empty(), null, $arguments, false, $this->type)];
    }
    public function is_deprecated(): Trinary_Logic
    {
        return Trinary_Logic::create_no();
    }
    public function get_deprecated_description(): ?string
    {
        return null;
    }
    public function is_final(): Trinary_Logic
    {
        return Trinary_Logic::create_no();
    }
    public function is_internal(): Trinary_Logic
    {
        return Trinary_Logic::create_no();
    }
    public function get_throw_type(): ?Type
    {
        return null;
    }
    public function has_side_effects(): Trinary_Logic
    {
        return Trinary_Logic::create_no();
    }
}