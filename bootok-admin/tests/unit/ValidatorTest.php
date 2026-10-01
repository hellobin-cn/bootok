<?php

namespace tests\unit;

use app\validate\Admin;
use app\validate\Article;
use app\validate\Group;
use PHPUnit\Framework\TestCase;

/**
 * 不触库的验证器场景（唯一性检查等依赖数据库的规则不在此覆盖）
 */
class ValidatorTest extends TestCase
{
    public function testLoginSceneRequiresUsernameAndPassword(): void
    {
        $v = new Admin();
        $this->assertFalse($v->scene('login')->check(['username' => 'a', 'password' => '']));
        $this->assertSame('请输入密码', $v->getError());
    }

    public function testLoginScenePasses(): void
    {
        $v = new Admin();
        $this->assertTrue($v->scene('login')->check(['username' => 'a', 'password' => 'x']));
    }

    public function testUpdatePasswordConfirmRule(): void
    {
        $v = new Admin();
        $this->assertFalse($v->scene('updatePassword')->check(['old_password' => 'a', 'new_password' => '123456', 're_password' => 'other']));
        $this->assertSame('两次输入的密码不一致', $v->getError());
        $this->assertTrue((new Admin())->scene('updatePassword')->check(['old_password' => 'a', 'new_password' => '123456', 're_password' => '123456']));
    }

    public function testUpdatePasswordMinLength(): void
    {
        $v = new Admin();
        $this->assertFalse($v->scene('updatePassword')->check(['old_password' => 'a', 'new_password' => '123', 're_password' => '123']));
        $this->assertSame('新密码不能少于6位', $v->getError());
    }

    public function testEditSceneAllowsEmptyPassword(): void
    {
        // 编辑时密码留空表示不修改；直接测规则方法避免唯一性检查触库
        $v = new Admin();
        $v->scene('edit');
        $rule = new \ReflectionMethod($v, 'checkAdminPassword');
        $this->assertTrue($rule->invoke($v, '', '', []));
        $this->assertSame('密码不能少于6位', $rule->invoke($v, '123', '', []));
    }

    public function testCreateSceneRejectsEmptyPassword(): void
    {
        $v = new Admin();
        $v->scene('create');
        $rule = new \ReflectionMethod($v, 'checkAdminPassword');
        $this->assertSame('请输入密码', $rule->invoke($v, '', '', []));
        $this->assertTrue($rule->invoke($v, '123456', '', []));
    }

    public function testGroupNameRequired(): void
    {
        $v = new Group();
        $this->assertFalse($v->scene('create')->check(['group_name' => '']));
        $this->assertSame('请输入名称', $v->getError());
    }

    public function testArticleTitleRequired(): void
    {
        $v = new Article();
        $this->assertFalse($v->scene('create')->check(['title' => '']));
        $this->assertSame('请输入标题', $v->getError());
        $this->assertTrue((new Article())->scene('edit')->check(['title' => '正常标题']));
    }
}
