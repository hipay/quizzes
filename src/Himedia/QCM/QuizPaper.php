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

/**
 * Correction de quiz.
 *
 * Copyright (c) 2013 Hi-Media
 * Copyright (c) 2013 Geoffroy Aubry <gaubry@hi-media.com>
 * Licensed under the GNU General Public License v3 (LGPL version 3).
 *
 * @copyright 2013 Hi-Media
 * @copyright 2013 Geoffroy Aubry <gaubry@hi-media.com>
 * @license http://www.gnu.org/licenses/gpl.html
 */
class QuizPaper
{
    private $oQuiz;
    private $aAnswers;
    private $aTiming;

    public function __construct (Quiz $oQuiz, array $aAnswers, array $aTiming)
    {
        $this->oQuiz = $oQuiz;
        $this->aAnswers = $aAnswers;
        $this->aTiming = $aTiming;
    }

    public function correct ()
    {
        $aResults = array();
        $aQuestions = $this->oQuiz->getQuestions();
        $aQuizStats = $this->oQuiz->getStats();
        $aThemes = array_keys($aQuizStats['themes']);

        // temps moyen par thème et par question :
        $aT = array();
        foreach ($this->aTiming as $iQuestion => $aStats) {
            if (! empty($aStats['elapsed_time'])) {
                $sTheme = $aQuestions[$iQuestion-1][0];
                if (! isset($aT[$sTheme])) {
                    $aT[$sTheme] = array(
                        'elasped_time' => 0,
                        'nb_questions' => 0
                    );
                }
                $aT[$sTheme]['elasped_time'] += $aStats['elapsed_time'];
                $aT[$sTheme]['nb_questions'] += 1;
                $aT[$sTheme]['avg_elapsed_time'] = round($aT[$sTheme]['elasped_time'] / $aT[$sTheme]['nb_questions'], 4);
            }
        }
        ksort($aT);
        $aResults['avg_elapsed_time_by_theme'] = $aT;

        // temps total de la session, temps moyen par question :
        $aT = array(
            'total_elasped_time' => 0,
            'total_nb_questions' => 0
        );
        foreach (array_values($aResults['avg_elapsed_time_by_theme']) as $aStats) {
            $aT['total_elasped_time'] += $aStats['elasped_time'];
        }
        $aT['total_elasped_time_msg'] = Tools::getRemainingTimeMsg(round($aT['total_elasped_time']));
        $aT['total_nb_questions'] = count($this->aAnswers);
        $aQuizStats = $this->oQuiz->getStats();
        $aT['time_limit_msg'] = Tools::getRemainingTimeMsg($aQuizStats['time_limit']);
        $aResults['total'] = $aT;

        // nombre de questions par thème :
        $aResults['total']['questions_by_theme'] =
            $this->oQuiz->getNbQuestionsByTheme($aResults['total']['total_nb_questions']);

        // catégorisation des réponses par question et par thème
        // et score par thème :
        $aAnswerTypes = array(
            'right_answers' => 0,                 // vert
            'good_but_incomplete_answers' => 0,   // bleu
            'partially_wrong_answers' => 0,       // orange
            'full_wrong_answers' => 0,            // rouge
            'skipped_questions' => 0,             // gris
            'not_displayed_questions' => 0        // noir
        );
        $aT = array();
        $aAll = $aAnswerTypes;
        foreach ($this->aAnswers as $iQuestion => $aCandidateAnswers) {
            $aQuestion = $aQuestions[$iQuestion-1];
            $sTheme = $aQuestion[0];
            if (! isset($aT[$sTheme])) {
                $aT[$sTheme] = $aAnswerTypes;
            }
            if (count($aCandidateAnswers) == 0) {
                if ($this->aTiming[$iQuestion]['elapsed_time'] == 0) {
                    $aT[$sTheme]['not_displayed_questions']++;
                    $aAll['not_displayed_questions']++;
                } else {
                    $aT[$sTheme]['skipped_questions']++;
                    $aAll['skipped_questions']++;
                }
            } else {
                list($iNeededCorrectAnswers, $iNbCorrect, $iNbIncorrect) =
                $this->analyseAnswers($aQuestion, $aCandidateAnswers);

                if ($iNbCorrect == $iNeededCorrectAnswers && $iNbIncorrect == 0) {
                    $aT[$sTheme]['right_answers']++;
                    $aAll['right_answers']++;
                } elseif ($iNbCorrect > 0 && $iNbCorrect < $iNeededCorrectAnswers && $iNbIncorrect == 0) {
                    $aT[$sTheme]['good_but_incomplete_answers']++;
                    $aAll['good_but_incomplete_answers']++;
                } elseif ($iNbCorrect > 0 && $iNbIncorrect > 0) {
                    $aT[$sTheme]['partially_wrong_answers']++;
                    $aAll['partially_wrong_answers']++;
                } else {
                    $aT[$sTheme]['full_wrong_answers']++;
                    $aAll['full_wrong_answers']++;
                }
            }
        }
        ksort($aT);
        $aResults['answer_types_by_theme'] = $aT;
        $aResults['total']['answer_types'] = $aAll;
        if ($aResults['total']['total_nb_questions'] == 0) {
            $aResults['total']['avg_elapsed_time_by_question'] = 0;
        } else {
            $aResults['total']['avg_elapsed_time_by_question'] = round(
                $aResults['total']['total_elasped_time']
                / ($aResults['total']['total_nb_questions'] - $aResults['total']['answer_types']['not_displayed_questions'])
                , 4);
        }

        // Scores par thème :
        $aScore = array();
        $aPenalty = array();
        $aAllQuestionsByTheme = array_fill_keys($aThemes, array());
        foreach ($this->aAnswers as $iQuestion => $aCandidateAnswers) {
            $aQuestion = $aQuestions[$iQuestion-1];
            $sTheme = $aQuestion[0];
            if (! isset($aScore[$sTheme])) {
                $aScore[$sTheme] = 0;
                $aPenalty[$sTheme] = 0;
                $aAllQuestionsByTheme[$sTheme] = array();
            }
            if (count($aCandidateAnswers) > 0) {
                list($iNeededCorrectAnswers, $iNbCorrect, $iNbIncorrect) =
                $this->analyseAnswers($aQuestion, $aCandidateAnswers);
//                 $aT[$sTheme] += max(-1, ($iNbCorrect-$iNbIncorrect)/$iNeededCorrectAnswers);
                $fPenalty = $iNbIncorrect / (count($aQuestion[2])-$iNeededCorrectAnswers);
                $fCredit = $iNbCorrect / $iNeededCorrectAnswers;
                $fScore = $fCredit - $fPenalty;
                $aScore[$sTheme] += $fScore;
                $aPenalty[$sTheme] -= $fPenalty;
                $aAllQuestionsByTheme[$sTheme][$iQuestion] = $fScore;
            } else {
                $aAllQuestionsByTheme[$sTheme][$iQuestion] = 0;
            }
        }
        ksort($aScore);
        ksort($aPenalty);
        foreach ($aAllQuestionsByTheme as &$aThemeScores) {
            asort($aThemeScores, SORT_NUMERIC);
        }
        $aResults['score_by_theme'] = $aScore;
        $aResults['penalty_by_theme'] = $aPenalty;
        $aResults['all_questions_by_theme'] = $aAllQuestionsByTheme;
        $aResults['total']['score'] = array_sum($aScore);
        $aResults['total']['penalty'] = array_sum($aPenalty);

        return $aResults;
    }

    private function analyseAnswers (array $aQuestion, array $aCandidateAnswers)
    {
        $aPossibleAnswers = array_values($aQuestion[2]);
        $iNeededCorrectAnswers = 0;
        foreach ($aPossibleAnswers as $bPossibleAnswer) {
            if ($bPossibleAnswer === true) {
                $iNeededCorrectAnswers++;
            }
        }

        $iNbCorrect = 0;
        $iNbIncorrect = 0;
        foreach ($aCandidateAnswers as $iChoice) {
            if ($aPossibleAnswers[$iChoice] === true) {
                $iNbCorrect++;
            } else {
                $iNbIncorrect++;
            }
        }

        return array($iNeededCorrectAnswers, $iNbCorrect, $iNbIncorrect);
    }
}
