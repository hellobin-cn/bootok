<?php

namespace app\entity;

use app\enum\Code;

class ServiceResult
{
    public string $code;
    public string $message;

    public ?array $data = null;

    public function __construct(?string $code = null, ?string $message = null, ?array $data = null)
    {
        $this->code = $code ?: Code::SUCCESS->value;
        $this->message = $message ?: Code::SUCCESS->getDescription();
        $this->data = $data;
    }
}