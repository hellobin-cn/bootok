<?php
function get_env($key, $default = null)
{
    $value = getenv($key);
    return $value ? $value : $default;
}