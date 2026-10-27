<?php

$finder = (new PhpCsFixer\Finder())
    ->in(array_filter([__DIR__ . '/src', __DIR__ . '/tests'], 'is_dir'))
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
    ])
    ->setFinder($finder)
;
