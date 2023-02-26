#!/usr/bin/env bash

set -e

PHP=php
INSTANCE=$1
echo "Working on" "$INSTANCE"

echo "update help text and vocabularies"
$PHP extension/occsvimport/bin/php/migration/update_helper.php -s${INSTANCE}_backend

echo "export to tables"
$PHP extension/occsvimport/bin/php/migration/export_to_tables.php --truncate -s${INSTANCE}_backend

echo "push to spreadsheet"
$PHP extension/occsvimport/bin/php/migration/push_tables_to_spreadsheet.php -v -s${INSTANCE}_backend

echo "configure spreadsheet"
$PHP extension/occsvimport/bin/php/migration/configure_spreadsheet.php -v -s${INSTANCE}_backend