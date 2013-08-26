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
use Himedia\QCM\Quiz;
use Himedia\QCM\QuizPaper;
use Himedia\QCM\Tools;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\FormView;

/**
 * Contrôleur de la partie utilisateur du site.
 *
 * Copyright (c) 2013 Hi-Media
 * Licensed under the GNU General Public License v3 (LGPL version 3).
 *
 * @copyright 2013 Hi-Media
 * @license http://www.gnu.org/licenses/gpl.html
 */
class User implements ControllerProviderInterface
{
    public function connect (Application $app)
    {
        $oController = $app['controllers_factory'];
        $oController->match('/', 'Himedia\QCM\Controllers\User::index');
        $oController->get('/new-quiz', 'Himedia\QCM\Controllers\User::newQuiz');
        $oController->match('/login', function(Request $request) use ($app) {
            return $app['twig']->render('admin-login.twig', array(
                'error'         => $app['security.last_error']($request),
                'last_username' => $app['session']->get('_security.last_username'),
            ));
        })->bind('homepage');
        return $oController;
    }

    public function newQuiz (Application $app)
    {
        if ($app['session']->get('state') == 'need-candidate') {
            $app['session']->set('state', 'need-quiz');
        } elseif ($app['session']->get('state') == 'end-quiz') {
            $app['session']->invalidate();
            $app['session']->set('state', 'need-quiz');
            $sIp = Tools::getIP();
            $app['session']->set('ip', $sIp);
            $app['session']->set('host_name', gethostbyaddr($sIp));
            $app['session']->set('seed', md5(microtime().rand()));
        }

//         $subRequest = Request::create('/', 'GET');
//         return $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);
        return $app->redirect('/');
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
        $aSession = json_decode(file_get_contents($sPath), true);
        $aSummary = $aSession;

        unset($aSummary['quiz']['questions']);
        unset($aSummary['answers']);
        unset($aSummary['timing']);

        $aSummary['candidate'] = ucwords($aSummary['firstname']) . ' ' . ucwords($aSummary['lastname']);

//         $aQuiz = $app['session']->get('quiz');
//         $aQuizStats = $this->getQuizStats($aQuiz);
//         $aAnswers = $app['session']->get('answers');
//         $aTiming = $app['session']->get('timing');
        $aSummary['result'] = $this->correctQuiz($aSession['quiz'], $aSession['answers'], $aSession['timing']);

        return $aSummary;
    }

    public function index (Application $app, Request $request)
    {
        if ($app['session']->get('state') == 'need-quiz') {
            $response = $this->handleNeedQuizState($app, $request);

        } elseif ($app['session']->get('state') == 'need-candidate') {
            $response = $this->handleNeedCandidateState($app, $request);

        } elseif ($app['session']->get('state') == 'quiz-in-progress') {
            $response = $this->handleQuizInProgressState($app, $request);

        } elseif ($app['session']->get('state') == 'end-quiz') {
            $response = $this->handleEndQuizState($app, $request);
        }

        return $response;
    }

    private function handleEndQuizState (Application $app, Request $request)
    {
        $oQuiz = $app['session']->get('quiz');
        $aQuizStats = $oQuiz->getStats();
        $aAnswers = $app['session']->get('answers');
        $aTiming = $app['session']->get('timing');
        $oQuizPaper = new QuizPaper($oQuiz, $aAnswers, $aTiming);
        $aQuizResults = $oQuizPaper->correct();

        $response = $app['twig']->render('stats.twig', array(
            'subtitle' => 'Résultats',
            'firstname' => ucwords($app['session']->get('firstname')),
            'lastname' => ucwords($app['session']->get('lastname')),
            'quiz_stats' => $aQuizStats,
            'quiz_results' => $aQuizResults
        ));

        return new Response($response, 200, $app['cache.defaults']);
    }

    private function saveSession (Application $app)
    {
        $aAttributes = $app['session']->all();
        unset($aAttributes['seed']);

        $sBackupSessionPattern = $app['config']['Himedia\QCM']['dir']['sessions'] . '/%1$s_%2$s';
        $sDate = date('Ymd-His', $aAttributes['timing'][1]['start']);
        $sHash = md5($aAttributes['firstname'] . '×' . $aAttributes['lastname']);
        $sBackupSessionPath = sprintf($sBackupSessionPattern, $sDate, $sHash);

        file_put_contents($sBackupSessionPath, serialize($aAttributes));
    }

