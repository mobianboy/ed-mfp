#!/bin/bash

#This is disabled by SDK on 09-27-2013 after successful conversion
exit 0;

git pull
php app/console cache:clear --env=prod --no-debug
php conversionstep1.php
php app/console --env=prod doctrine:schema:update --force
php conversionstep3.php
