#! /bin/sh
cp /git/roadsituation/server/yum.repos.d/elasticsearch.repo /etc/yum.repos.d/
cp /git/roadsituation/server/yum.repos.d/curator.repo /etc/yum.repos.d/
yum install epel-release yum-utils -y
yum update -y
yum install http://rpms.remirepo.net/enterprise/remi-release-7.rpm -y
yum-config-manager --enable remi-php71
yum update -y
yum install java-1.8.0-openjdk-devel -y
yum install unzip wget curl tcpdump mc screen htop iotop iftop php-cli php-fpm nginx elasticsearch git -y
yum install elasticsearch-curator -y
yum install kibana -y