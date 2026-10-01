<?php

namespace app\ui;

class File extends Field
{
    public function type(): string
    {
        return 'file';
    }

    public function storage(string $value): static
    {
        $this->extra["storage"] = $value;
        return $this;
    }
}
