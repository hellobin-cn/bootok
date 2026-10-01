<?php

use app\enum\Code;
use support\Response;

/**
 * 成功响应
 */
function json_success(string $requestId = '', $data = null, string $message = '操作成功', string $code = Code::SUCCESS->value, array $header = []): Response
{
    $result = [
        'code'       => $code,
        'data'       => $data,
        'message'    => $message,
        'request_id' => $requestId,
    ];

    if (is_string($data)) {
        $result['message'] = $data;
        $result['data'] = [];
    }

    return json($result)->withHeaders($header);
}

/**
 * 失败响应
 */
function json_error(string $requestId = '', string $message = '操作失败', string $code = Code::FAIL->value, $data = null, array $header = []): Response
{
    $result = [
        'code'       => $code,
        'message'    => $message,
        'data'       => $data,
        'request_id' => $requestId,
    ];

    return json($result)->withHeaders($header);
}

/**
 * 把返回的数据集转换成Tree
 */
function list_to_tree(array $list, string $pk = 'id', string $pid = 'pid', string $child = 'children', int $root = 0): array
{
    $tree = [];
    $refer = [];
    foreach ($list as $key => $data) {
        $refer[$data[$pk]] = &$list[$key];
    }
    foreach ($list as $key => $data) {
        $parentId = $data[$pid];
        if ($root == $parentId) {
            $tree[] = &$list[$key];
        } else {
            if (isset($refer[$parentId])) {
                $parent = &$refer[$parentId];
                $parent[$child][] = &$list[$key];
            }
        }
    }
    return $tree;
}

/**
 * 生成密码散列
 */
function make_password(string $plain): string
{
    return \password_hash($plain, PASSWORD_BCRYPT);
}

/**
 * 校验密码
 */
function verify_password(string $plain, string $hash): bool
{
    return \password_verify($plain, $hash);
}
