# Rendering Icons

If you want to output the svg markup in your frontend all you have to do is echo the field's value:

```php
echo $page->my_icon_field;
```

The formatted value of your field will be a `RockIcon` object that holds the name and the svg markup of your icon:

<img src=output.png class=blur>

When requesting the field value as a string (as when using `echo`) it will automatically return the svg markup.
