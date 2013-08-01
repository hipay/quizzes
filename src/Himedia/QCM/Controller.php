<?php

namespace Himedia\QCM;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\FormView;

class Controller
{

    public function __construct (Application $app)
    {
        $app->match('/', array($this, 'index'));
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
        $aQuiz = $app['session']->get('quiz');
        $aQuizStats = $this->getQuizStats($aQuiz);
//         \GAubry\Helpers\Debug::htmlPrintr($aQuiz);

        $aAnswers = $app['session']->get('answers');
//         \GAubry\Helpers\Debug::htmlPrintr($aAnswers);

        $aTiming = $app['session']->get('timing');
//         \GAubry\Helpers\Debug::htmlPrintr($aTiming);

        if (! $app['session']->has('results')) {
            $aQuizResults = $this->correctQuiz($aQuiz, $aAnswers, $aTiming);
        }
//         \GAubry\Helpers\Debug::htmlPrintr($aQuizResults);

        $response = $app['twig']->render('first-results.twig', array(
            'subtitle' => 'Résultats',
            'firstname' => ucwords($app['session']->get('firstname')),
            'lastname' => ucwords($app['session']->get('lastname')),
            'quiz_stats' => $aQuizStats,
            'quiz_results' => $aQuizResults
        ));

        return new Response($response, 200, $app['cache.defaults']);
    }

