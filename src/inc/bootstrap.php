<?php

/**
 * Copyright (c) 2013 Hi-Media
 * Copyright (c) 2013 Geoffroy Aubry <gaubry@hi-media.com>
 *
 * This file is part of Hi-Media Quizzes.
 *
 * Hi-Media Quizzes is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Hi-Media Quizzes is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Hi-Media Quizzes. If not, see <http://www.gnu.org/licenses/>.
 *
 * @copyright 2013 Hi-Media
 * @copyright 2013 Geoffroy Aubry <gaubry@hi-media.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 */

use GAubry\ErrorHandler\ErrorHandler;

$aConfig = require_once(__DIR__ . '/../../conf/qcm.php');

new ErrorHandler($aConfig['GAubry\ErrorHandler']);
date_default_timezone_set('UTC');
