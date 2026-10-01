<?php

namespace app\ui;

class Number extends Field
{
    public function type(): string
    {
        return 'number';
    }

    public function step(float $value): static
    {
        $this->extra["step"] = $value;
        return $this;
    }
}
