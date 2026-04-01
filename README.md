[![GitHub Tag](https://img.shields.io/github/v/tag/vielhuber/maildelay)](https://github.com/vielhuber/maildelay/tags)
[![Code Style](https://img.shields.io/badge/code_style-psr--12-ff69b4.svg)](https://www.php-fig.org/psr/psr-12/)
[![License](https://img.shields.io/github/license/vielhuber/maildelay)](https://github.com/vielhuber/maildelay/blob/main/LICENSE.md)
[![Last Commit](https://img.shields.io/github/last-commit/vielhuber/maildelay)](https://github.com/vielhuber/maildelay/commits)

# 📧 maildelay 📧

## motivation

this package sends delayed mails, because e.g. outlooks delay email feature is not feasible, since outlook must be running. to do this, you create special folders in your inbox (which specify the sending time) and save all mails you want to delay as drafts inside those folders.

## setup

### install packages

```sh
composer install
```

### fill out environment variables

```sh
cp .env.example .env
vi .env
```

### create mail folder structure

```
  ...
  - DELAY
    - THIS EVENING
    - THIS NIGHT
    - NEXT MORNING
    - NEXT WEEK
  ...
```

### setup cronjob

```sh
*/10 * * * * /path/to/maildelay/maildelay.sh 2>&1
```
