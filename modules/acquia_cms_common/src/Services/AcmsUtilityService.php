<?php

namespace Drupal\acquia_cms_common\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\State\StateInterface;

/**
 * Defines a service for ACMS.
 */
class AcmsUtilityService {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module preinstall triggered status.
   *
   * @var string
   *
   * @see \Drupal\acquia_cms_common\Services\AcmsUtilityService::setModulePreinstallTriggered()
   * @see acquia_cms_article_modules_installed()
   * @see acquia_cms_event_modules_installed()
   * @see acquia_cms_person_modules_installed()
   * @see acquia_cms_place_modules_installed()
   * @see acquia_cms_search_modules_installed()
   */

  private static $modulePreinstallTriggered;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new AcmsService object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The ModuleHandlerInterface.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(ModuleHandlerInterface $moduleHandler,
                              ConfigFactoryInterface $config_factory,
                              StateInterface $state) {
    $this->moduleHandler = $moduleHandler;
    $this->configFactory = $config_factory;
    $this->state = $state;
  }

  /**
   * Fetch acquia cms profile with list of enabled modules of ACMS.
   */
  public function getAcquiaCmsModuleList(): array {
    $profile_modules = $this->moduleHandler->getModuleList();
    return array_filter($profile_modules, function ($key) {
      return str_starts_with($key, 'acquia_cms');
    }, ARRAY_FILTER_USE_KEY);
  }

  /**
   * Build and import site studio packages.
   */
  public function siteStudioPackageImport() {
    if ($this->moduleHandler->moduleExists('acquia_cms_site_studio')) {
      $config = $this->configFactory->get('cohesion.settings');
      if ($config->get('api_key') && $config->get('organization_key')) {
        batch_set(_acquia_cms_site_studio_install_initialize());
        _acquia_cms_site_studio_import_ui_kit();
        _acquia_cms_site_studio_update_settings();
        return drush_backend_batch_process();
      }
    }
  }

  /**
   * Validates an array of config data that contains dependency information.
   *
   * Copied from Drupal/Core/Config/ConfigInstaller.php.
   *
   * @param string $config_name
   *   The name of the configuration object that is being validated.
   * @param array $data
   *   Configuration data.
   * @param array $enabled_extensions
   *   A list of all the currently enabled modules and themes.
   * @param array $all_config
   *   A list of all the active configuration names.
   *
   * @return bool
   *   TRUE if all dependencies are present, FALSE otherwise.
   */
  public function validateDependencies(string $config_name, array $data, array $enabled_extensions, array $all_config): bool {
    if (!isset($data['dependencies'])) {
      // Simple config or a config entity without dependencies.
      [$provider] = explode('.', $config_name, 2);
      return in_array($provider, $enabled_extensions, TRUE);
    }

    $missing = $this->getMissingDependencies($config_name, $data, $enabled_extensions, $all_config);
    return empty($missing);
  }

  /**
   * Returns an array of missing dependencies for a config object.
   *
   * Copied from Drupal/Core/Config/ConfigInstaller.php.
   *
   * @param string $config_name
   *   The name of the configuration object that is being validated.
   * @param array $data
   *   Configuration data.
   * @param array $enabled_extensions
   *   A list of all the currently enabled modules and themes.
   * @param array $all_config
   *   A list of all the active configuration names.
   *
   * @return array
   *   A list of missing config dependencies.
   */
  protected function getMissingDependencies(string $config_name, array $data, array $enabled_extensions, array $all_config): array {
    $missing = [];
    if (isset($data['dependencies'])) {
      [$provider] = explode('.', $config_name, 2);
      $all_dependencies = $data['dependencies'];

      // Ensure enforced dependencies are included.
      if (isset($all_dependencies['enforced'])) {
        $all_dependencies = array_merge($all_dependencies, $data['dependencies']['enforced']);
        unset($all_dependencies['enforced']);
      }
      // Ensure the configuration entity type provider is in the list of
      // dependencies.
      if (!isset($all_dependencies['module']) || !in_array($provider, $all_dependencies['module'])) {
        $all_dependencies['module'][] = $provider;
      }

      foreach ($all_dependencies as $type => $dependencies) {
        $list_to_check = [];
        switch ($type) {
          case 'module':
          case 'theme':
            $list_to_check = $enabled_extensions;
            break;

          case 'config':
            $list_to_check = $all_config;
            break;
        }
        if (!empty($list_to_check)) {
          $missing = array_merge($missing, array_diff($dependencies, $list_to_check));
        }
      }
    }

    return $missing;
  }

  /**
   * Gets the list of enabled extensions including both modules and themes.
   *
   * Copied from Drupal/Core/Config/ConfigInstaller.php.
   *
   * @return array
   *   A list of enabled extensions which includes both modules and themes.
   */
  public function getEnabledExtensions(): array {
    // Read enabled extensions directly from configuration to avoid circular
    // dependencies on ModuleHandler and ThemeHandler.
    $extension_config = $this->configFactory->get('core.extension');
    $enabled_extensions = (array) $extension_config->get('module');
    $enabled_extensions += (array) $extension_config->get('theme');
    // Core can provide configuration.
    $enabled_extensions['core'] = 'core';
    return array_keys($enabled_extensions);
  }

  /**
   * Set module preinstall triggered state variable.
   *
   * As service will be rebuild during the module install,
   * set a private static variable is used to store this information.
   *
   * @param string $module
   *   The module name.
   */
  public function setModulePreinstallTriggered(string $module) {
    static::$modulePreinstallTriggered = $module;
  }

  /**
   * Get module preinstall triggered state variable.
   *
   * @return string|null
   *   The module name or null.
   */
  public function getModulePreinstallTriggered(): ?string {
    if (static::$modulePreinstallTriggered !== NULL) {
      return static::$modulePreinstallTriggered;
    }
    return NULL;
  }

  /**
   * Get selected starter-kit.
   *
   * @return string|null
   *   The starter-kit name or null.
   */
  public function getStarterKit(): ?string {
    // List of available starter kits.
    $starter_kits = [
      'acquia_cms_enterprise_low_code' => 'Acquia CMS Enterprise Low Code',
      'acquia_cms_headless' => 'Acquia CMS Headless' ,
      'acquia_cms_community' => 'Acquia CMS Community',
    ];

    // Check for the starter kit selection.
    if ($starter_kit = $this->state->get('acquia_cms.starter_kit')) {
      // Return starter-kit value.
      return $starter_kits[$starter_kit];
    }

    return NULL;
  }

}
