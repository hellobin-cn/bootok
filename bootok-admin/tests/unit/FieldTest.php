<?php

namespace tests\unit;

use app\enum\ArticleStatus;
use app\ui\Number;
use app\ui\Select;
use app\ui\Text;
use PHPUnit\Framework\TestCase;

class FieldTest extends TestCase
{
    public function testTextToArrayProtocol(): void
    {
        $field = Text::make('title', '标题')->required()->max(100)->help('必填');
        $this->assertSame([
            'field'    => 'title',
            'type'     => 'text',
            'label'    => '标题',
            'required' => true,
            'max'      => 100,
            'help'     => '必填',
        ], $field->toArray());
    }

    public function testSelectWithEnumImplementsLabeled(): void
    {
        $field = Select::make('status', '状态')->options(ArticleStatus::class)->default(1);
        $arr = $field->toArray();
        $this->assertSame('select', $arr['type']);
        $this->assertSame(1, $arr['default']);
        $this->assertSame([
            ['value' => 0, 'label' => '下架'],
            ['value' => 1, 'label' => '上架'],
        ], $arr['data']);
    }

    public function testSelectWithAssociativeArray(): void
    {
        $field = Select::make('type', '类型')->options(['a' => '甲', 'b' => '乙']);
        $this->assertSame([
            ['value' => 'a', 'label' => '甲'],
            ['value' => 'b', 'label' => '乙'],
        ], $field->toArray()['data']);
    }

    public function testNumberRange(): void
    {
        $arr = Number::make('sort', '排序')->min(0)->max(999)->step(10)->default(0)->toArray();
        // step 的参数声明为 float，10 会规格化为 10.0，前端渲染等价
        $this->assertSame(['min' => 0, 'max' => 999, 'step' => 10.0, 'default' => 0], array_diff_key($arr, array_flip(['field', 'type', 'label'])));
        $this->assertSame('number', $arr['type']);
        $this->assertSame('sort', $arr['field']);
    }

    public function testDefaultsAreOmittedWhenUnset(): void
    {
        $arr = Text::make('name', '名称')->toArray();
        $this->assertSame(['field' => 'name', 'type' => 'text', 'label' => '名称'], $arr);
    }
}
