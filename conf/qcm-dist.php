<?php

$sRootDir = realpath(__DIR__ . '/..');
$aDirs = array(
    'root'     => $sRootDir,
    'conf'     => $sRootDir . '/conf',
    'src'      => $sRootDir . '/src',
    'inc'      => $sRootDir . '/src/inc',
    'vendor'   => $sRootDir . '/vendor',
    'quizzes'  => $sRootDir . '/resources/quizzes',
    'sessions'  => '/var/log/himedia-quizzes',
);

return array(
    'Himedia\QCM' => array(
        'dir' => $aDirs,
        'admin_accounts' => array(
            'admin' => '098f6bcd4621d373cade4e832627b4f6',    // password: test
        ),
        'mutt_to' => 'admin@xyz.com',
        'mutt_cfg' => "set charset='utf-8'; set copy=no; set content_type=text/html; my_hdr From: Quizzes <quizzes@xyz.com>; my_hdr Reply-To: Admin <admin@xyz.com>",
        'mutt_cmd' => 'mutt -e "%1$s" -s "%2$s" -- %3$s' . " <<EOT\n%4\$s\nEOT\n",
        'crypt_salt' => 'salt'
    ),

    'GAubry\ErrorHandler'     => array(
        // Determines whether errors should be printed to the screen
        // as part of the output or if they should be hidden from the user (bool) :
        'display_errors'      => true,

        // Name of the file where script errors should be logged (string) :
        'error_log_path'      => '',

        // Error reporting level (int) :
        'error_level'         => -1,

        // Allows to deactivate '@' operator (bool) :
        'auth_error_suppr_op' => false,

        // Default error code for errors converted into exceptions or for exceptions without code (int) :
        'default_error_code'    => 1
    ),
);
