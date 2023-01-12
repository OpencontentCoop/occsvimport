#!/usr/bin/env bash

SITEACCESS=$1
ACTION=$2
ONLY=$3
UPDATE=$4

#echo $SITEACCESS;
#echo $ACTION;
#echo $ONLY;
#echo $UPDATE;
echo "php extension/occsvimport/bin/php/migration/run.php --allow-root-user -q -s${SITEACCESS} --action=${ACTION} ${ONLY} ${UPDATE}"
php extension/occsvimport/bin/php/migration/run.php --allow-root-user -q -s${SITEACCESS} --action=${ACTION} ${ONLY} ${UPDATE} > /dev/null &
