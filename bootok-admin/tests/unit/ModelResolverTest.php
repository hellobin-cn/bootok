<?php

namespace tests\unit;

use app\attribute\AutoResource;
use app\service\ModelResolver;
use PHPUnit\Framework\TestCase;

class ModelResolverTest extends TestCase
{
    public function testResolveKebabToAnnotatedModel(): void
    {
        $this->assertSame(\app\model\Article::class, ModelResolver::resolve('article'));
        $this->assertSame(\app\model\OperationLog::class, ModelResolver::resolve('operation-log'));
        $this->assertSame(\app\model\SystemConfig::class, ModelResolver::resolve('system-config'));
    }

    public function testRejectsUnannotatedModel(): void
    {
        // app\model\Model 基类本身没有注解
        $this->assertNull(ModelResolver::resolve('model'));
    }

    public function testRejectsNonExistentModel(): void
    {
        $this->assertNull(ModelResolver::resolve('no-such-model'));
    }

    public function testRejectsIllegalPathCharacters(): void
    {
        $this->assertNull(ModelResolver::resolve('../Config'));
        $this->assertNull(ModelResolver::resolve('Article;drop'));
        $this->assertNull(ModelResolver::resolve(''));
        $this->assertNull(ModelResolver::resolve('UPPER'));
    }

    public function testAttributeActionsWhitelist(): void
    {
        $attr = ModelResolver::attribute(\app\model\OperationLog::class);
        $this->assertNotNull($attr);
        $this->assertTrue($attr->allows('getRows'));
        $this->assertFalse($attr->allows('create'));
        $this->assertFalse($attr->allows('delete'));
    }

    public function testAttributeExcept(): void
    {
        $attr = ModelResolver::attribute(\app\model\Admin::class);
        $this->assertNotNull($attr);
        $this->assertFalse($attr->allows('deleteFile'));
        $this->assertTrue($attr->allows('create'));
    }

    public function testReadMark(): void
    {
        $this->assertSame('admin.rows', ModelResolver::attribute(\app\model\Admin::class)->readMark());
        $this->assertSame('', ModelResolver::attribute(\app\model\Article::class)->readMark());
    }

    public function testOnlyExceptCombination(): void
    {
        $attr = new AutoResource(name: 'x', only: ['getRows', 'getItem'], except: ['getItem']);
        $this->assertSame(['getRows'], $attr->actions);
        $this->assertTrue($attr->allows('getRows'));
        $this->assertFalse($attr->allows('getItem'));
    }
}
