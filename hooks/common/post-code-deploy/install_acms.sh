#!/bin/sh
# Cloud Hook: Acquia CMS install,update and modules toggle.

which drush
drush --version

site="$1"
target_env="$2"

# Fresh install of Acquia CMS. We need to clear cache first in case memcache is
# enabled, else there will be a collision on site install.
/usr/local/bin/drush9 @$site.$target_env cr

# Only run update hooks on ode4. ode4 is used to test update path.
if [ "$target_env" = "ode5" ]; then
    /usr/local/bin/drush9 @$site.$target_env updatedb --no-interaction
# Install Acquia CMS.
else
    /var/www/html/$site.$target_env/vendor/bin/acms site-install minimal --account-pass=admin --yes --account-mail=no-reply@example.com --site-mail=no-reply@example.com
fi

# Toggle Modules based on the environment.
/usr/local/bin/drush9 @$site.$target_env pm-enable acquia_cms_development --yes

# Enable development related modules. This is for ease of development for core
# Acquia CMS development.
echo "Enabling Acquia CMS Starter in $target_env"
case $target_env in
  ode1)
    /usr/local/bin/drush9 @$site.$target_env pm-enable acquia_cms_starter --yes
    ;;

  ode3)
    /usr/local/bin/drush9 @$site.$target_env pm-enable acquia_cms_starter --yes
    ;;

  stage)
    /usr/local/bin/drush9 @$site.$target_env pm-enable acquia_cms_starter --yes
    ;;
esac
