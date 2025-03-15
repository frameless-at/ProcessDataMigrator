<?php

namespace ProcessWire;

/**
 * @author Bernhard Baumrock, 29.12.2023
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 */

function rockicons(): RockIcons
{
  return wire()->modules->get('RockIcons');
}

class RockIcons extends WireData implements Module, ConfigurableModule
{
  const cache = "rockicons";
  const cachegroups = "rockicons-groups";

  /** @var array */
  public $icons = [];

  /** Sort icons by group or name */
  public $sortIcons = 'name';

  /** Icon groups to use (like svg-filled) */
  public $useIcons;

  public function init()
  {
    $this->wire('rockicons', $this);
    $this->createAssets();

    // hooks
    wire()->addHookAfter("Modules::refresh", function () {
      wire()->cache->delete(self::cache);
    });
    wire()->addHookAfter("FieldtypeText::formatValue", $this, "hookFormatValue");
  }

  /**
   * Get all available icons not taking module config into account
   */
  public function allIcons(): array
  {
    $icons = wire()->cache->get(self::cache, function () {
      return $this->getIcons();
    });

    // check if cache is outdated
    // this can happen on new deployments
    // see https://processwire.com/talk/topic/30610--
    // get first icon
    $firstKey = @array_keys($icons)[0];
    $firstIcon = @$icons[$firstKey];

    // no icons? return empty array
    if (!$firstIcon) return $icons;

    // file exists? all good!
    if (is_file($firstIcon)) return $icons;

    // cache is outdated, recreate it and get icons again
    $this->resetCache();
    return $this->allIcons();
  }

  private function createAssets(): void
  {
    if (!function_exists('\ProcessWire\rockfrontend')) return;
    rockfrontend()->lessToCss(__DIR__ . "/InputfieldRockIcons.less", false);
  }

  /**
   * Get groups from cache or recreate cache
   */
  private function getGroups(): array
  {
    $groups = wire()->cache->get(self::cachegroups);
    if (is_array($groups)) return $groups;
    $this->getIcons(); // recreate cache
    return wire()->cache->get(self::cachegroups);
  }

  public function hookFormatValue(HookEvent $event): void
  {
    $field = $event->arguments('field');
    if ($field->inputfieldClass !== 'InputfieldRockIcons') return;
    $value = $event->return;
    if (!$value) return;

    // create icon object
    require_once __DIR__ . "/RockIcon.php";
    $icon = new RockIcon();
    $icon->svg = trim($this->svg($value));
    $icon->name = $event->return;

    // return icon object
    $event->return = $icon;
  }

  public function html(string $str): \Latte\Runtime\Html|string
  {
    if (wire()->modules->isInstalled("RockFrontend")) {
      return rockfrontend()->html($str);
    }
    return $str;
  }

  public function icons(): array
  {
    if (count($this->icons)) return $this->icons;

    // during development we reset the cache every time
    // wire()->cache->delete(self::cache);

    // get all icons
    $allIcons = $this->allIcons();

    // filter only selected groups like svg-filled:...
    $icons = array_filter($allIcons, function ($name) {
      $groups = $this->useIcons ?: [];
      foreach ($groups as $group) {
        if (str_starts_with($name, "$group:")) return true;
      }
      return false;
    }, ARRAY_FILTER_USE_KEY);

    // sort icons based on user config
    if ($this->sortIcons == 'group') {
      // keep array as it is (sorted by group = sorted by file path)
    } else {
      // sort by basename
      uasort($icons, function ($a, $b) {
        $nameA = $this->iconName($a);
        $nameB = $this->iconName($b);
        return strcasecmp($nameA, $nameB);
      });
    }

    // save icons and return them
    return $this->icons = $icons;
  }

  /**
   * Get array of all available icons
   * @return array
   * @throws WireException
   */
  public function getIcons(): array
  {
    $icons = [];
    $groups = [];
    $files = wire()->files->find(
      wire()->config->paths->templates . "RockIcons",
      ['extensions' => ['svg']]
    );

    // loop all files
    foreach ($files as $file) {
      // get short name relative to icons folder
      // eg /svg/filled/foo.svg
      $short = str_replace(
        wire()->config->paths->templates . "RockIcons/",
        "",
        $file
      );

      // get basename, eg foo.svg
      $base = basename($file);

      $parts = explode("/", $short, 2);
      if ($parts[1] !== $base) {
        $second = explode("/", $parts[1], 2)[0];
        $dir = wire()->sanitizer->pageName($parts[0] . "-" . $second);
      } else {
        $dir = $parts[0];
      }

      // count icons in group (increase +1 for each icon)
      $groups[$dir] = (int)@$groups[$dir] + 1;

      // create name like svg-filled:foo
      $iconName = $this->iconName($file);
      $name = strtolower("$dir:$iconName");

      // save path to icon
      $icons[$name] = Paths::normalizeSeparators($file);
    }

    // save groups to cache
    wire()->cache->save(self::cachegroups, $groups);

    return $icons;
  }

  public function iconName(string $file): string
  {
    return wire()->sanitizer->pageName(substr(basename($file), 0, -4));
  }

  private function resetCache(): void
  {
    wire()->cache->delete(self::cache);
    wire()->cache->delete(self::cachegroups);
  }

  public function svg(string $name, $silent = false): \Latte\Runtime\Html|string
  {
    if ($silent && !array_key_exists($name, $this->icons())) {
      return "";
    }
    $path = $this->icons()[$name];
    return wire()->files->fileGetContents($path);
  }

  /**
   * Config inputfields
   * @param InputfieldWrapper $inputfields
   */
  public function getModuleConfigInputfields($inputfields)
  {
    if (wire()->input->post->submit_save_module) {
      $this->resetCache();
    }

    $groups = $this->getGroups();
    $f = wire()->modules->get('InputfieldCheckboxes');
    $f->name = 'useIcons';
    $f->label = "Icons to use";
    $f->icon = 'magic';
    foreach ($groups as $name => $cnt) {
      $f->addOption($name, "$name: $cnt icons");
    }
    $f->value = $this->useIcons;
    $f->notes = "Upload custom icons to /site/templates/RockIcons/[your-iconset]/* and save this page to refresh the list of icons.
      [https://fontawesome.com/download](https://fontawesome.com/download)
      [https://github.com/tabler/tabler-icons/releases](https://github.com/tabler/tabler-icons/releases)
      [https://github.com/480-Design/Solar-Icon-Set/releases](https://github.com/480-Design/Solar-Icon-Set/releases)
      [https://icones.js.org/](https://icones.js.org/) (Collection of many free iconsets)
      ";
    $inputfields->add($f);

    $inputfields->add([
      'type' => 'integer',
      'label' => "Icon size (in px)",
      'name' => 'iconsize',
      'icon' => 'square',
      'value' => $this->iconsize ?: 24,
    ]);

    $f = wire()->modules->get('InputfieldRadios');
    $f->name = 'sortIcons';
    $f->label = "Sort Icons by ...";
    $f->icon = 'sort-alpha-asc';
    $f->addOption('name', 'Name:Group (eg processwire:brands)');
    $f->addOption('group', 'Group:Name (eg brands:processwire)');
    $f->value = $this->sortIcons;
    $inputfields->add($f);

    $cnt = count($this->icons());
    $inputfields->add([
      'type' => 'RockIcons',
      'label' => "Preview ($cnt Icons)",
      'name' => 'preview',
      'icon' => 'smile-o',
      'value' => $this->preview,
    ]);

    return $inputfields;
  }

  public function __debugInfo(): array
  {
    return [
      'icons' => $this->icons(),
    ];
  }
}
