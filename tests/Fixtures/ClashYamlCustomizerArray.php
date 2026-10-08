<?php

return function (array $config): array {
    $config['rules'] = array_merge(['DOMAIN-SUFFIX,custom.example,DIRECT'], $config['rules']);
    $config['dns']['enable'] = true;

    return $config;
};
