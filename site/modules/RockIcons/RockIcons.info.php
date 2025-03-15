<?php

namespace ProcessWire;

$info = [
  'title' => 'RockIcons',
  'version' => json_decode(file_get_contents(__DIR__ . "/package.json"))->version,
  'summary' => 'Fieldtype module to select SVG icons',
  'autoload' => true,
  'singular' => true,
  'icon' => 'smile-o',
  'requires' => [
    'PHP>=8.0',
  ],
  'installs' => [
    'InputfieldRockIcons',
  ],
];
