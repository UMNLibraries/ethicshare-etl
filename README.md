# ethicshare-etl

Extract-Transform-Load system for EthicShare.

## Prerequisites

* PHP >= 5.3
* PHP DOM Extension
    * To install on RHEL/CentOS: `yum install php-xml`
* PHP Multibyte String Extension
    * To install on RHEL/CentOS: `yum install php-mbstring`
* [Composer](https://getcomposer.org/)
* Git
* A [GitHub](https://github.com/) Account
    * Running `composer install` (see below) will likely require GitHub credentials.
* An EthicShare Drupal website, including the [ethicshare_sites_all](https://github.com/chadfennell/ethicshare_sites_all) repository.
* [drush](https://github.com/drush-ops/drush)
* An OCLC [WorldCat WSKey](https://platform.worldcat.org/wskey/)

## Install

### Clone this Repository

`git clone git@github.com:UMNLibraries/ethicshare-etl.git`

### Install PHP Packages

```
cd ethicshare-etl/composer/
composer install
```

## Configure

### Configuration Files

All of the configuration files that require local modification are in `ethicshare-etl/config` and have a `.dist` extension:

```
mysql.json.dist
drupal.json.dist
local-paths.json.dist
download/pubmed.json.dist
download/worldcat.json.dist
```

Copy these files to new files with identical names, but without the `.dist` extension, in the same directories. Replace the dummy values with your values. Note that you will need your WWSKey for the WorldCat configuration. This repository is configured to ignore these local, non-dist files, so no passwords or other sensitive information will be revealed to remote hosts like github.com.

### cron

Set up a cron job to run `ethicshare-etl/bin/etl-cron.php` at least once a day. UMN Libraries had this set up to run twice a day, at around 1:00 AM and 6:00 AM.

## Attribution

The University of Minnesota Libraries created this software for the [EthicShare](http://www.ethicshare.org/about) project.
