// select icon on click
$(document).on("click", ".rockicons .icon", (e) => {
  // prevent firing the event more than once on child elements
  e.stopPropagation();
  let $root = $(e.target).closest(".rockicons");
  let $preview = $root.find(".preview");
  let $icons = $root.find(".icons");
  let $span = $(e.target).closest("span.icon");
  let $value = $root.find("input.value");
  let $clone = $span.clone().addClass("selected uk-margin-small-right");
  let name = $clone.attr("data-title");
  let $inputfield = $root.closest(".Inputfield");

  // select icon
  if (!$span.hasClass("selected")) {
    // remove selection from old selected icon
    $icons.find("span.icon").removeClass("selected");

    // select this icon
    $span.addClass("selected");

    // copy icon to preview
    $clone.attr("title", name);
    UIkit.tooltip($clone);
    $preview.html($clone);

    // update input value
    $value.val(name);
    $inputfield.addClass("InputfieldStateChanged");
  }
  // unselect icon
  else {
    $icons.find("span.icon").removeClass("selected");
    $preview.html("");
    $value.val("");
    $inputfield.addClass("InputfieldStateChanged");
  }

  // trigger change event on $value input
  $value.trigger("change");
});

// search icon
$(document).ready(() => {
  let timeout;
  let old = false;
  $(document).on("keyup change", ".rockicons input.search", (e) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
      let val = e.target.value.trim().toLowerCase();
      let parts = val.split(" ").filter((el) => el !== "");
      let str = parts.join(" ");
      if (str === old) return;

      // show only matched icons
      let $root = $(e.target).closest(".rockicons");
      let $icons = $root.find(".icons span.icon");
      $icons.each((i, icon) => {
        let $icon = $(icon);
        let name = String($icon.data("search"));
        if (!str || parts.some((word) => name.includes(word))) {
          $icon.removeAttr("hidden");
        } else $icon.attr("hidden", true);
      });

      // save str for comparison
      old = str;
    }, 500);
  });
});
