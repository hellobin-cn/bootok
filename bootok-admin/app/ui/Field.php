<?php

namespace app\ui;

/**
 * 表单字段基类
 * toArray() 输出的结构与通用crud组件的表单协议一一对应
 */
abstract class Field
{
    /** 附加属性，如 required/help/default/placeholder 等 */
    protected array $extra = [];

    public function __construct(public string $field, public string $label)
    {
    }

    abstract public function type(): string;

    public static function make(string $field, string $label): static
    {
        return new static($field, $label);
    }

    public function required(bool $value = true): static
    {
        $this->extra['required'] = $value;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->extra['default'] = $value;
        return $this;
    }

    public function help(string $value): static
    {
        $this->extra['help'] = $value;
        return $this;
    }

    public function placeholder(string $value): static
    {
        $this->extra['placeholder'] = $value;
        return $this;
    }

    public function prepend(string $value): static
    {
        $this->extra['prepend'] = $value;
        return $this;
    }

    public function append(string $value): static
    {
        $this->extra['append'] = $value;
        return $this;
    }

    /**
     * 表单栅格跨度（24 等分制，默认 24 通栏；12 即半宽两列布局），
     * crud 弹窗按该值包裹 a-col
     */
    public function col(int $span): static
    {
        $this->extra['col'] = max(1, min(24, $span));
        return $this;
    }

    /**
     * 文本类字段为长度上限，数值类字段为最大值
     */
    public function max(int|float $value): static
    {
        $this->extra['max'] = $value;
        return $this;
    }

    public function min(int|float $value): static
    {
        $this->extra['min'] = $value;
        return $this;
    }

    public function toArray(): array
    {
        return array_merge([
            'field' => $this->field,
            'type'  => $this->type(),
            'label' => $this->label,
        ], $this->extra);
    }
}
