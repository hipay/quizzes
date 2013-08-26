<?php

/*
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
 */

namespace Himedia\QCM;

use Symfony\Component\Finder\Finder;

/**
 * Un quiz, avec ses questions.
 *
 * Copyright (c) 2013 Hi-Media
 * Licensed under the GNU General Public License v3 (LGPL version 3).
 *
 * @copyright 2013 Hi-Media
 * @license http://www.gnu.org/licenses/gpl.html
 */
class Quiz implements \Serializable
{
    private $aData;

    public static function getAllQuizzes ($sDirectory)
    {
        $aQuizzes = array();
        $oFinder = new Finder();
        $oFinder->files()->in($sDirectory)->name('*.php')->depth(0);
        foreach ($oFinder as $oFile) {
            $sPath = $oFile->getRealpath();
            $oQuiz = self::getInstanceFromPath($sPath);
            $aQuizzes[$sPath] = $oQuiz->getStats();
        }
        asort($aQuizzes);
        return $aQuizzes;
    }

    public function __construct (array $aData)
    {
        $this->aData = $aData;
        $this->aStats = $this->processStats();
    }

    public function serialize() {
        return serialize($this->aData);
    }

    public function unserialize($data) {
        $this->aData = unserialize($data);
        $this->aStats = $this->processStats();
    }

    public function getStats ()
    {
        return $this->aStats;
    }

    public function getQuestions ()
    {
        return $this->aData['questions'];
    }

    public static function getInstanceFromPath ($sPath)
    {
        ob_start();
        $aQuiz = require($sPath);
        ob_end_clean();

        if (! is_array($aQuiz)) {
            $aQuiz = array();
        } else {
            if (! empty($aQuiz['questions'])) {
                $aQuiz['meta']['questions_pool_size'] = count($aQuiz['questions']);
            }
            if (! isset($aQuiz['meta']['max_nb_questions']) || $aQuiz['meta']['max_nb_questions'] <= 0) {
                $aQuiz['meta']['max_nb_questions'] = $aQuiz['meta']['questions_pool_size'];
            } else {
                $aQuiz['meta']['max_nb_questions'] =
                min($aQuiz['meta']['max_nb_questions'], $aQuiz['meta']['questions_pool_size']);
            }
        }
        return new self($aQuiz);
    }

    private function processStats ()
    {
        $aDefaultMeta = array(
            'title' => '?',
            'time_limit' => 0,
            'max_nb_questions' => 0,
            'questions_pool_size' => 0
        );
        $this->aData['meta'] = array_merge($aDefaultMeta, $this->aData['meta']);
        if (empty($this->aData['questions'])) {
            $this->aData['questions'] = array();
        }

        $aQuizStats = array(
            'title' => $this->aData['meta']['title'],
            'questions_pool_size' => $this->aData['meta']['questions_pool_size'],
            'time_limit' => $this->aData['meta']['time_limit'],
            'time_limit_msg' => Tools::getRemainingTimeMsg($this->aData['meta']['time_limit'])
        );

        if ($this->aData['meta']['max_nb_questions'] <= 0) {
            $aQuizStats['nb_questions'] = $aQuizStats['questions_pool_size'];
        } else {
            $aQuizStats['nb_questions'] = min($this->aData['meta']['max_nb_questions'], $aQuizStats['questions_pool_size']);
        }

        $aQuizStats['themes'] = $this->getNbQuestionsByTheme($aQuizStats['questions_pool_size']);
        return $aQuizStats;
    }

    public function getNbQuestionsByTheme ($iNbQuestionsToScan)
    {
        $aThemes = array();
        for ($i=0; $i<$iNbQuestionsToScan; $i++) {
            $sTheme = $this->aData['questions'][$i][0];
            if (! isset($aThemes[$sTheme])) {
                $aThemes[$sTheme] = 1;
            } else {
                $aThemes[$sTheme]++;
            }
        }
        ksort($aThemes);
        return $aThemes;
    }

    public function checkWellFormed ()
    {
        $sErrorMsg = '';
        if (is_array($this->aData) && count($this->aData) == 2 && is_array($this->aData['meta']) && is_array($this->aData['questions'])) {
            if (empty($this->aData['meta']['title'])) {
                $sErrorMsg = "Titre manquant : array('meta' => array('title' => '<title>')).";
            } else if (empty($this->aData['meta']['time_limit']) || ! is_int($this->aData['meta']['time_limit'])) {
                $sErrorMsg = "Temps limite manquant ('meta' => array('time_limit' => <nb-seconds>)).";
            } else if (count($this->aData['questions']) == 0) {
                $sErrorMsg = "Il faut au moins une question.";
            } else {
                foreach ($this->aData['questions'] as $aQuestion) {
                    if (
                        count($aQuestion) != 3
                        || ! is_string($aQuestion[0])
                        || ! is_string($aQuestion[1])
                        || ! is_array($aQuestion[2]) || count($aQuestion[2]) < 2
                    ) {
                        $sErrorMsg = 'Cette question est mal formée : ' . print_r($aQuestion, true);
                        break;
                    } else {
                        $iAnswersChecked = 0;
                        foreach ($aQuestion[2] as $sAnswer => $bIsChecked) {
                            if (empty($sAnswer) || (! is_string($sAnswer) && ! is_numeric($sAnswer))) {
                                $sErrorMsg = "Cette réponse n'est pas de type string : " . print_r($sAnswer, true)
                                . ". Question concernée : " . print_r($aQuestion, true);
                                break 2;
                            }
                            if ($bIsChecked) {
                                $iAnswersChecked++;
                            }
                        }
                        if ($iAnswersChecked == 0) {
                            $sErrorMsg = "Au moins l'une des réponses doit être à cocher : " . print_r($aQuestion, true);
                            break;
                        }
                        if ($iAnswersChecked == count($aQuestion[2])) {
                            $sErrorMsg = "Au moins l'une des réponses ne doit pas être cochée : " . print_r($aQuestion, true);
                            break;
                        }
                    }
                }
            }
        } else {
            $sErrorMsg = "Le QCM doit être un tableau du type array('meta' => array(…), 'questions' => array(…)).";
        }
        return $sErrorMsg;
    }

    public function shuffleQuestions ()
    {
        $aMixedQuiz = $this->aData;
        shuffle($aMixedQuiz['questions']);
        for ($i=0; $i<count($aMixedQuiz['questions']); $i++) {
            $aQkeys = array_keys($aMixedQuiz['questions'][$i][2]);
            shuffle($aQkeys);
            $aChoices = array();
            foreach ($aQkeys as $sKey) {
                $aChoices[$sKey] = $aMixedQuiz['questions'][$i][2][$sKey];
            }
            $aMixedQuiz['questions'][$i][2] = $aChoices;
        }

        $this->aData = $aMixedQuiz;
    }
}
