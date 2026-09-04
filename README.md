# gf3

=== Genius Family Tree ===

Contributors: Jan Fichter
Developer URL: https://xn----8sbbdpda1c7cwf.xn--p1ai/product-category/genius-family-tree/
Tags: family, tree, genealogy, gedcom
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.4.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Plugin for creating and displaying interactive family trees on a WordPress site with GEDCOM support, root elements, and advanced navigation.

== Description ==

Genius Family Tree is a powerful tool for creating and visualizing family trees. Version 1.4.1 includes many improvements and new features.

🚀 **Key features:**

* **Interactive tree** — create beautiful, scalable family trees using the D3.js library.
* **Multiple family groups** — create multiple independent trees by assigning each family member to their own group (shortcode `[family_tree group="ID"]`).
* **Tree root element** — you can specify which family member the main tree should be built from (star icon in the members list).
* **Navigate from a specific person** — each person's page has a "Show in tree" button that builds the tree starting from that person (URL parameters `person_id` or `center_on`).
* **GEDCOM import/export** — full support for GEDCOM 5.5.1 format with photos and root element preservation.
* **Surname catalog** — convenient surname navigation with an alphabetical index (shortcode `[family_surname_catalog]`).
* **Enhanced admin** — columns with portraits, lifespan dates, and root element indicators.

🎯 **New features in version 1.4.1:**

* **Multiple family groups support** — you can now create multiple independent trees by assigning each family member to their own group
* **New admin dashboard** — fully redesigned dashboard with section tiles, status block, recent actions, quick search, and setup wizard
* **Improved settings page** — modern card-based interface with clear heading styling and Pro feature display
* **Smart root element sidebar** — root checkbox appears only after selecting a group, with dynamic display of the current root in the selected group
* **Improved notifications** — list of ungrouped members with direct edit links
* **Plugin version** — version display at the top of the dashboard and settings page
* **Counting fix** — correct counting of groups and members, excluding revisions and system posts
* **Ungrouped fix** — records linked to deleted groups are now properly detected
* **Improved styles** — unified modern card design, rounded corners, shadows, and responsive layout
* **Improved GEDCOM import/export** — family group selection for operations

== Changelog ==

= 1.4.1 =
* **NEW**: Multiple family groups support — each tree can now be in its own group
* **NEW**: Fully redesigned dashboard in the "Family Tree" section with section tiles, status block, and setup wizard
* **NEW**: Quick person search on the dashboard
* **NEW**: Dynamic root element sidebar showing the current root of the selected group
* **NEW**: Display of ungrouped members list in notifications
* **IMPROVED**: Correct counting of groups and members (revisions and system posts excluded)
* **IMPROVED**: Accounting for deleted groups when detecting ungrouped members
* **IMPROVED**: Modern settings page interface with card layout
* **IMPROVED**: Unified style for all admin and frontend elements
* **FIXED**: Settings page headings are now displayed correctly
* **FIXED**: Plugin version is now displayed in the admin panel

= 1.3.4 =
* **NEW**: Ability to set a global tree root element (star icon in the members list)
* **NEW**: Meta box for selecting the root element when editing a person
* **NEW**: Support for `person_id` and `center_on` URL parameters for building a tree from a specific person
* **NEW**: "Tree Root" column in the admin panel with visual indicator
* **IMPROVED**: GEDCOM import automatically restores the root element from the `_ROOT Y` tag
* **IMPROVED**: GEDCOM export preserves root element information
* **IMPROVED**: Tree building priorities: URL > global root > data attribute
* **FIXED**: Conflict between data-root-id and global root when building the tree
* **FIXED**: Errors when passing root_member_id to JavaScript

= 1.3.3 =
* **NEW**: Meta box for selecting the tree root element
* **NEW**: "Tree Root" column in the members list
* **IMPROVED**: GEDCOM export now includes root element information
* **IMPROVED**: GEDCOM import automatically restores the root element

