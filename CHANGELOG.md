# Changelog

All Notable changes to `julianpitt\dbmanager` will be documented in this file

## 1.0.3.2 | 2016-02-25

#### Changed
- Added 'public' tag to config for publishing

#### Fixed
- Console warn does not exist error on < laravel 5.1

## 1.0.3.1 | 2016-02-24

#### Changed
- Changed Facade to return IOC reference

## 1.0.3 | 2016-02-24

#### Added
- Fascade
- Failsafe backup for non full backups
- Output interface
- Individual files per table backups
- Artisan command options

#### Changed
- Surrounded fire method for both classes with try catch to make sure temporary file cleanup also occurs on an exception
- Renamed Helper classes to Drivers and moved methods in command class fire to drivers

### 1.0.2 2016-01-18

#### Fixed
- Crash when adding signature on backup file

#### Changed
- Added checking if mysqldump path exists

### 1.0.1 2016-01-18

#### Changed
- Class names and structures to follow psr-4

#### Fixed
- Missing classes


### 1.0.0 2016-01-18

#### Added
- The whole project