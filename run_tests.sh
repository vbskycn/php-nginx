#!/usr/bin/env sh
apk --no-cache add curl
sleep 15
curl --silent --fail http://app:8080/php.php | grep -i 'php'
