#!/usr/bin/env bash

# NAME
#     before_install.sh - Before Install CI dependencies
#
# SYNOPSIS
#     before_install.sh
#
# DESCRIPTION
#     Run commands before_installing site.

cd "$(dirname "$0")"

# Reuse ORCA's own includes.
source ../../../orca/bin/ci/_includes.sh

# If sut directory doesn't exist, there's nothing else for us to do.
[[ -d "${ORCA_SUT_DIR}" ]] || exit 1

cd ${ORCA_SUT_DIR}

# One of the patch is failing on Drupal Core 9.5. So remove that patch.
if [ "${ORCA_JOB}" = "INTEGRATED_TEST_ON_PREVIOUS_MINOR" ]; then
  NEW_JSON=$(composer config extra.patches."drupal/core" | sed -r 's/,?"3328187.*3142.patch"//')
  composer config extra.patches.drupal/core "${NEW_JSON}" --json
fi

# Remove all PHPUnit tests from individual modules, except the integrated & ExistingSite tests.
if [ "${ACMS_JOB}" = "integrated_existing_site_tests" ]; then
  find modules/*/tests tests -type f -name "*Test.php" ! -path "*/ExistingSite*/*" -exec rm -fr '{}' ';'
elif [ "${ACMS_JOB}" = "integrated_php_unit_tests" ]; then
  # Remove all isolated/existing_site phpunit tests from acquia_cms modules.
  find modules/*/tests -type f -name "*Test.php" -exec rm -fr '{}' ';'
  # Remove all existing_site phpunit tests from acquia_cms repo as those get's covered from integrated_existing_site_tests.
  find tests/src -type f -name "*Test.php" -path "*/ExistingSite*/*" -exec rm -fr '{}' ';'
elif [ "${JOB_TYPE}" = "isolated-tests" ]; then
  # Do not run any existing site tests. We run them separately.
  find tests/src modules -type f -name "*Test.php" -path "*/ExistingSite*/*" -exec rm -fr '{}' ';'
fi

cd -

../../../orca/bin/ci/before_install.sh

if [[ "${JOB_TYPE}" = "integrated-tests" && "${JOB_TYPE}" = "isolated-tests" ]]; then
  chromedriver --disable-dev-shm-usage --disable-extensions --disable-gpu --headless --no-sandbox --port=4444 &
  CHROMEDRIVER_PID=$!
  echo "CHROMEDRIVER_PID=${CHROMEDRIVER_PID}" >> ${GITHUB_ENV}
fi
