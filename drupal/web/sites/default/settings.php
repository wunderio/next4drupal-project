<?php

/**
 * @file
 * Drupal site-specific configuration file.
 */

// Database settings, overridden per environment.
$databases = [];
$databases['default']['default'] = [
  'database' => $_ENV['DB_NAME_DRUPAL'],
  'username' => $_ENV['DB_USER_DRUPAL'],
  'password' => $_ENV['DB_PASS_DRUPAL'],
  'prefix' => '',
  'host' => $_ENV['DB_HOST_DRUPAL'],
  'port' => '3306',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
];

// Salt for one-time login links, cancel links, form tokens, etc.
$settings['hash_salt'] = $_ENV['HASH_SALT'];

// Location of the site configuration files.
$settings['config_sync_directory'] = '../config/sync';

// Load services definition file.
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';

/**
 * The default list of directories that will be ignored by Drupal's file API.
 *
 * By default ignore node_modules and bower_components folders to avoid issues
 * with common frontend tools and recursive scanning of directories looking for
 * extensions.
 *
 * @see file_scan_directory()
 * @see \Drupal\Core\Extension\ExtensionDiscovery::scanDirectory()
 */
$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];

// Get the url of the frontend from an environment variable:
$settings['wunder_next.settings']['frontend_url'] = $_ENV['WUNDER_NEXT_FRONTEND_URL'];
$settings['wunder_next.settings']['revalidate_secret'] = $_ENV['DRUPAL_REVALIDATE_SECRET'];

// Environment-specific settings.
$env = $_ENV['ENVIRONMENT_NAME'];
switch ($env) {
  case 'production':
    $settings['simple_environment_indicator'] = 'DarkRed Production';
    // Warden settings.
    $config['warden.settings']['warden_token'] = $_ENV['WARDEN_TOKEN'];
    break;

  case 'main':
    $settings['simple_environment_indicator'] = 'DarkBlue Stage';
    break;

  case 'local':
  case 'lando':
    $settings['simple_environment_indicator'] = 'DarkGreen Local';
    // Skip file system permissions hardening.
    $settings['skip_permissions_hardening'] = TRUE;
    // Skip trusted host pattern.
    $settings['trusted_host_patterns'] = ['.*'];
    // Debug mode on lando.
    $settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';
    $config['system.performance']['css']['preprocess'] = FALSE;
    $config['system.performance']['js']['preprocess'] = FALSE;
    $settings['cache']['bins']['render'] = 'cache.backend.null';
    $settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
    $settings['cache']['bins']['page'] = 'cache.backend.null';
    $settings['extension_discovery_scan_tests'] = FALSE;
    $config['elasticsearch_helper.settings']['hosts'][0]['host'] = "elasticsearch";
    break;

  default:
    $settings['simple_environment_indicator'] = '#2F2942 Test';
    break;
}

/**
 * Load local development override configuration, if available.
 *
 * Use settings.local.php to override variables on secondary (staging,
 * development, etc) installations of this site. Typically used to disable
 * caching, JavaScript/CSS compression, re-routing of outgoing emails, and
 * other things that should not happen on development and testing sites.
 */
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}

// Set Elasticsearch host in silta:
if (getenv("SILTA_CLUSTER") && getenv('ELASTICSEARCH_HOST')) {
  $config['elasticsearch_helper.settings']['hosts'][0]['host'] = getenv('ELASTICSEARCH_HOST');
}

// Silta cluster configuration overrides.
if (isset($_ENV['SILTA_CLUSTER']) && file_exists($app_root . '/' . $site_path . '/settings.silta.php')) {
  include $app_root . '/' . $site_path . '/settings.silta.php';
}
