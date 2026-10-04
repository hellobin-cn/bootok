<?php

use app\service\cloud\CdnCertManager;
use app\service\cloud\contract\CloudClientInterface;
use app\service\cloud\strategy\AliyunCdnCertStrategy;
use app\service\cloud\strategy\TencentCdnCertStrategy;
use app\service\mail\MailChannelManager;
use app\service\mail\MailService;
use app\service\storage\ChannelManager;
use app\service\storage\StorageInterface;

/**
 * PHP-DI 容器定义
 *
 * 依赖装配的唯一出口：业务类构造函数声明类型提示即可获得注入（无注解）。
 *
 * 存储装配：StorageInterface 绑定默认渠道驱动，业务代码不感知具体驱动。
 * 云平台装配：CloudClientInterface 为协议契约（按厂商端点瞬时构造）；
 * CdnCertManager 组装 CDN 证书部署策略清单，新增云厂商注册一行即可。
 * 邮件装配：业务代码注入 MailService 发送（渠道选择/模板渲染内聚在服务内）。
 */
return [
    StorageInterface::class   => fn () => (new ChannelManager())->default(),
    ChannelManager::class     => fn () => new ChannelManager(),
    CdnCertManager::class     => fn () => new CdnCertManager([
        new AliyunCdnCertStrategy(),
        new TencentCdnCertStrategy(),
    ]),
    MailService::class        => fn () => new MailService(new MailChannelManager()),
    MailChannelManager::class => fn () => new MailChannelManager(),
];
