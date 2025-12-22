<?php

function loadini($path, $defaults = []) {
    if (!file_exists($path)) {
        return $defaults;
    }

    if (!is_readable($path)) {
        throw new Exception("Cannot read INI file: $path");
    }

    $data = parse_ini_file($path, true);
    return array_merge($defaults, $data ?: []);
}