= 1.3.2 =
* **IMPROVED**: Improved photo handling during GEDCOM import
* **FIXED**: Minor bugs in the GEDCOM parser

= 1.3.1 =
* **IMPROVED**: Support for more image formats during GEDCOM import
* **ADDED**: Error logging during image import

= 1.3.0 =
* **NEW**: "Portrait" and "Lifespan" columns in the admin panel
* **IMPROVED**: Visual display of members in the list

= 1.2.3 =
* **NEW**: Ability to show a selected person in the family tree via a button on the person's individual page
* **IMPROVED**: Support for the `center_on` URL parameter

= 1.2.2 =
* Improved: Shortcode now supports [family_tree root="10" center_on="15"]
* Improved: Bidirectional sync between parents and children
* Improved: Interactive tree performance

= 1.2.1 =
* Fixed: Heartbeat API disabled on the plugin settings page
* Improved: Increased stability

= 1.2.0 =
* Added: GEDCOM export
* Added: GEDCOM import
* Added: Surname catalog with alphabetical navigation

= 1.1.0 =
* Added: Licensing support
* Added: Limit of 50 members in the free version

= 1.0.0 =
* Initial release

== Description ==

Genius Family Tree is a powerful tool for creating and visualizing family trees. Version 1.4.1 includes many improvements and new features.

🚀 **Key features:**

* **Interactive tree** — create beautiful, scalable family trees using the D3.js library.
* **Multiple family groups** — create multiple independent trees by assigning each family member to their own group (shortcode `[family_tree group="ID"]`).
* **Tree root element** — you can specify which family member the main tree should be built from (star icon in the members list).
* **Navigate from a specific person** — each person's page has a "Show in tree" button that builds the tree starting from that person (URL parameters `person_id` or `center_on`).
* **GEDCOM import/export** — full support for GEDCOM 5.5.1 format with photos and root element preservation.
* **Surname catalog** — convenient surname navigation with an alphabetical index (shortcode `[family_surname_catalog]`).
* **Enhanced admin** — columns with portraits, lifespan dates, and root element indicators.

🎯 **New features in version 1.4.1:**

* **Multiple family groups support** — you can now create multiple independent trees by assigning each family member to their own group
* **New admin dashboard** — fully redesigned dashboard with section tiles, status block, recent actions, quick search, and setup wizard
* **Improved settings page** — modern card-based interface with clear heading styling and Pro feature display
* **Smart root element sidebar** — root checkbox appears only after selecting a group, with dynamic display of the current root in the selected group
* **Improved notifications** — list of ungrouped members with direct edit links
* **Plugin version** — version display at the top of the dashboard and settings page
* **Counting fix** — correct counting of groups and members, excluding revisions and system posts
* **Ungrouped fix** — records linked to deleted groups are now properly detected
* **Improved styles** — unified modern card design, rounded corners, shadows, and responsive layout
* **Improved GEDCOM import/export** — family group selection for operations

== Changelog ==

= 1.4.1 =
* **NEW**: Multiple family groups support — each tree can now be in its own group
* **NEW**: Fully redesigned dashboard in the "Family Tree" section with section tiles, status block, and setup wizard
* **NEW**: Quick person search on the dashboard
* **NEW**: Dynamic root element sidebar showing the current root of the selected group
* **NEW**: Display of ungrouped members list in notifications
* **IMPROVED**: Correct counting of groups and members (revisions and system posts excluded)
* **IMPROVED**: Accounting for deleted groups when detecting ungrouped members
* **IMPROVED**: Modern settings page interface with card layout
* **IMPROVED**: Unified style for all admin and frontend elements
* **FIXED**: Settings page headings are now displayed correctly
* **FIXED**: Plugin version is now displayed in the admin panel

