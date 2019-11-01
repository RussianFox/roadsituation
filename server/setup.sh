#! /bin/sh
mkdir -p /srv/www/htdocs/roadsituation
mkdir -p /var/log/curator/
mkdir -p /var/log/roadsituation/
ln -s /git/roadsituation/web/ /srv/www/htdocs/roadsituation/map
ln -s /git/roadsituation/server/htdocs/ /srv/www/htdocs/roadsituation/server
mkdir -p /etc/nginx/vhosts.d/
ln -s /git/roadsituation/server/nginx/vhosts.d/roadsituation.conf  /etc/nginx/vhosts.d/roadsituation.conf
ln -s /git/roadsituation/server/php-fpm/roadsituation.conf /etc/php-fpm.d/roadsituation.conf
cd /srv/www/htdocs/roadsituation/server/staff
sh ./composer_install.sh
sed -i 's/.*default_server/# \0/' /etc/nginx/nginx.conf
systemctl enable elasticsearch
systemctl restart elasticsearch
systemctl enable php-fpm
systemctl restart php-fpm
systemctl enable nginx
systemctl restart nginx
