<?php

namespace app\process;

use app\service\mail\MailService;
use support\Redis;
use Throwable;

/**
 * 队列动作注册表（共享定义，与 TaskRegistry 同哲学）
 * 安全边界：sys_queue 只存动作键，处理器永远在代码里——绝不 eval 用户输入
 * 新增消费者：在此登记动作键，业务侧 QueueService::dispatch('键', 参数)
 */
class QueueRegistry
{
    public static function all(): array
    {
        return [
            'demo.echo' => [
                'title' => '演示：回写标记（联调/验收用）',
                'action' => function (array $args) {
                    Redis::setex('bootok:queue:demo:' . ($args['marker'] ?? 'default'), 600, date('Y-m-d H:i:s'));
                },
            ],
            'demo.fail' => [
                'title' => '演示：必失败（验证重试与死信）',
                'action' => function (array $args) {
                    throw new \RuntimeException('demo.fail 永远失败（第 ' . ($args['attempt_hint'] ?? '?') . ' 次尝试语义）');
                },
            ],
            'mail.template_send' => [
                'title' => '异步发送模板邮件（批量场景）',
                'action' => function (array $args) {
                    $result = (new MailService())->sendTemplate(
                        (string) ($args['code'] ?? ''),
                        (array) ($args['to'] ?? []),
                        (array) ($args['vars'] ?? [])
                    );
                    if (empty($result['ok'])) {
                        throw new \RuntimeException((string) ($result['message'] ?? '模板邮件发送失败'));
                    }
                },
            ],
            'wechat.publish_article' => [
                'title' => '文章发布到公众号（图片转存+建草稿+可选发布）',
                'action' => function (array $args) {
                    \support\Container::get(\app\service\wechat\Publisher::class)->runJob(
                        (int) ($args['article_id'] ?? 0),
                        (bool) ($args['auto_publish'] ?? false),
                        (string) ($args['digest'] ?? '')
                    );
                },
            ],
        ];
    }

    public static function has(string $action): bool
    {
        return isset(self::all()[$action]);
    }

    /**
     * @throws Throwable 动作不存在或处理器抛出
     */
    public static function execute(string $action, array $args): void
    {
        $entry = self::all()[$action] ?? throw new \RuntimeException("队列动作未登记: {$action}");
        ($entry['action'])($args);
    }
}