= 1.3.4 =
* **NEW**: Ability to set a global tree root element (star icon in the members list)
* **NEW**: Meta box for selecting the root element when editing a person
* **NEW**: Support for `person_id` and `center_on` URL parameters for building a tree from a specific person
* **NEW**: "Tree Root" column in the admin panel with visual indicator
* **IMPROVED**: GEDCOM import automatically restores the root element from the `_ROOT Y` tag
* **IMPROVED**: GEDCOM export preserves root element information
* **IMPROVED**: Tree building priorities: URL > global root > data attribute
* **FIXED**: Conflict between data-root-id and global root when building the tree
* **FIXED**: Errors when passing root_member_id to JavaScript

= 1.3.3 =
* **NEW**: Meta box for selecting the tree root element
* **NEW**: "Tree Root" column in the members list
* **IMPROVED**: GEDCOM export now includes root element information
* **IMPROVED**: GEDCOM import automatically restores the root element

= 1.3.2 =
* **IMPROVED**: Improved photo handling during GEDCOM import
* **FIXED**: Minor bugs in the GEDCOM parser

= 1.3.1 =
* **IMPROVED**: Support for more image formats during GEDCOM import
* **ADDED**: Error logging during image import

= 1.3.0 =
* **NEW**: "Portrait" and "Lifespan" columns in the admin panel
* **IMPROVED**: Visual display of members in the list

= 1.2.3 =
* **NEW**: Ability to show a selected person in the family tree via a button on the person's individual page
* **IMPROVED**: Support for the `center_on` URL parameter

= 1.2.2 =
* Improved: Shortcode now supports [family_tree root="10" center_on="15"]
* Improved: Bidirectional sync between parents and children
* Improved: Interactive tree performance

= 1.2.1 =
* Fixed: Heartbeat API disabled on the plugin settings page
* Improved: Increased stability

= 1.2.0 =
* Added: GEDCOM export
* Added: GEDCOM import
* Added: Surname catalog with alphabetical navigation

= 1.1.0 =
* Added: Licensing support
* Added: Limit of 50 members in the free version

= 1.0.0 =
* Initial release

== Requirements ==

* WordPress 5.0 or higher
* PHP 7.0 or higher
* MySQL 5.6 or higher
* For Pro version: active license

== Installation ==

= Installation via WordPress admin =

1. Download the plugin (.zip archive).
2. Log into your WordPress admin (your-site.com/wp-admin).
3. Go to "Plugins" → "Add New".
4. Click the "Upload Plugin" button (at the top of the page).
5. Select the downloaded .zip archive and click "Install".
6. After installation, click "Activate Plugin".

= Manual installation via FTP =

1. Extract the plugin archive.
2. Connect to your server via FTP.
3. Go to the `/wp-content/plugins/` folder.
4. Upload the `genius-family-tree` folder to the server.
5. Go to WordPress admin → "Plugins".
6. Find "Genius Family Tree" in the list and click "Activate".

== Configuration ==

= Basic settings =

1. After activating the plugin, a new "Family Tree" item will appear in the menu.
2. Click "Family Tree" → "Settings" to access the plugin settings.
3. Here you can enter your license key and manage GEDCOM features.

= Setting the root element =

**Method 1 (automatic during import):**
1. When exporting GEDCOM, the root element is automatically marked with the `_ROOT Y` tag.
2. When importing this file, the root element is automatically restored.

**Method 2 (manual):**
1. Go to "Family Tree" → "All Family Members"
2. Find the desired person and click "Edit"
3. In the right column, find the "Family Group" meta box
4. Select a group, then check the "Make this the root element" checkbox
5. Save the post

**Method 3 (quick edit):**
1. In the members list, hover over the desired entry
2. Click "Quick Edit"
3. Check the "Make this the tree root element" checkbox
4. Click "Update"

= Shortcodes =

**Main tree:**
[family_tree]

Displays an interactive tree from the global root element.

**Tree from a specific person:**
[family_tree root="123"]

Builds a tree from the person with ID 123.

**With focus on a person:**
[family_tree root="123" center_on="456"]

Builds a tree from person 123, but centers on person 456.

