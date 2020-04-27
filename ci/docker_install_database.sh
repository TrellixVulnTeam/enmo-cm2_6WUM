#!/bin/bash

# We need to install dependencies only for Docker
[[ ! -e /.dockerenv ]] && exit 0

set -xe

apt-get install postgresql-client -yqq

#export PGPASSWORD=$POSTGRES_PASSWORD

psql -h "postgres" -U "$POSTGRES_USER" -d "$POSTGRES_DB" -w < /builds/maarch/MaarchCourrier/sql/structure.sql
psql -h "postgres" -U "$POSTGRES_USER" -d "$POSTGRES_DB" -w < /builds/maarch/MaarchCourrier/sql/data_fr.sql

sed -i -e "s/datestyle = 'iso, mdy'/datestyle = 'iso, dmy'/g" /etc/postgresql/10/main/postgresql.conf
