# Setting Colours

You can set the primary colour that is used for the selected icon border by setting the CSS variable `--rockicons-primary`:

```css
html {
  --rockicons-primary: red;
}
```

If you are using `AdminStyleRock` the module will automatically use the colour that you set there:

<img src=colors.png class=blur>

## Coloring Icons in the Backend

Different Iconsets work differently when it comes to setting the icon color.

For Tabler and Fontawesome you can set the backend icon colour with a css variable. To do this add this to `admin.less`:

```css
.Inputfield {
  --rockicon-color: red;
}
```

<img src=icon-color.png class=blur>

## Coloring Icons on the Frontend

Coloring icons depends on which icons you use. Here is an example for how to style the color of your icons for tabler and fontawesome:

```css
/* for tabler icons */
svg {
  color: var(--rockicon-color);
}

/* for fontawesome */
svg path {
  fill: var(--rockicon-color);
}
```
