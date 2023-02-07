=== UCF Online Entries Exporter ===
Contributors: ucfwebcom
Requires at least: 6.0
Tested up to: 6.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv3 or later
License URI: http://www.gnu.org/copyleft/gpl-3.0.html

Provides a WP CLI command for exporting gravity form entries to an external MySQL database.


== Description ==

In order to be able to better work with the data received through Gravity Form entries, this plugin allows for the export of form entries to an external database table for use in tools like Power BI.


== Documentation ==

Head over to the [UCF Online Entries Exporter wiki](https://github.com/UCF/UCF-Online-Entries-Exporter/wiki) for detailed information about this plugin, installation instructions, and more.


== Changelog ==

= 1.0.0 =
* Initial release


== Upgrade Notice ==

n/a


== Development ==

[Enabling debug mode](https://codex.wordpress.org/Debugging_in_WordPress) in your `wp-config.php` file is recommended during development to help catch warnings and bugs.

= Requirements =
* node v16+
* gulp-cli

= Instructions =
1. Clone the UCF-Online-Entries-Exporter repo into your local development environment, within your WordPress installation's `plugins/` directory: `git clone https://github.com/UCF/UCF-Online-Entries-Exporter.git`
2. `cd` into the new UCF-Online-Entries-Exporter directory, and run `npm install` to install required packages for development into `node_modules/` within the repo
3. Optional: If you'd like to enable [BrowserSync](https://browsersync.io) for local development, or make other changes to this project's default gulp configuration, copy `gulp-config.template.json`, make any desired changes, and save as `gulp-config.json`.

    To enable BrowserSync, set `sync` to `true` and assign `syncTarget` the base URL of a site on your local WordPress instance that will use this plugin, such as `http://localhost/wordpress/my-site/`.  Your `syncTarget` value will vary depending on your local host setup.

    The full list of modifiable config values can be viewed in `gulpfile.js` (see `config` variable).
3. Run `gulp default` to process front-end assets.
4. If you haven't already done so, create a new WordPress site on your development environment to test this plugin against.
5. Activate this plugin on your development WordPress site.
6. Configure plugin settings from the WordPress admin under "Settings > UCF Online Entries Exporter".

= Other Notes =
* This plugin's README.md file is automatically generated. Please only make modifications to the README.txt file, and make sure the `gulp readme` command has been run before committing README changes.  See the [contributing guidelines](https://github.com/UCF/UCF-Online-Entries-Exporter/blob/master/CONTRIBUTING.md) for more information.


== Contributing ==

Want to submit a bug report or feature request?  Check out our [contributing guidelines](https://github.com/UCF/UCF-Online-Entries-Exporter/blob/master/CONTRIBUTING.md) for more information.  We'd love to hear from you!
