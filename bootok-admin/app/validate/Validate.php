<?php

namespace app\validate;

/**
 * 轻量验证器基类
 * 规则串语法：require|max:50|in:1,2|regex:^\d+$|confirm:rePassword 等
 * 规则值为自定义方法名时（不含内置规则分隔符），调用本类的同名方法，返回 true 或错误信息
 */
class Validate
{
    protected array $rule = [];
    protected array $message = [];
    protected array $scene = [];

    protected string $error = '';
    protected string $currentScene = '';
    private array $activeFields = [];

    public function hasScene(string $name): bool
    {
        return isset($this->scene[$name]);
    }

    public function scene(string $name): static
    {
        $this->currentScene = $name;
        $this->activeFields = $this->scene[$name] ?? [];
        return $this;
    }

    public function check(array $data): bool
    {
        foreach ($this->activeFields as $field) {
            $rules = $this->rule[$field] ?? '';
            if ($rules === '') {
                continue;
            }
            $value = $data[$field] ?? null;
            $present = $value !== null && $value !== '';
            foreach (explode('|', $rules) as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, '');
                if ($name == 'require') {
                    if (!$present) {
                        return $this->fail($field, 'require');
                    }
                    continue;
                }
                if (!$present) {
                    continue;
                }
                if (method_exists($this, $name)) {
                    $result = $this->{$name}($value, $param, $data);
                    if ($result !== true) {
                        return $this->fail($field, $name, is_string($result) ? $result : '');
                    }
                    continue;
                }
                if (!$this->verify($value, $name, $param, $data)) {
                    return $this->fail($field, $name);
                }
            }
        }
        return true;
    }

    public function getError(): string
    {
        return $this->error;
    }

    protected function verify(mixed $value, string $name, string $param, array $data): bool
    {
        return match ($name) {
            'max'     => mb_strlen((string)$value) <= (int)$param,
            'min'     => mb_strlen((string)$value) >= (int)$param,
            'numeric' => is_numeric($value),
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'email'   => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'mobile'  => (bool)preg_match('/^1\d{10}$/', (string)$value),
            'in'      => in_array((string)$value, explode(',', $param)),
            'regex'   => (bool)preg_match('/' . str_replace('/', '\/', $param) . '/', (string)$value),
            'confirm' => (string)$value === (string)($data[$param] ?? ''),
            default   => true,
        };
    }

    private function fail(string $field, string $rule, string $custom = ''): bool
    {
        $this->error = $custom ?: ($this->message[$field . '.' . $rule] ?? $this->message[$field] ?? ($field . '不符合要求'));
        return false;
    }
}
