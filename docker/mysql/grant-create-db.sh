#!/usr/bin/env bash
# Grant the application user full privileges so it can CREATE DATABASE
# for tenant onboarding. Runs once on first container init.

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    GRANT ALL PRIVILEGES ON *.* TO '${MYSQL_USER}'@'%' WITH GRANT OPTION;
    FLUSH PRIVILEGES;
EOSQL
