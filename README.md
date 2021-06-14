ISLE - Inventory System for Lab Equipment
==========

![Demo](https://cloud.githubusercontent.com/assets/1322063/14293284/efe856d2-fb39-11e5-9765-7605555b06f8.gif)

## Abstract
ISLE is a web-based asset management system.
Assets are defined as equipment that has a manufacturer (brand), model, and serial, and can be assigned categories and attributes.
Users can browse, search, and check-in/check-out assets. Each asset and transaction is assigned a location.

## Motivation
One of our labs at the NASA Glenn Research Center wanted a way to track their inventory of over 350 pieces of lab equipment, who is using it, and where it is located.
They also wanted to give lab users a way to parametrically search for equipment, and check-in/out the equipment themselves.

## Installation
### Requirements
* Webserver (nginx, Apache, etc.)
* MySQL or MariaDB Server
* PHP 7.1 or later, with extensions:
    * Imagick
    * PDO/pdo_mysql

### Installation Guide
1. Clone the repository and symlink (or copy) the `www` directory to the webserver's document root (or a subdirectory). Example:
    1. `cd ~`
    1. `git clone https://github.com/nasa/isle.git`
    1. `sudo ln -s ~/isle/www /var/www/html/isle`
1. Configure the webserver
    1. Configure "pretty" rewrites and max POST body size (see [conf examples](https://github.com/nasa/isle/blob/master/confs))
    1. (optional) Configure PHP options `post_max_size` and `upload_max_filesize` as the defaults may be too low
        * Make changes in system `php.ini` or put directives in `www/.user.ini`
    1. Restart the webserver and/or PHP
1. Configure the database server
    1. Create or select an existing a database for ISLE
    1. Create or select an existing user account for ISLE
1. Access the configured path from a browser and complete the setup wizard

### Addtional Settings
The setup wizard generates a settings file stored at `includes/settings.php`.
Optional settings can be configured by adding them to the returned array.
All default values are contained in [Settings.php](https://github.com/nasa/isle/blob/master/www/includes/classes/Settings.php)
You may also add other PHP directives or settings (such as `error_log` or `error_reporting`) to the beginning of this file.

### Multiple Instances
Similar to a [wiki-family](https://www.mediawiki.org/wiki/Manual:Wiki_family), ISLE supports multiple "instances", so multiple inventories can be managed separately using the same source code,
This is accomplished by using separate settings for each instance, specifically database and/or table names, and the uploads directory.
1. Install the first instance as above
1. Configure the webserver to rewrite the new instance paths to the source, except for the uploads directory
1. Modify the `www/includes/settings.php` file to parse a `$_SERVER` (probably `HTTP_HOST` and/or `REQUEST_URI`) to determine which instance is being requested, and return different settings arrays, or return different `settings.php`-like files.

## Contributing
See [CONTRIBUTING.md](https://github.com/nasa/isle/blob/master/CONTRIBUTING.md)

