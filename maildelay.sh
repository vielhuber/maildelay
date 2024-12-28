#!/usr/bin/env bash
source $(dirname "$0")/.env
"$PHP_EXECUTABLE" $(dirname "$0")/maildelay.php
