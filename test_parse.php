<?php
require 'vendor/autoload.php';
$target = '£net+n';
$parsed = \Fortress\IRC\ChanServ::parseTargetAndModes($target);
print_r($parsed);
$access = \Fortress\IRC\ChanServ::checkAccess($target);
print_r($access);
