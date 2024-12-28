# 📧 maildelay 📧

## motivation

this package sends delayed mails. to do this, you create special folders in your inbox (which specify the sending time) and save all mails, you want to delay, as drafts inside those folders.

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

### create folder structure

```
  ...
  - DELAY
    - THIS NIGHT
    - NEXT MORNING
    - NEXT WEEK
  ...
```

### setup cronjob

```sh
*/10 * * * * /usr/bin/php83 /var/www/maildelay/script.php 2>&1
```
