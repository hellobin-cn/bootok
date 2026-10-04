<?php

use app\storage\ChannelManager;
use app\storage\StorageInterface;

/**
 * PHP-DI 容器定义
 *
 * 存储装配的唯一出口：业务代码只依赖 StorageInterface（#[Inject]），
 * 具体驱动由存储渠道（sys_storage_channel）决定，渠道切换不改业务代码。
 */
return [
    StorageInterface::class => fn () => (new ChannelManager())->default(),
    ChannelManager::class   => fn () => new ChannelManager(),
];
