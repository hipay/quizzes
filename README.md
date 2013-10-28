# Quizzes

[![Latest stable version](https://poser.pugx.org/himedia/quizzes/v/stable.png "Latest stable version")](https://packagist.org/packages/himedia/quizzes)

Plate-forme de quizzes à choix multiples (QCM) avec interface d'analyse des résultats.

*Technologies* : [Silex](http://silex.sensiolabs.org/), [Twig](http://twig.sensiolabs.org/),
[Bootstrap](http://getbootstrap.com/2.3.2/), [Composer](http://getcomposer.org), aucune base de données.

## Sommaire

  * [Description](#description)
  * [Installation et configuration](#installation-et-configuration)
  * [Captures d'écran](#captures-d%C3%A9cran)
  * [Copyrights & licensing](#copyrights--licensing)
  * [Change log](#change-log)
  * [Git branching model](#git-branching-model)

## Description

### Deux zones

  * L'une publique pour choisir un questionnaire, le dérouler et obtenir score et statistiques.
  * L'autre avec mot de passe pour accéder aux sessions passées, à leur score et statistiques,
    à leur correction détaillée et au suivi temps réel des sessions en cours.

### Questionnaires

La plate-forme de quizzes permet :

  * d'héberger et proposer de multiples questionnaires,
  * de proposer des sessions mélangeant plusieurs questionnaires,
  * de réaliser des sessions ne portant que sur une partie des questions d'un ou plusieurs questionnaires,
    questions tirées aléatoirement,
  * de désactiver voire masquer des questionnaires tout en continuant de les inclure dans d'autres questionnaires,
  * une grande facilité d'ajout de questionnaires, coloration syntaxique des bouts de code pouvant émailler
    les questions et propositions de réponse,
  * une impression du résultat des sessions.

Dans un questionnaire :

  * chaque question appartient à un thème afin de faciliter l'analyse des réponses, mais cette information
    ne transparaît pas forcément dans l'énoncé des questions
    (un seul thème est retenu par question par souci de simplicité),
  * thèmes, questions et propositions arrivent dans un ordre différent à chaque session,
  * le temps restant est affiché constamment,
  * pas moyen de revenir sur une question précédente (page précédente sans effet),
  * le barème est optimal lorsque toute question admet **au moins une bonne proposition**
    et **au moins une mauvaise proposition**.

### Barème

Le barème favorise l'absence de réponse à la mauvaise réponse.
**Il vaut mieux s'abstenir lorsque l'on n'est pas sûr de soi.**

Ainsi de manière générale, si une question possède `P` propositions de réponse, alors :

  * chaque question nécessite de cocher `1` à `P-1` cases et rapporte de `-1` à `1` point, `0` si non répondue.
  * si une question requiert `N` cases cochées pour la bonne réponse, alors :
     * chaque case bien cochée rapporte `1/N` point,
     * chaque case mal cochée enlève `1/(P-N)` point.

**Il en découle que les trois stratégies suivantes aboutissent à un score nul :**
  * cocher toutes les cases,
  * n'en cocher aucune
  * et statistiquement cocher au hasard `1` à `P-1` cases.

## Installation et configuration

### Git clone

Cloner dans le répertoire de votre choix, *par ex.* `/var/www/quizzes` (le répertoire doit être vide) :

```bash
$ git clone git@github.com:Hi-Media/Quizzes.git /var/www/quizzes
```

### Dépendences

#### Composer

La plupart des dépendences sont gérées par [composer](http://getcomposer.org).
Lancer l'une des commandes suivantes :

```bash
$ composer install
# or
$ php composer.phar install
```

Au besoin, pour installer composer localement, lancer l'une des commandes suivantes :

```bash
$ curl -sS https://getcomposer.org/installer | php
# or
$ wget --no-check-certificate -q -O- https://getcomposer.org/installer | php
```

Lire <http://getcomposer.org/doc/00-intro.md#installation-nix> pour plus d'informations.

#### Mailing

L'envoi de mail exploite [mutt](http://www.mutt.org/).

### Configuration

#### Apache 2

Les *rewrite rules* sont nécessaires.
Un fichier `.htaccess` se trouve dans `/www` pour rediriger les URLs sur `/web/index.php`.
Au besoin :

```bash
$ sudo a2enmod rewrite
```

Exemple de *virtual host* :

```bash
$ cat /etc/apache2/sites-enabled/quizzes.xyz.com

<Directory /var/www/quizzes/web>
    Options -Indexes
    AllowOverride FileInfo
    Order allow,deny
    allow from all
</Directory>

<VirtualHost *:80>
    ServerName    quizzes.xyz.com
    ServerAlias    quizzes
    ServerAdmin    admin@xyz.com
    RewriteEngine    On
    DocumentRoot    /var/www/quizzes/web

    ErrorLog    /var/log/apache2/quizzes-error.log
    CustomLog    /var/log/apache2/quizzes-access.log combined
    LogLevel warn
</VirtualHost>
```

#### Application
Initialiser le fichier de configuration en dupliquant `conf/qcm-dist.php` et en l'adaptant :

```bash
$ cp '/var/www/quizzes/conf/qcm-dist.php' '/var/www/quizzes/conf/qcm.php'
```

Pour mettre à jour des comptes d'administration modifier la clé `'admin_accounts'`,
tableau au format `login => md5(password)`.

#### Mise à jour des questionnaires

Les questionnaires sont cryptés en AES-256 sur le serveur web.

Lors d'une mise à jour des questionnaires exécuter le script `/src/encrypt.php` afin de régénérer
les `/resources/quizzes/*.enc.php` à partir des `/resources/quizzes/src/*.php`.
Le répertoire `/resources/quizzes/src` n'est alors plus nécessaire, ainsi que `/src/encrypt.php`.

**Des exemples de questionnaires sont disponibles dans `/resources/quizzes/examples` :**

  * 2 mini questionnaires intitulés « *Additions* » et « *Multiplications* »,
  * 1 questionnaire « *JavaScript* » d'une seule question mais illustrant l'insertion de code
    avec coloration syntaxique,
  * 1 questionnaire nommé « *Toutes les questions !* » expliquant comment déclarer un questionnaire
    comme l'union d'autres questionnaires
  * et 1 questionnaire intitulé « *Un petit peu de tout…* » piochant au hasard un nombre défini de question
    parmi celles des autres questionnaires.

Les copier dans `/resources/quizzes/src` pour les utiliser dans l'application…

## Captures d'écran

### Déroulement d'une session

Choix de la session :

[![Choix de la session](doc/images/choix_session2.png "Choix de la session")](doc/images/choix_session2.png)

Un questionnaire peut être la réunion de plusieurs questionnaires ou une partie d'un autre questionnaire :

[![Questionnaire à partir d'autres](doc/images/choix_session1.png "Questionnaire à partir d'autres")](doc/images/choix_session1.png)

Identification du candidat :

[![Identification du candidat](doc/images/identification.png "Identification du candidat")](doc/images/identification.png)

Exemple d'affichage d'une question :

[![Affichage d'une question](doc/images/question.png "Affichage d'une question")](doc/images/question.png)

### Analyse

Accueil de la section d'administration avec liste des sessions passées et en cours :

[![Liste des sessions](doc/images/listing_sessions.png "Liste des sessions")](doc/images/listing_sessions.png)

Résultat général d'une session :

[![Résultat général d'une session](doc/images/resultat_global.png "Résultat général d'une session")](doc/images/resultat_global.png)

Score par thème avec visualisation de la quantité de points perdus par pénalités :

[![Score par thème](doc/images/score_par_theme.png "Score par thème")](doc/images/score_par_theme.png)

Temps moyen de réponse par thème :

[![Temps par thème](doc/images/temps_par_theme.png "Temps par thème")](doc/images/temps_par_theme.png)

Catégorisation des réponses par thème :

[![Catégorisation des réponses par thème](doc/images/categorisation_par_theme.png "Catégorisation des réponses par thème")](doc/images/categorisation_par_theme.png)

Correction d'une question, accessible seulement à partir de la zone privée :

[![Correction d'une question](doc/images/correction_question.png "Correction d'une question")](doc/images/correction_question.png)

Configuration d'un questionnaire, dans `/resources/quizzes/src` :

```php
<?php

return array(
    'meta' => array(
        'title' => 'POO et design patterns',
        'time_limit' => 15*20,
        'max_nb_questions' => 0,
        'status' => 'available' // {'available', 'deactivated', 'hidden'}
    ),
    'questions' => array(
        array(
            'POO',
            "Quel est le patron de conception central dans Doctrine 2 ?",
            array(
                "table data gateway" => false,
                "active record" => false,
                "data mapper" => true,
                "row data gateway" => false,
            )
        ),
        …
    )
);
```

## Copyrights & licensing

Licensed under the GNU General Public License v3 (GPL-3.0+).
See [LICENSE](LICENSE) file for details.

## Change log
See [CHANGELOG](CHANGELOG.md) file for details.

## Git branching model
The git branching model used for development is the one described and assisted
by `twgit` tool: [https://github.com/Twenga/twgit](https://github.com/Twenga/twgit).