    private function correctQuiz (array $aQuiz, array $aAnswers, array $aTiming)
    {
        $aResults = array();

        // temps moyen par thème et par question :
        $aT = array();
        foreach ($aTiming as $iQuestion => $aStats) {
            if ($aStats['elapsed_time'] > 0) {
                $sTheme = $aQuiz['questions'][$iQuestion-1][0];
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
        $aT['total_nb_questions'] = count($aAnswers);
        $aT['total_elasped_time_msg'] = $this->getRemainingTimeMsg(round($aT['total_elasped_time']));
        $aT['time_limit_msg'] = $this->getRemainingTimeMsg($aQuiz['meta']['time_limit']);
        $aResults['total'] = $aT;

        // nombre de questions par thème :
        $aResults['total']['questions_by_theme'] =
            $this->getNbQuestionsByTheme($aQuiz, $aResults['total']['total_nb_questions']);

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
        foreach ($aAnswers as $iQuestion => $aCandidateAnswers) {
            $aQuestion = $aQuiz['questions'][$iQuestion-1];
            $sTheme = $aQuestion[0];
            if (! isset($aT[$sTheme])) {
                $aT[$sTheme] = $aAnswerTypes;
            }
            if (count($aCandidateAnswers) == 0) {
                if ($aTiming[$iQuestion]['elapsed_time'] == 0) {
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

            ksort($aT);
            $aResults['answer_types_by_theme'] = $aT;
            $aResults['total']['answer_types'] = $aAll;
        }

        // Scores par thème :
        $aT = array();
        foreach ($aAnswers as $iQuestion => $aCandidateAnswers) {
            $aQuestion = $aQuiz['questions'][$iQuestion-1];
            $sTheme = $aQuestion[0];
            if (! isset($aT[$sTheme])) {
                $aT[$sTheme] = 0;
            }
            if (count($aCandidateAnswers) > 0) {
                list($iNeededCorrectAnswers, $iNbCorrect, $iNbIncorrect) =
                    $this->analyseAnswers($aQuestion, $aCandidateAnswers);
                $aT[$sTheme] += max(-1, ($iNbCorrect-$iNbIncorrect)/$iNeededCorrectAnswers);
            }

            ksort($aT);
            $aResults['score_by_theme'] = $aT;
            $aResults['total']['score'] = array_sum($aT);
        }

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

    private function handleQuizInProgressState (Application $app, Request $request)
    {
        $aQuiz = $app['session']->get('quiz');
        $aQuestions = $aQuiz['questions'];
        $aAnswers = $app['session']->get('answers');
        $iQuestionNumber = count($aAnswers) + 1;
        $sObfuscatedQNumber = $this->obfuscateValue($iQuestionNumber, $app['session']->get('seed'));
        $iNbQuestions = $aQuiz['meta']['max_nb_questions'];
        $aQuestion = $aQuestions[$iQuestionNumber-1];
        $sQuestionSubject = $this->formatQuestionSubject($aQuestion[1]);
        $aQuestionChoices = $this->formatQuestionChoices(array_keys($aQuestion[2]));

        $aTiming = $app['session']->get('timing');
        if (! isset($aTiming[$iQuestionNumber])) {
            $aTiming[$iQuestionNumber] = array('start' => microtime(true));
            $app['session']->set('timing', $aTiming);
        }

        $iTimelimit = $app['session']->get('timelimit');
        $iRemainingTime = $iTimelimit - microtime(true);
        $sRemainingTimeMsg = $this->getRemainingTimeMsg($iRemainingTime);
        $iMaxElapsedTime = $aQuiz['meta']['time_limit'];
        $sRemainingPercentage = round($iRemainingTime*100/$iMaxElapsedTime);
        $sRemainingPercentageClass = ($sRemainingPercentage <= 10
            ? 'progress-danger'
            : ($sRemainingPercentage <= 30 ? 'progress-warning' : 'progress-success'));

        $form = $app['form.factory']
            ->createBuilder('form', null, array('csrf_protection' => true, 'intention' => 'quiz-in-progress'))
            ->add('choices', 'choice', array(
                'choices' => $aQuestionChoices,
                'multiple' => true,
                'expanded' => true
            ))
            ->add('qnumber', 'hidden', array('data' => $sObfuscatedQNumber))
            ->add('save', 'submit', array('label' => 'Valider', 'attr' => array('class' => 'btn btn-primary')))
            ->add('withdraw', 'submit', array(
                'label' => 'Abréger les souffrances…',
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
        }

        $response = $app['twig']->render('quiz-in-progress.twig', array(
            'subtitle' => $aQuiz['meta']['title'],
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

    // escape '<?php' (PHP), '<!' et '<=' (PCRE)
    private function escapeQuestionText ($sRawText)
    {
        $aSearch = array(
            '/<\?php/',
            '/<!(?!--)/',
            '/<=/'
        );
        $aReplace = array(
            '&lt;?php',
            '&lt;!',
            '&lt;='
        );
        $sEscapedText = preg_replace($aSearch, $aReplace, $sRawText);
        return $sEscapedText;
    }

    private function formatQuestionChoices (array $aChoices)
    {
        foreach ($aChoices as $iIdx => $sRawText) {
            $aChoices[$iIdx] = $this->escapeQuestionText($sRawText);
        }
        return $aChoices;
    }

    private function formatQuestionSubject ($sRawQuestion)
    {
        $sQuestionSubject = $this->escapeQuestionText($sRawQuestion);

        $sClass = 'brush: %s; auto-links: false; toolbar: false; gutter: false';
        $sQuestionSubject = preg_replace(
            '/<pre\s*>/sim',
            '<pre class="plain">',
            $sQuestionSubject
        );
        $sQuestionSubject = preg_replace(
            '/<pre class="(js|php|plain|sql)">(.*?<\/pre>)/sim',
            '<div class="qcm-sh"><pre class="brush: $1; auto-links: false; toolbar: false; gutter: false">$2</div>',
            $sQuestionSubject
        );
        return $sQuestionSubject;
    }

    private function getQuizStats (array $aQuiz)
    {
        $aDefaultMeta = array(
            'title' => '?',
            'time_limit' => 0,
            'max_nb_questions' => 0,
            'questions_pool_size' => 0
        );
        $aQuiz['meta'] = array_merge($aDefaultMeta, $aQuiz['meta']);
        if (empty($aQuiz['questions'])) {
            $aQuiz['questions'] = array();
        }

        $aQuizStats = array(
            'title' => $aQuiz['meta']['title'],
            'questions_pool_size' => $aQuiz['meta']['questions_pool_size'],
            'time_limit' => $aQuiz['meta']['time_limit'],
            'time_limit_msg' => $this->getRemainingTimeMsg($aQuiz['meta']['time_limit'])
        );

        if ($aQuiz['meta']['max_nb_questions'] <= 0) {
            $aQuizStats['nb_questions'] = $aQuizStats['questions_pool_size'];
        } else {
            $aQuizStats['nb_questions'] = min($aQuiz['meta']['max_nb_questions'], $aQuizStats['questions_pool_size']);
        }

        $aQuizStats['themes'] = $this->getNbQuestionsByTheme($aQuiz, $aQuizStats['questions_pool_size']);
        return $aQuizStats;
    }

    private function getNbQuestionsByTheme (array $aQuiz, $iQToScan)
    {
        $aThemes = array();
        for ($i=0; $i<$iQToScan; $i++) {
//         foreach ($aQuiz['questions'] as $i => $aQuestion) {
            $sTheme = $aQuiz['questions'][$i][0];
            if (! isset($aThemes[$sTheme])) {
                $aThemes[$sTheme] = 1;
            } else {
                $aThemes[$sTheme]++;
            }
        }
        ksort($aThemes);
        return $aThemes;
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

        $aQuiz = $app['session']->get('quiz');
        $aQuizStats = $this->getQuizStats($aQuiz);

        if ('POST' == $request->getMethod()) {
            $form->bind($request);
            if ($form->isValid()) {
                $aData = $form->getData();
                $app['session']->set('state', 'quiz-in-progress');
                $app['session']->set('firstname', $aData['firstname']);
                $app['session']->set('lastname', $aData['lastname']);
                $app['session']->set('timelimit', ceil(microtime(true) + $aQuiz['meta']['time_limit']));
                $app['session']->set('answers', array());
                $app['session']->set('timing', array());
                $subRequest = Request::create('/', 'GET');
                return $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);
            }
        }

        $response = $app['twig']->render('need-candidate.twig', array(
            'subtitle' => $aQuiz['meta']['title'],
            'quiz_stats' => $aQuizStats,
            'form' => $form->createView()
        ));

        return new Response($response, 200, $app['cache.defaults']);
    }

    private function getRemainingTimeMsg ($iRemainingSeconds)
    {
        $iRemainingSeconds = max(0, $iRemainingSeconds);
        $sTimelimitMsg = '';
        if ($iRemainingSeconds >= 60) {
            $iRemainingMinutes = floor($iRemainingSeconds / 60);
            $sTimelimitMsg = $iRemainingMinutes . ' minute' . ($iRemainingMinutes > 1 ? 's' : '');
        }
        if ($iRemainingSeconds < 60 || $iRemainingSeconds % 60 != 0) {
            $sTimelimitMsg .= ($iRemainingSeconds >= 60 ? ' et ' : '')
                            . ($iRemainingSeconds % 60) . ' seconde'
                            . ($iRemainingSeconds % 60 > 1 ? 's' : '');
        }
        return $sTimelimitMsg;
    }

    private function getAllQuizzes ($sDirectory)
    {
        $aQuizzes = array();
        $oFinder = new Finder();
        $oFinder->files()->in($sDirectory)->name('*.php')->depth(0);
        foreach ($oFinder as $oFile) {
            $sPath = $oFile->getRealpath();
            $aQuiz = $this->loadQuiz($sPath);
            $aQuizzes[$sPath] = $this->getQuizStats($aQuiz);
        }
        return $aQuizzes;
    }

    private function loadQuiz ($sPath)
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
        return $aQuiz;
    }

    private function obfuscateValue ($mValue, $sSeed) {
        return md5($sSeed.(string)$mValue);
    }

    private function obfuscateKeys (array $aData, $sSeed)
    {
        $aObfuscated = array();
        foreach ($aData as $mKey => $mValue) {
            $aObfuscated[$this->obfuscateValue($mKey, $sSeed)] = $mValue;
        }
        return $aObfuscated;
    }

    private function unobfuscateKey ($sKey, array $aData, $sSeed)
    {
        foreach (array_keys($aData) as $mKey) {
            if ($this->obfuscateValue($mKey, $sSeed) == $sKey) {
                return $mKey;
            }
        }
        throw new \RuntimeException("Unable to retrieve original key of '$sKey'!");
    }

    private function handleNeedQuizState (Application $app, Request $request)
    {
        $aQuizzes = $this->getAllQuizzes($app['config']['Himedia\QCM']['dir']['quizzes']);
        $aObfuscatedQuizzes = $this->obfuscateKeys($aQuizzes, $app['session']->get('seed'));
        $form = $app['form.factory']
            ->createBuilder('form', null, array('csrf_protection' => true, 'intention' => 'need-quiz'))
            ->add('quizzes', 'choice', array(
                'choices' => array_fill_keys(array_keys($aObfuscatedQuizzes), true),
                'multiple' => false,
                'expanded' => true
            ))
            ->add('save', 'submit', array('label' => 'Valider', 'attr' => array('class' => 'btn btn-primary')))
            ->getForm();

        if ('POST' == $request->getMethod()) {
            $form->bind($request);

            if ($form->isValid()) {
                $aData = $form->getData();
                $sQuizPath = $this->unobfuscateKey($aData['quizzes'], $aQuizzes, $app['session']->get('seed'));
                $aQuiz = $this->loadQuiz($sQuizPath);
                $sErrorMsg = $this->checkWellFormedQuiz($aQuiz);
                if (! empty($sErrorMsg)) {
                    $oError = new FormError('Questionnaire mal formé ! ' . $sErrorMsg);
                    $form->addError($oError);
                }
            }

            if ($form->isValid()) {
                $aMixedQuiz = $this->shuffleQuiz($aQuiz);
                $app['session']->set('state', 'need-candidate');
                $app['session']->set('quiz', $aMixedQuiz);
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

    private function shuffleQuiz (array $aQuiz)
    {
        $aMixedQuiz = $aQuiz;
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

        return $aMixedQuiz;
    }

//     // http://markusslima.github.io/bootstrap-filestyle/
//     private function handleNeedQuizState (Application $app, Request $request)
//     {
//         $form = $app['form.factory']
//             ->createBuilder('form', null, array('csrf_protection' => true, 'intention' => 'need-quiz'))
//             ->add('attachment', 'file', array('label' => 'Charger un questionnaire :'))
//             ->add('save', 'submit', array('label' => 'Valider', 'attr' => array('class' => 'btn btn-primary')))
//             ->getForm();

//         if ('POST' == $request->getMethod()) {
//             $form->bind($request);

//             if ($form->isValid()) {
//                 $aData = $form->getData();
//                 /* @var $oUploadedFile \Symfony\Component\HttpFoundation\File\UploadedFile */
//                 $oUploadedFile = $aData['attachment'];
//                 try {
//                     $oUploadedFile->move('/tmp', 'toto');
//                 } catch (FileException $oException) {
//                     $oError = new FormError($oException->getMessage());
//                     $form->addError($oError);
//                 }
//             }

//             if ($form->isValid()) {
//                 ob_start();
//                 $aQcmWithAnswers = require('/tmp/toto');
//                 ob_end_clean();

//                 if (! is_array($aQcmWithAnswers)) {
//                     $aQcmWithAnswers = array();
//                 }
//                 $sErrorMsg = $this->checkWellFormedQuiz($aQcmWithAnswers);
//                 if (! empty($sErrorMsg)) {
//                     $oError = new FormError('Questionnaire mal formé ! ' . $sErrorMsg);
//                     $form->addError($oError);
//                 }
//             }

//             if ($form->isValid()) {
//                 $aQcmWoAnswers = $this->removeAnswers($aQcmWithAnswers);
//                 $app['session']->set('state', 'need-candidate');
//                 $app['session']->set('quiz', $aQcmWoAnswers);
//                 $subRequest = Request::create('/', 'GET');
//                 return $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);
//             }
//         }

//         $response = $app['twig']->render('need-quiz.twig', array(
//             'form' => $form->createView()
//         ));

//         return new Response($response, 200, $app['cache.defaults']);
//     }

//     private function removeAnswers (array $aQcm)
//     {
//         foreach ($aQcm['questions'] as $iQuestionIdx => $aQuestion) {
//             $aQcm['questions'][$iQuestionIdx][2] = array_keys($aQuestion[2]);
//         }
//         return $aQcm;
//     }

    private function checkWellFormedQuiz (array $aQcm)
    {
        $sErrorMsg = '';
        if (is_array($aQcm) && count($aQcm) == 2 && is_array($aQcm['meta']) && is_array($aQcm['questions'])) {
            if (empty($aQcm['meta']['title'])) {
                $sErrorMsg = "Titre manquant : array('meta' => array('title' => '<title>')).";
            } else if (empty($aQcm['meta']['time_limit']) || ! is_int($aQcm['meta']['time_limit'])) {
                $sErrorMsg = "Temps limite manquant ('meta' => array('time_limit' => <nb-seconds>)).";
            } else if (count($aQcm['questions']) == 0) {
                $sErrorMsg = "Il faut au moins une question.";
            } else {
                foreach ($aQcm['questions'] as $aQuestion) {
                    if (
                        count($aQuestion) != 3
                        || ! is_string($aQuestion[0])
                        || ! is_string($aQuestion[1])
                        || ! is_array($aQuestion[2]) || count($aQuestion[2]) < 2
                    ) {
                        $sErrorMsg = 'Cette question est mal formée : ' . print_r($aQuestion, true);
                        break;
                    } else {
                        $bAnAnswerIsChecked = false;
                        foreach ($aQuestion[2] as $sAnswer => $bIsChecked) {
                            if (empty($sAnswer) || (! is_string($sAnswer) && ! is_numeric($sAnswer))) {
                                $sErrorMsg = "Cette réponse n'est pas de type string : " . print_r($sAnswer, true)
                                           . ". Question concernée : " . print_r($aQuestion, true);
                                break 2;
                            }
                            $bAnAnswerIsChecked = ($bAnAnswerIsChecked || $bIsChecked);
                        }
                        if (! $bAnAnswerIsChecked) {
                            $sErrorMsg = "Au moins l'une des réponses doit être à cocher : " . print_r($aQuestion, true);
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
}
