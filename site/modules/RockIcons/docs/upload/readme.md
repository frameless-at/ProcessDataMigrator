# Uploading Icons

You can upload any iconset you want to `/site/templates/RockIcons` and do a modules refresh to make sure that RockIcons recognises the change.

To finalize the iconset integration, navigate to the module's settings page. Here, you will find a list of available icon folders. Locate the folder name corresponding to the iconset you've just uploaded (e.g., `fa` for FontAwesome or `tabler` for Tabler Icons) and ensure the checkbox next to it is checked. This action will enable the iconset for use within the RockIcons module.

## Using FontAwesome Icons

All you need to do is go to https://fontawesome.com/download and download your version of Font Awesome For The Web.

After downloading extract the zip-file and copy the `svgs` folder to /site/templates/RockIcons/svgs.

Then rename the `svgs` folder to something like `fa`. This folder-name will be used for selecting icons, eg `fa-brands:facebook`.

## Using Tabler Icons

Another great icon library is Tabler Icons. Download the zip from https://github.com/tabler/tabler-icons/releases (eg tabler-icons-2.44.0.zip) and extract the zip file.

Then copy all svgs to the RockIcons folder and rename the svg folder to `tabler` so that icon names will be something like `tabler:zzz`.
