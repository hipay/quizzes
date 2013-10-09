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
 * Classe outil de masquage/démasquage d'identifiants internes à destination de l'URL ou de formulaires.
 *
 * Copyright (c) 2013 Hi-Media
 * Copyright (c) 2013 Geoffroy Aubry <gaubry@hi-media.com>
 * Licensed under the GNU General Public License v3 (LGPL version 3).
 *
 * @copyright 2013 Hi-Media
 * @copyright 2013 Geoffroy Aubry <gaubry@hi-media.com>
 * @license http://www.gnu.org/licenses/gpl.html
 */
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