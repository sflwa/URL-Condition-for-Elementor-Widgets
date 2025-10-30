=== URL Condition for Elementor Widgets ===
Contributors: sflwa
Tags: elementor, conditions
Requires at least: 6.7
Tested up to: 6.8
Stable tag: 1.0.6
Requires PHP: 8.0
License: GPLv2 or later
Allows Elementor widgets, sections, and containers to be shown or hidden based on a URL query variable (`?key=value`).

== Description ==
The **URL Condition for Elementor Widgets** plugin provides simple, reliable front-end logic to control the display of any Elementor element (Widget, Section, Column, or Container) based on the presence or value of a URL query parameter.

The plugin is configured via the **Advanced** tab of any Elementor element, allowing you to set display logic to:

1.  **Show** or **Hide** the element only when a specific URL query variable is present (e.g., `?promo`).
2.  **Show** or **Hide** the element only when the variable matches a specific value (e.g., `?source=facebook`).

The plugin includes built-in cache mitigation (setting the `DONOTCACHEPAGE` constant and sending no-cache headers) to ensure conditional content displays correctly, even when aggressive server or caching plugins are active.

== Installation ==
1.  Upload the plugin folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.

== Usage ==
1.  In the Elementor Editor, select any **Widget, Column, Section, or Container**.
2.  Go to the **Advanced** tab.
3.  Expand the **URL Query Condition** panel.
4.  Enable the condition, specify the **URL Variable Name** (e.g., 'source') and optionally the **Expected Value** (e.g., 'affiliate').
5.  Set the **Action** (Show or Hide) for when the condition is met.

== Changelog ==
= 1.0.6 =
* Final code cleanup and removal of debug logging and the debug toggle.
* Plugin name adjusted to "URL Condition for Elementor Widgets".
* Updated plugin header information.

= 1.0.5 =
* Implemented cache mitigation by setting `DONOTCACHEPAGE` when a query parameter is detected, resolving display issues with caching layers (e.g., Elementor cache, LiteSpeed, WP Rocket).

= 1.0.4 =
* Added robust debug logging to `debug.log` to assist with troubleshooting.
* Ensured element always displays in Elementor editor/preview mode for configuration ease.
