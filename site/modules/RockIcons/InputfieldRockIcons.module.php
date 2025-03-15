<?php

namespace ProcessWire;

/**
 * @author Bernhard Baumrock, 29.12.2023
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 */
class InputfieldRockIcons extends InputfieldText implements InputfieldHasTextValue
{

  public static function getModuleInfo()
  {
    return [
      'title' => 'RockIcons',
      'version' => json_decode(file_get_contents(__DIR__ . "/package.json"))->version,
      'summary' => 'Inputfield to pick an icon',
      'icon' => 'smile-o',
      'requires' => [
        'RockIcons',
      ],
    ];
  }

  /**
   * Render the Inputfield
   * @return string
   */
  public function ___render()
  {
    $module = rockicons();

    // prepare icons
    $icons = "";
    foreach ($module->icons() as $name => $path) {
      $selected = ($name == $this->value) ? "selected" : "";
      $parts = explode(":", $name, 2);
      $group = $parts[0];
      $search = strtolower($parts[1]);
      $icons .= "<span
        class='icon $group $selected'
        title='$name'
        data-title='$name'
        data-search='$search'
        uk-tooltip>" . $module->svg($name) . "</span>";
    }

    // prepare selected preview
    $preview = "<span class='icon' hidden><svg width='24' height='24'></svg></span>";
    if ($this->value) {
      $preview = "<span class='icon selected uk-margin-small-right' title='{$this->value}' uk-tooltip>" . $module->svg($this->value, true) . "</span>";
    }

    $out = "
    <div class='rockicons'>

      <div hidden>
        {$this->name} <input class='value' name='{$this->name}' value='{$this->value}'>
      </div>

      <div class='uk-grid-collapse uk-flex-middle' uk-grid>
        <div class='uk-width-auto preview'>
          {$preview}
        </div>
        <div class='uk-width-expand'>
          <div class='uk-inline uk-width-1-1'>
            <span class='search uk-form-icon' uk-icon='icon: search'></span>
            <input class='uk-input search' type='text' aria-label='Not clickable icon'>
          </div>
        </div>
      </div>

      <div class='icons uk-margin-small-top'>
        <div>{icons}</div>
      </div>
    </div>
    <style>
      .rockicons span.icon svg {
        width: {$module->iconsize}px;
        height: {$module->iconsize}px;
      }
    </style>
    ";

    $out = str_replace("{icons}", $icons, $out);
    return $out;
  }
}
