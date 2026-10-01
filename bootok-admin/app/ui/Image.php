<?php

namespace app\ui;

class Image extends Field
{
    public function type(): string
    {
        return 'file';
    }

    public function storage(string $value = "image"): static
    {
        $this->extra["storage"] = $value;
        return $this;
    }
}
