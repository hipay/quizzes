<?php

use GAubry\ErrorHandler\ErrorHandler;

$aConfig = require_once(__DIR__ . '/../../conf/qcm.php');

new ErrorHandler($aConfig['GAubry\ErrorHandler']);
date_default_timezone_set('UTC');
