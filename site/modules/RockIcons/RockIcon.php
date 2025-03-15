<?php

namespace ProcessWire;

class RockIcon extends WireData
{
  public $name;
  public $svg;

  public function __toString()
  {
    return $this->svg;
  }

  public function __debugInfo()
  {
    return [
      'name' => $this->name,
      'svg' => $this->svg,
    ];
  }
}
