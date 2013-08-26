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

namespace Himedia\QCM\Controllers;

use Himedia\QCM\Obfuscator;
use Himedia\QCM\QuizPaper;
use Himedia\QCM\Tools;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôleur de la partie administration du site.
 *
 * Copyright (c) 2013 Hi-Media
 * Licensed under the GNU General Public License v3 (LGPL version 3).
 *
 * @copyright 2013 Hi-Media
 * @license http://www.gnu.org/licenses/gpl.html
 */
class Admin implements ControllerProviderInterface
{
    public function connect (Application $app)
    {
        $oController = $app['controllers_factory'];
        $oController->match('/sessions', 'Himedia\QCM\Controllers\Admin::sessions')->bind('admin_sessions');
        $oController->get('/sessions/{sSessionId}', 'Himedia\QCM\Controllers\Admin::sessionResult');
        $oController->get('/sessions/{sSessionId}/{sThemeId}', 'Himedia\QCM\Controllers\Admin::themeResult');
        return $oController;
    }

    public function sessions (Application $app, Request $request)
    {
        $sDir = $app['config']['Himedia\QCM']['dir']['sessions'];
        $aSessions = $this->getAllSessions($sDir);
        $aObfuscatedSessions = Obfuscator::obfuscateKeys($aSessions, $app['session']->get('seed'));

        return $app['twig']->render('admin-sessions.twig', array(
            'sessions' => $aObfuscatedSessions
        ));
    }

    public function sessionResult (Application $app, Request $request, $sSessionId)
    {
        $sDir = $app['config']['Himedia\QCM']['dir']['sessions'];
        $aSessions = $this->getAllSessions($sDir);
        $sSessionPath = Obfuscator::unobfuscateKey($sSessionId, $aSessions, $app['session']->get('seed'));
        $aSession = $this->loadSession($sSessionPath);

//         return $app['twig']->render('admin-results.twig', array(
//         ));

        $oQuiz = $aSession['quiz'];
        $aQuizStats = $oQuiz->getStats();
        $aAnswers = $aSession['answers'];
        $aTiming = $aSession['timing'];
        $oQuizPaper = new QuizPaper($oQuiz, $aAnswers, $aTiming);
        $aQuizResults = $oQuizPaper->correct();

        $response = $app['twig']->render('stats.twig', array(
            'subtitle' => 'Résultats',
            'firstname' => ucwords($aSession['firstname']),
            'lastname' => ucwords($aSession['lastname']),
            'quiz_stats' => $aQuizStats,
            'quiz_results' => $aQuizResults,
            'session_key' => $sSessionId
        ));

        return new Response($response, 200, $app['cache.defaults']);
    }

    public function themeResult (Application $app, Request $request, $sSessionId, $sThemeId)
    {
        $sDir = $app['config']['Himedia\QCM']['dir']['sessions'];
        $aSessions = $this->getAllSessions($sDir);
        $sSessionPath = Obfuscator::unobfuscateKey($sSessionId, $aSessions, $app['session']->get('seed'));
        $aSession = $this->loadSession($sSessionPath);

        $oQuiz = $aSession['quiz'];
        $aQuizStats = $oQuiz->getStats();
        $aQuestions = $oQuiz->getQuestions();

        $iNbQuestions = $aQuizStats['nb_questions'];

        $aAnswers = $aSession['answers'];
        $aTiming = $aSession['timing'];
        $oQuizPaper = new QuizPaper($oQuiz, $aAnswers, $aTiming);
        $aQuizResults = $oQuizPaper->correct();

        $aThemes = array_keys($aQuizResults['answer_types_by_theme']);
        $sTheme = $aThemes[$sThemeId];
        $aThemeQuestions = $aQuizResults['all_questions_by_theme'][$sTheme];
        $aQuestionsSubject = array();
        $aQuestionsChoices = array();
        foreach (array_keys($aThemeQuestions) as $iQuestionNumber) {
            $aQuestion = $aQuestions[$iQuestionNumber-1];
            $aQuestionsSubject[$iQuestionNumber] = Tools::formatText(
                Tools::simpleDecrypt($aQuestion[1], $app['config']['Himedia\QCM']['crypt_salt'])
            );
            $aDecryptedChoices = array();
            foreach (array_keys($aQuestion[2]) as $sChoice) {
                $aDecryptedChoices[] = Tools::simpleDecrypt($sChoice, $app['config']['Himedia\QCM']['crypt_salt']);
            }
            $aQuestionsChoices[$iQuestionNumber] = Tools::formatQuestionChoices($aDecryptedChoices);
        }

        $response = $app['twig']->render('admin-theme-result.twig', array(
            'subtitle' => 'Correction par thème',
            'firstname' => ucwords($aSession['firstname']),
            'lastname' => ucwords($aSession['lastname']),
            'theme_questions' => $aThemeQuestions,
            'nb_questions' => $iNbQuestions,
            'questions_subject' => $aQuestionsSubject,
            'questions_choices' => $aQuestionsChoices,
            'questions' => $aQuestions,
            'answers' => $aAnswers,
            'quiz_results' => $aQuizResults,
            'session_key' => $sSessionId,
            'theme_id' => $sThemeId
        ));
        return new Response($response, 200, $app['cache.defaults']);
    }

    private function getAllSessions ($sDirectory)
    {
        $aSessions = array();
        $oFinder = new Finder();
        $oFinder->files()->in($sDirectory)->name('/\d{8}-\d{6}_[a-z0-9]{32}/')->depth(0)->date('since 30 days ago');
        foreach ($oFinder as $oFile) {
            $sPath = $oFile->getRealpath();
            $aSummary = $this->loadSummaryOfSession($sPath);
            $aSessions[$sPath] = $aSummary;
        }
        krsort($aSessions);
        return $aSessions;
    }

    private function loadSummaryOfSession ($sPath)
    {
        $aSession = $this->loadSession($sPath);
        $oQuizPaper = new QuizPaper($aSession['quiz'], $aSession['answers'], $aSession['timing']);

        $aSummary = $aSession;
        $aSummary['quiz_stats'] = $aSession['quiz']->getStats();
//         unset($aSummary['quiz']['questions']);
        unset($aSummary['quiz']);
        unset($aSummary['answers']);
        unset($aSummary['timing']);
        $aSummary['candidate'] = ucwords($aSummary['firstname']) . ' ' . ucwords($aSummary['lastname']);
        $aSummary['result'] = $oQuizPaper->correct();
        return $aSummary;
    }

    private function loadSession ($sPath)
    {
        $aSession = unserialize(file_get_contents($sPath));
        return $aSession;
    }
}
