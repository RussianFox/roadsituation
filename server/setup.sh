#! /bin/sh
mkdir -p /srv/www/htdocs/roadsituation
ln -s /git/roadsituation/web /srv/www/htdocs/roadsituation/map
ln -s /git/roadsituation/server/htdocs /srv/www/htdocs/roadsituation/server
ln -s /git/roadsituation/server/nginx/conf.d/vhosts.conf  /etc/nginx/conf.d/vhosts.conf
ln -s /git/roadsituation/server/nginx/vhosts.d  /etc/nginx/vhosts.d
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
