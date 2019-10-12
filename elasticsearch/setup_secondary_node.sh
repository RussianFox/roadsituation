#! /bin/sh
systemctl stop elasticsearch
rm -rf /var/lib/elasticsearch/*
rm /etc/elasticsearch/elasticsearch.yml
cp /git/elasticsearch/elasticsearch_secondary.yml /etc/elasticsearch/elasticsearch.yml
