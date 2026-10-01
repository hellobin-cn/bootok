<?php
$builder = new \DI\ContainerBuilder();
$builder->addDefinitions(config('dependence', []));
$builder->useAutowiring(true);
$builder->useAttributes(true);
try {
    return $builder->build();
} catch (Exception $e) {
    return null;
}