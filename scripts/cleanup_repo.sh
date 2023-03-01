#!/bin/bash

cd "$(dirname "$0")"

PROJECT_DIR=$(dirname $(pwd))
MODULES_DIRECTORY="${PROJECT_DIR}/modules"
NOCOLOR="\033[0m"
RED="\033[0;31m"

if [[ ! -d "${MODULES_DIRECTORY}" ]]; then
  echo -e "Invalid modules directory path: ${RED}'${MODULES_DIRECTORY}'${NOCOLOR}."
  exit 1
fi

DIRECTORIES=$(find "${MODULES_DIRECTORY}" ! -path "${MODULES_DIRECTORY}" -type d -maxdepth 1)
echo "${DIRECTORIES}" | while read -r MODULE_DIRECTORY
do
  composer config repositories.drupal --unset -d "${MODULE_DIRECTORY}"
done
composer config repositories.drupal --unset -d ${PROJECT_DIR}
