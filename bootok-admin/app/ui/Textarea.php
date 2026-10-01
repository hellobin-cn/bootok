<?php

namespace app\ui;

class Textarea extends Field
{
    public function type(): string
    {
        return 'textarea';
    }

    public function maxlength(int $value): static
    {
        $this->extra["maxlength"] = $value;
        return $this;
    }
}
