#!/bin/sh
/usr/bin/mysql \
    -u $RDS_USERNAME \
    -p$RDS_PASSWORD \
    -h $RDS_HOSTNAME \
    $RDS_DB_NAME \
    -e 'CREATE TABLE IF NOT EXISTS hashtagAnalysis(id INT UNSIGNED NOT NULL AUTO_INCREMENT, hashtag VARCHAR(63) NOT NULL, analysisText TEXT, PRIMARY KEY (id))' \
	-e 'CREATE TABLE IF NOT EXISTS userAnalysis(id INT UNSIGNED NOT NULL AUTO_INCREMENT, user VARCHAR(63) NOT NULL, analysisText TEXT, PRIMARY KEY (id))'