**Surname catalog:**
[family_surname_catalog]

Displays an alphabetical catalog of all surnames with links to members.

= License activation (Pro version) =

1. Purchase a license key on the [official website](https://xn----8sbbdpda1c7cwf.xn--p1ai/product-category/genius-family-tree/).
2. Go to "Family Tree" → "Settings".
3. Enter the key in the "License Key" field.
4. Click "Save Settings".
5. After activation, GEDCOM features become available and the 50-member limit is removed.

== Frequently Asked Questions ==

= How does the root element system work? =

The plugin uses a smart priority system:
1. **Highest priority**: URL parameters (`?person_id=123` or `?center_on=123`) — when navigating from a person's page
2. **Medium priority**: Global root from settings — default value for the main tree
3. **Lowest priority**: Data attribute `data-root-id` — for backward compatibility

= How to navigate to the tree from a specific person? =

**Method 1:** On the person's page, click the "Show in tree" button.
**Method 2:** Add the parameter `?person_id=123` to the tree page URL (where 123 is the person's ID).
**Method 3:** Use the `?center_on=123` parameter (for old links).

= How to find a person's ID? =

The person's ID is displayed in the admin panel in the list of all family members (column "ID") or in the URL when editing the person (e.g., `post=123`).

= What data is saved in GEDCOM? =

When exporting, the following are saved:
- All personal data (name, surname, dates, places)
- Family relationships (parents, children, spouses)
- **Root element** (`_ROOT Y` tag)
- Photos (URL and local path)
- Notes

= Are there any recommendations for filling out data? =

* Start with the oldest generation — this will help build relationships correctly.
* Fill in required fields — name, surname, gender.
* Use search when adding relationships — in the father/mother/spouse fields, start typing a name and the system will suggest options.
* Set the root element — this will determine who the main tree is built from.

= What are the limitations of the free version? =

* Maximum of 50 members
* No GEDCOM export
* No GEDCOM import
* No automatic root element restoration on import

= How to remove limitations? =

1. Purchase a license key on the [official website](https://xn----8sbbdpda1c7cwf.xn--p1ai/product-category/genius-family-tree/).
2. Enter the key in the plugin settings.
3. Activate the Pro version.

= What to do if the tree is not displayed? =

1. Check if family members have been added.
2. Make sure the `[family_tree]` shortcode has been added to a page.
3. Check if a root element is set (there should be a star icon in the members list).
4. Open the browser console (F12 → Console) and check for errors.
5. Make sure scripts are not blocked (e.g., by AdBlock).

= What to do if the root element is not saved during import? =

Make sure that:
1. The exported GEDCOM file has the `_ROOT Y` tag for the desired person.
2. You are using the Pro version of the plugin (this feature is only available in the paid version).
3. There are no errors in the logs during import.

= What to do if the `?center_on=123` link does not work? =

Check:
1. That a person with that ID exists.
2. That this person has relationships (parents/children/spouses).
3. Open the browser console — there should be a message `🎯 Using ID from URL (center_on): 123`

= What to do if errors occur when adding persons? =

1. Check if the 50-person limit has been reached (in the free version).
2. Make sure required fields are filled out (name, surname, gender).
3. Check the WordPress error logs (file `/wp-content/debug.log`).

= What to do if there are problems with the plugin? =

Check system requirements:
- WordPress 5.0+
- PHP 7.0+
- MySQL 5.6+

Collect information for support:
- WordPress version
- PHP version
- Plugin version
- Problem description
- Error screenshots (if any)
- Browser console (F12 → Console)

**IMPORTANT!** Priority technical support is provided to holders of PRO versions of the plugin.

= How to update the plugin? =

1. Create a backup of your site (database + files).
2. Deactivate the current version of the plugin.
3. Delete the plugin (data will be preserved in the database).
4. Install the new version.
5. Activate the plugin.
6. Check settings and data.

⚠️ **Important:** Always create a backup of your site before updating plugins!
