<?php

namespace Himedia\QCM;

class Obfuscator
{
    public static function obfuscateValue ($mValue, $sSeed) {
        return md5($sSeed.(string)$mValue);
    }

    public static function obfuscateKeys (array $aData, $sSeed)
    {
        $aObfuscated = array();
        foreach ($aData as $mKey => $mValue) {
            $aObfuscated[self::obfuscateValue($mKey, $sSeed)] = $mValue;
        }
        return $aObfuscated;
    }

    public static function unobfuscateKey ($sKey, array $aData, $sSeed)
    {
        foreach (array_keys($aData) as $mKey) {
            if (self::obfuscateValue($mKey, $sSeed) == $sKey) {
                return $mKey;
            }
        }
        throw new \RuntimeException("Unable to retrieve original key of '$sKey'!");
    }
}