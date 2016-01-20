# Changelog

All Notable changes to `julianpitt\dbmanager` will be documented in this file

## 1.0.3 | 2016-01-

#### Added
- Fascade

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