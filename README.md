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

## Architecture

An abstract and simplified picture of the automated flow of bibliographic citation data, from multiple disparate sources, into a Drupal MySQL database:

![ETL Architecture](https://cloud.githubusercontent.com/assets/428609/11128603/69046ef0-8940-11e5-853d-90b60c9e37af.gif)

The gray boxes are PHP classes, and the yellow XML boxes are files. Some important points:

* **Modularity.** Separate extractor and transformer classes for each data source, allowing a data source to be added or removed just by adding or removing classes for that source. Just one example of the highly-modular nature of the entire system.
* **Loose coupling to Drupal.** Loading of transformed records into MySQL is the only part of the system that depends on Drupal.
* **Automatic, built-in deduplication** of all records, across all data sources.

## Data-Flow Walk-Through

Almost every automated, scheduled execution of this ETL system will start with `etl-cron.php`, in the `bin/` directory of this repository. Given this common entry-point, we can walk through the code to get a more detailed picture of the data flow through the system, and of how all the software components work together. We do that below, with some explanatory comments about some of the steps and components.

### 1. `bin/etl-cron.php`

`bin/etl-cron.php` executes each `*.php` file in the `cron/` directory of this repository, one at a time, in alpha-numeric order by name, each in its own process. This approach brings several benefits:

* **Memory management.** Long-running PHP CLI processes can leak memory. Breaking up ETL tasks into separate processes helps to limit the memory consumed by any one process.
* **Independent task execution.** Allows for running one or more sub-tasks without having to run the entire ETL system for all data sources.

#### Debugging Notes

* The last point is especially important for debugging. If a cron run fails, debugging often requires identifying which sub-task failed, and executing it directly with all its specific command-line arguments, in order to see the error message.

#### Special Note

Notice that the last file in `cron/` in alpha-numeric order is `800-drush-cron.php`. This adds all of the newly-loaded records to both the MySQL and Solr indexes, and should always be the final task in any ETL run.

#### To Do

* Improve logging, and maybe other aspects, of sub-task execution, for easier debugging. For example, instead of just printing errors to `STDOUT`, which will be invisible for forked processes, print them to a log file instead.
* Use more sophisticated methods of managing memory, e.g. automatically re-starting processes, worker processes and message queues, etc.

### 2. `cron/*.php`

As described in the previous section, `bin/etl-cron.php` executes all the files in this directory, in alpha-numeric order by name. The original idea was to allow for adding and removing data sources to daily cron runs just by adding files to, or removing files from, this directory, and modifying the order of sub-task execution just by naming and renaming files. However, we have since found some problems with this approach:

* Based on similarities in what each sub-task does, we have re-factored all files except `800-drush-cron.php` to be identical, except for their file names, which are based on the data source. Most of these sub-tasks just call `bin/etl-source-full-cycle.php`, passing it the name of the data source.
* Because the WorldCat API allows for temporally limiting searches only by year, UMN has removed the WorldCat subtask from this daily cron directory, running it manually every couple of months.

#### To Do

* Further refactor `bin/etl-cron.php` to take a configuration list of data sources, so that we can eliminate all the repeated code in `cron/*.php`.
* Automate the special-case handling for WorldCat, so that we can eliminate the tedious, manual tasks for associated with it.

### 3. `bin/etl-source-full-cycle.php`

Here we start to do some real work. This app constructs command lines for three sequential tasks, which it then executes, each in its own separate process:

1. `bin/download-*.php`: Downloads XML files for a given `source` into `downloads/$source/originals/`. (This is the 'E' for 'Extraction' in ETL.)
2. `bin/dedupe-citations.php`: Deduplicates the downloaded records against both each other and records already in the Drupal database. Puts deduplicated records in `downloads/$source/deduped/`.
3. `drush cite-load`: Extracts the deduplicated records into PHP arrays (more of the 'E' in ETL), transforms those arrays into a common structure (the 'T' in ETL), and loads the transformed arrays into Drupal (the 'L' in ETL).

To construct these command lines, `etl-source-full-cycle.php` requires a `source` parameter on its own command line, and adherence to a set of conventions, e.g. that `bin/`, `config/`, and `downloads` all share a common parent directory, that config files conform to a `$source.json` naming convention, etc. See the source code, which should be straight-forward, for full details of these conventions. We go into each of the above three sub-tasks in more detail in the following sections.

### 4. `bin/download-*.php`

Most of our data sources are RSS feeds of major news organizations, and we use `download-feed.php` for all of them. Their `config/download/$source.json` files are all very simple, containing only a list of URLs for RSS XML files to download. Typically this will be just one file per data source.

The two exceptions are PubMed and WorldCat, both of which provide sophisticated search engines, for which our search strategies (i.e. expressions) return many more records than can be downloaded in a single file. Therefore the download apps for these sources are much more complex, issuing multiple requests and downloading many files for a single search-result record-set. The config files for these sources are also more complex, containing our search strategies and institution-identifying information required by these search engines.

For all data sources, the download apps name the downloaded files according to the date and time, and put them in `downloads/$source/originals/`.

#### Debugging Notes

* Sometimes a download request will return an empty file, which will cause XML parsing errors later in the ETL process.

#### To Do

* Implement ways of discarding zero-byte downloaded files, and/or of ignoring them during later parsing.
* Improve logging/error-reporting.

### 5. `bin/dedupe-citations.php`

We use this app to deduplicate records from all data sources. Each `config/dedupe/$source.json` config file contains a record-identifier type and a name for each source, as well as an `xmlRecordFileClass` and an `xmlRecordClass`, which the deduplicator uses to parse the XML files and records, respectively. The deduplicator reads input files from `downloads/$source/originals/` and writes output files to `downloads/$source/deduped/`. After deduplication, it compresses each `*.xml` file in `originals/`, renaming it to `*.xml.gz`.

#### Debugging Notes

* Sometimes a download request will return an empty file, or a file containing malformed XML, which will cause XML parsing errors. When that happens, the deduplication process will crash, usually leaving some files in `downloads/$source/originals/` uncompressed. UMN Libraries runs a daily task via cron to search for `*.xml` files in `downloads/`, in order to detect these crashes.

#### To Do

* Implement ways of discarding/skipping zero-byte or malformed downloaded files.
* Improve logging/error-reporting.

### 6. `drush cite-load`

This is where the bulk of the extracting, transforming, and loading (ETL) happens. Although almost all of this code is in the `cite` Drupal module, only the loading (L) part of the process depends on Drupal. We put the rest of the code in `cite` to make it easy to pass citation records, in the form of PHP arrays, from one phase of the process to the next. Also, although the `cite` source code is in the [ethicshare_sites_all](https://github.com/chadfennell/ethicshare_sites_all) repo, we comment on it here in order to keep all of the high-level ETL documentation in one place. 

One of the most important parameters for `drush cite-load` is the `config/etl/$source.json` file, which includes properties that specificy the classes to use for each phase of the ETL process for the given data source:

* `extractorFactoryClass`
* `transformerClass`
* `loaderClass`

Notice that the values for these properties all start with `CiteETL_`, followed by `E_`, `T_`, or `L_`, respectively, and then a class basename. These correspond to classes defined in `cite/contrib/CiteETL/`, and subdirectories `E/`, `T/`, or `L/`. For example, the `extractorFactoryClass` for `bbchealth` is `CiteETL_E_BbcFactory`, which corresponds to `cite/contrib/CiteETL/E/BbcFactory.php`. For all data sources, the `loaderClass` should be `CiteETL_T_Loader`. Again, this modular design allows for a lot of flexibility. Support for a new data source typically requires only implementing extractor and transformer classes for that source. Also notice that there are other `drush cite-*` commands that use this same pipeline-of-process-phases idea to implement non-ETL and one-off processes, like batch correction of bad data in multiple records. For examples, see some of the many classes in `cite/contrib/CiteETL` that do not appear in any of the config files for scheduled ETL runs.

`drush cite-load` reads input files from `downloads/$source/deduped/`, compressing and renaming each file from `*.xml` to `*.xml.gz` after loading all of its records. It writes any loading error messages to `log/$source-error.log`.

#### Debugging Notes

* Sometimes a download request will return an empty file, or a file containing malformed XML, which will cause XML parsing errors. When that happens, `drush cite-load` will crash, usually leaving some files in `downloads/$source/deduped/` uncompressed. UMN Libraries runs a daily task via cron to search for `*.xml` files in `downloads/`, in order to detect these crashes.

#### To Do

* Implement ways of discarding/skipping zero-byte or malformed downloaded files.
* Improve logging/error-reporting.

## Attribution

The University of Minnesota Libraries created this software for the [EthicShare](http://www.ethicshare.org/about) project.
