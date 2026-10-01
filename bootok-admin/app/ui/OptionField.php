<?php

namespace app\ui;

/**
 * 带选项字段基类（Select/Radio/Checkbox）
 * options 接受关联数组或 BackedEnum 类名（枚举实现 LabeledEnum 时取 label，否则取 name）
 */
abstract class OptionField extends Field
{
    protected array $data = [];

    public function options(array|string $options): static
    {
        if (is_string($options) && is_a($options, \BackedEnum::class, true)) {
            $this->data = array_map(
                fn(\BackedEnum $case) => [
                    'value' => $case->value,
                    'label' => $case instanceof \app\enum\LabeledEnum ? $case->label() : $case->name,
                ],
                $options::cases()
            );
            return $this;
        }
        foreach ($options as $value => $label) {
            $this->data[] = ['value' => $value, 'label' => $label];
        }
        return $this;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), ['data' => $this->data]);
    }
}
