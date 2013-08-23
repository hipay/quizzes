QCM Technique
=============

# Apache 2

$ sudo a2enmod rewrite

$ sudo vi /etc/hosts
    127.0.0.1   quizzes
       
$ cat /etc/apache2/sites-enabled/quizzes.hi-media-techno.com 
<Directory /var/www/quizzes/web>
    Options -Indexes
    AllowOverride FileInfo
    Order allow,deny
    allow from all
</Directory>

<VirtualHost *:80>
    ServerName    quizzes.hi-media-techno.com
    ServerAlias    quizzes
    ServerAdmin    gaubry@hi-media.com
    RewriteEngine    On
    DocumentRoot    /var/www/quizzes/web

    ErrorLog    /var/log/apache2/quizzes-error.log
    CustomLog    /var/log/apache2/quizzes-access.log combined
    LogLevel warn
</VirtualHost>

$ sudo service apache2 restart

# Déploiement

## Linux

### Local

sudo mkdir -p /var/log/himedia-quizzes
sudo chown geoffroy:geoffroy /var/log/himedia-quizzes

src="/home/geoffroy/eclipse-workspace-4.2/himedia-quizzes" && \
dest="/var/www/qcm" && \
rm -rf "$dest" && mkdir -p "$dest" && \
rsync -axz --delete --exclude=".git/" --exclude=".gitignore" --stats "$src/" "$dest/"

### web1.multiprojet

/var/log/himedia-quizzes…

src="/home/geoffroy/eclipse-workspace-4.2/himedia-quizzes" && \
dest="web1.multiprojet:/var/www/quizzes" && \
rsync -axz --delete --exclude=".git/" --exclude=".gitignore" --stats -e ssh "$src/" "$dest/"

## Windows


