<?php

namespace Himedia\QCM;

class Tools
{
    public static function getRemainingTimeMsg ($iRemainingSeconds)
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

    /**
     * Retourne l'adresse IP de l'utilisateur.
     *
     * @return string l'adresse IP de l'utilisateur.
     */
    public static function getIP () {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $sIp = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $sIp = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            $sIp = $_SERVER['REMOTE_ADDR'];
        }
        return $sIp;
    }

    // escape '<?php' (PHP), '<!' et '<=' (PCRE)
    public static function escapeQuestionText ($sRawText)
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

    public static function formatQuestionChoices (array $aChoices)
    {
        foreach ($aChoices as $iIdx => $sRawText) {
            $aChoices[$iIdx] = self::formatText($sRawText);
        }
        return $aChoices;
    }

    public static function formatText ($sRawQuestion)
    {
        $sQuestionSubject = self::escapeQuestionText($sRawQuestion);

        $sClass = 'brush: %s; auto-links: false; toolbar: false; gutter: false';
        $sQuestionSubject = preg_replace(
            '/<pre\s*>/sim',
            '<pre class="plain">',
            $sQuestionSubject
        );
        $sQuestionSubject = preg_replace(
            '/<pre class="(bash|css|java|js|php|plain|shell|sql)">(.*?<\/pre>)/sim',
            '<div class="qcm-sh"><pre class="brush: $1; auto-links: false; toolbar: false; gutter: false">$2</div>',
            $sQuestionSubject
        );
        return $sQuestionSubject;
    }

    public static function simpleEncrypt ($text, $salt) {
        return trim(base64_encode(mcrypt_encrypt(
            MCRYPT_RIJNDAEL_256,
            $salt,
            $text,
            MCRYPT_MODE_ECB,
            mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)
        )));
    }

    public static function simpleDecrypt ($text, $salt) {
        return trim(mcrypt_decrypt(
            MCRYPT_RIJNDAEL_256,
            $salt,
            base64_decode($text),
            MCRYPT_MODE_ECB,
            mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)
        ));
    }
}