    private function handleQuizInProgressState (Application $app, Request $request)
    {
        $oQuiz = $app['session']->get('quiz');
        $aQuizStats = $oQuiz->getStats();
        $aQuestions = $oQuiz->getQuestions();

        $aAnswers = $app['session']->get('answers');
        $iQuestionNumber = count($aAnswers) + 1;

        $sObfuscatedQNumber = Obfuscator::obfuscateValue($iQuestionNumber, $app['session']->get('seed'));
        $iNbQuestions = $aQuizStats['nb_questions'];
        $aQuestion = $aQuestions[$iQuestionNumber-1];
        $sQuestionSubject = Tools::formatText(
            Tools::simpleDecrypt($aQuestion[1], $app['config']['Himedia\QCM']['crypt_salt'])
        );
        $aDecryptedChoices = array();
        foreach (array_keys($aQuestion[2]) as $sChoice) {
            $aDecryptedChoices[] = Tools::simpleDecrypt($sChoice, $app['config']['Himedia\QCM']['crypt_salt']);
        }
        $aQuestionChoices = Tools::formatQuestionChoices($aDecryptedChoices);

        $aTiming = $app['session']->get('timing');
        if (! isset($aTiming[$iQuestionNumber])) {
            $aTiming[$iQuestionNumber] = array('start' => microtime(true));
            $app['session']->set('timing', $aTiming);
        }

        $iTimelimit = $app['session']->get('timelimit');
        $iRemainingTime = $iTimelimit - microtime(true);
        $sRemainingTimeMsg = Tools::getRemainingTimeMsg($iRemainingTime);
        $iMaxElapsedTime = $aQuizStats['time_limit'];
        $sRemainingPercentage = round($iRemainingTime*100/$iMaxElapsedTime);

        if ($sRemainingPercentage <= 5) {
            $sRemainingPercentageClass = 'bar-danger';
        } elseif ($sRemainingPercentage <= 15) {
            $sRemainingPercentageClass = 'bar-warning';
        } elseif ($sRemainingPercentage <= 30) {
            $sRemainingPercentageClass = 'bar-yellow';
        } elseif ($sRemainingPercentage <= 50) {
            $sRemainingPercentageClass = 'bar-light-green';
        } else {
            $sRemainingPercentageClass = 'bar-success';
        }

        $form = $app['form.factory']
            ->createBuilder('form', null, array('csrf_protection' => true, 'intention' => 'quiz-in-progress'))
            ->add('choices', 'choice', array(
                'choices' => $aQuestionChoices,
                'multiple' => true,
                'expanded' => true
            ))
            ->add('qnumber', 'hidden', array('data' => $sObfuscatedQNumber))
            ->add('save', 'submit', array('label' => 'Valider / passer', 'attr' => array('class' => 'btn btn-primary')))
            ->add('withdraw', 'submit', array(
                'label' => 'Arrêter la session…',
                'attr' => array(
                    'class' => 'btn btn-warning btn-mini pull-right',
                    'onClick' => "return confirm('Êtes-vous sûr(e) de vouloir arrêter la session ?');"
                )
            ))
            ->getForm();
        $oFormView = $form->createView();

        if ($iRemainingTime <= 0) {
            $fNow = microtime(true);
            for ($iQ = $iQuestionNumber; $iQ <= $iNbQuestions; $iQ++) {
                if (! isset($aTiming[$iQ]['start'])) {
                    $aTiming[$iQ]['start'] = $fNow;
                }
                $aTiming[$iQ]['stop'] = $fNow;
                $aTiming[$iQ]['elapsed_time'] = round($aTiming[$iQ]['stop'] - $aTiming[$iQ]['start'], 4);
                $aAnswers[$iQ] = array();
            }
            $app['session']->set('timing', $aTiming);
            $app['session']->set('answers', $aAnswers);
            $app['session']->set('state', 'end-quiz');
            $this->saveSession($app);

            $subRequest = Request::create('/', 'GET');
            return $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);

        } elseif ('POST' == $request->getMethod()) {
            $form->bind($request);
            if ($form->isValid()) {
                if ($form->get('withdraw')->isClicked()) {
                    $fNow = microtime(true);
                    for ($iQ = $iQuestionNumber; $iQ <= $iNbQuestions; $iQ++) {
                        if (! isset($aTiming[$iQ]['start'])) {
                            $aTiming[$iQ]['start'] = $fNow;
                        }
                        $aTiming[$iQ]['stop'] = $fNow;
                        $aTiming[$iQ]['elapsed_time'] = round($aTiming[$iQ]['stop'] - $aTiming[$iQ]['start'], 4);
                        $aAnswers[$iQ] = array();
                    }
                    $app['session']->set('timing', $aTiming);
                    $app['session']->set('answers', $aAnswers);
                    $app['session']->set('state', 'end-quiz');
                    $this->saveSession($app);

                    $subRequest = Request::create('/', 'GET');
                    return $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);

                } else {
                    $aData = $form->getData();
                    if ($aData['qnumber'] == $sObfuscatedQNumber) {
                        $aTiming[$iQuestionNumber]['stop'] = microtime(true);
                        $aTiming[$iQuestionNumber]['elapsed_time'] =
                            round($aTiming[$iQuestionNumber]['stop'] - $aTiming[$iQuestionNumber]['start'], 4);
                        $aAnswers[$iQuestionNumber] = $aData['choices'];

                        $app['session']->set('timing', $aTiming);
                        $app['session']->set('answers', $aAnswers);

                        if ($iQuestionNumber == $iNbQuestions) {
                            $app['session']->set('state', 'end-quiz');
                        }
                        $this->saveSession($app);

                        $subRequest = Request::create('/', 'GET');
                        return $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);
                    } else {
                        $app['session']->getFlashBag()->add(
                            'notice',
                            'Des réponses ne concernant pas cette question ont été détectées, puis écartées :-D'
                        );
                    }
                }
            }
        } else {
            $this->saveSession($app);
        }

        // Suivi début de session :
        if (count($aAnswers) == 0) {
            $aMuttCfg = $app['config']['Himedia\QCM'];
            $sMailSubject = '[Quizzes] New session: ' . $aQuizStats['title'];
            $sMailTo = 'gaubry@hi-media.com';
            $sHTML = "<html><body>" . $aQuizStats['title'] . "</body></html>";
            $sCmd = sprintf($aMuttCfg['mutt_cmd'], $aMuttCfg['mutt_cfg'], $sMailSubject, $sMailTo, $sHTML);
            \GAubry\Helpers\Helpers::exec($sCmd);
        }

        $response = $app['twig']->render('quiz-in-progress.twig', array(
            'subtitle' => $aQuizStats['title'],
            'firstname' => ucwords($app['session']->get('firstname')),
            'lastname' => ucwords($app['session']->get('lastname')),
            'question_number' => $iQuestionNumber,
            'nb_questions' => $iNbQuestions,
            'remaining_time' => $sRemainingTimeMsg,
            'remaining_percentage' => $sRemainingPercentage,
            'remaining_percentage_class' => $sRemainingPercentageClass,
            'question_subject' => $sQuestionSubject,
            'choices' => $aQuestionChoices,
            'form' => $oFormView
        ));

        return new Response($response, 200, $app['cache.defaults']);
    }

    private function handleNeedCandidateState (Application $app, Request $request)
    {
        $form = $app['form.factory']
            ->createBuilder('form', null, array('csrf_protection' => true, 'intention' => 'need-candidate'))
            ->add('firstname', 'text', array(
                'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 2))),
                'label' => 'Votre prénom :',
                'attr' => array('placeholder' => 'prénom')
            ))
            ->add('lastname', 'text', array(
                'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 2))),
                'label' => 'Votre nom :',
                'attr' => array('placeholder' => 'nom')
            ))
            ->add('save', 'submit', array(
                'label' => 'Démarrer le questionnaire', 'attr' => array('class' => 'btn btn-primary')
            ))
            ->getForm();

        $oQuiz = $app['session']->get('quiz');
        $aQuizStats = $oQuiz->getStats();

        if ('POST' == $request->getMethod()) {
            $form->bind($request);
            if ($form->isValid()) {
                $aData = $form->getData();
                $app['session']->set('state', 'quiz-in-progress');
                $app['session']->set('firstname', $aData['firstname']);
                $app['session']->set('lastname', $aData['lastname']);
                $app['session']->set('timelimit', ceil(microtime(true) + $aQuizStats['time_limit']));
                $app['session']->set('answers', array());
                $app['session']->set('timing', array());
                $subRequest = Request::create('/', 'GET');
                return $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);
            }
        }

        $response = $app['twig']->render('need-candidate.twig', array(
            'subtitle' => $aQuizStats['title'],
            'quiz_stats' => $aQuizStats,
            'form' => $form->createView()
        ));

        return new Response($response, 200, $app['cache.defaults']);
    }

    private function handleNeedQuizState (Application $app, Request $request)
    {
        $aQuizzes = Quiz::getAllQuizzes($app['config']['Himedia\QCM']['dir']['quizzes']);
        $aObfuscatedQuizzes = Obfuscator::obfuscateKeys($aQuizzes, $app['session']->get('seed'));
        $form = $app['form.factory']
            ->createBuilder('form', null, array('csrf_protection' => true, 'intention' => 'need-quiz'))
            ->add('quizzes', 'choice', array(
                'choices' => array_fill_keys(array_keys($aObfuscatedQuizzes), true),
                'multiple' => false,
                'expanded' => true
            ))
//             ->add('save', 'submit', array('label' => 'Valider', 'attr' => array('class' => 'btn btn-primary')))
            ->getForm();

        if ('POST' == $request->getMethod()) {
            $form->bind($request);

            if ($form->isValid()) {
                $aData = $form->getData();
                $sQuizPath = Obfuscator::unobfuscateKey($aData['quizzes'], $aQuizzes, $app['session']->get('seed'));
                $oQuiz = Quiz::getInstanceFromPath($sQuizPath);
                $sErrorMsg = $oQuiz->checkWellFormed();
                if (! empty($sErrorMsg)) {
                    $oError = new FormError('Questionnaire mal formé ! ' . $sErrorMsg);
                    $form->addError($oError);
//                     var_dump($oError); die;
                }
            }

            if ($form->isValid()) {
                $oQuiz->shuffleQuestions();
                $app['session']->set('state', 'need-candidate');
                $app['session']->set('quiz', $oQuiz);
                $subRequest = Request::create('/', 'GET');
                return $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);
            }
        }

        $response = $app['twig']->render('need-quiz.twig', array(
            'quizzes' => $aObfuscatedQuizzes,
            'form' => $form->createView()
        ));

        return new Response($response, 200, $app['cache.defaults']);
    }
}
