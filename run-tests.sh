#!/bin/bash

echo Parameters example: ./tests/TestClass.php testMethod

echo Path $1
echo 'Method pattern (regular):' $2

cmd="./vendor/bin/phpunit --bootstrap vendor/autoload.php --verbose --filter $2 $1"
echo $cmd

exec $cmd
