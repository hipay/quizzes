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

use Himedia\QCM\Quiz;
use Himedia\QCM\Tools;
use Symfony\Component\Finder\Finder;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/inc/bootstrap.php';

$sSalt = $aConfig['Himedia\QCM']['crypt_salt'];
$sQuizzesDir = $aConfig['Himedia\QCM']['dir']['quizzes'] . '/src';

$aQuizzes = array();
$oFinder = new Finder();
$oFinder->files()->in($sQuizzesDir)->name('*.php')->depth(0);
foreach ($oFinder as $oFile) {
    $sSrcPath = $oFile->getRealpath();
    $sDestPath = substr(str_replace('/src/', '/', $sSrcPath), 0, -3) . 'enc.php';

    $oQuiz = Quiz::getInstanceFromPath($sSrcPath);
    if ($oQuiz->checkWellFormed() == '') {
        $aStats = $oQuiz->getStats();
        $aQuestions = $oQuiz->getQuestions();
        foreach ($aQuestions as &$aQuestion) {
            $aQuestion[1] = Tools::simpleEncrypt($aQuestion[1], $sSalt);
            foreach ($aQuestion[2] as $sSubject => $bIsRight) {
                $sEncSubject = Tools::simpleEncrypt($sSubject, $sSalt);
                $aQuestion[2][$sEncSubject] = $bIsRight;
                unset($aQuestion[2][$sSubject]);
            }
        }

        $aEncQuiz = array(
            'meta' => array(
                'title' => $aStats['title'],
                'time_limit' => $aStats['time_limit'],
                'max_nb_questions' => $aStats['nb_questions'],
                'status' => $aStats['status']
            ),
            'questions' => $aQuestions
        );
        file_put_contents($sDestPath, "<?php\n\nreturn " . var_export($aEncQuiz, true) . ";\n");
    }
}
