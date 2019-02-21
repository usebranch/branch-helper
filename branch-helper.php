<?php

/**
 * Plugin Name: Branch Helper
 * Plugin URI: https://usebranch.co
 * Description: Client plugin for Branch
 * Version: 0.0.1
 * Author: Branch
 * Author URI: https://usebranch.co
 * License: GNU GENERAL PUBLIC LICENSE
 */

include_once(ABSPATH . 'wp-admin/includes/plugin.php');
include_once(ABSPATH . 'wp-admin/includes/file.php');
include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
include_once(ABSPATH . 'wp-admin/includes/misc.php');
include_once(plugin_dir_path(__FILE__) . 'updater.php');

// If this file is called directly, abort.
if ( ! defined('WPINC')) {
    die;
}

$updater = new Updater(__FILE__);
$updater->initialize();

add_action('rest_api_init', function () {
    register_rest_route('branch-helper/v1', '/check-connection', [
        'methods' => 'GET',
        'callback' => function () {
            return [
                'site_url' => site_url(),
            ];
        },
    ]);

    register_rest_route('branch-helper/v1', '/trigger-deployment', [
        'methods' => 'POST',
        'callback' => function () {
            $content = file_get_contents('php://input');
            $json = json_decode($content, true);

            $result = wp_remote_get("https://app.usebranch.co/api/internal/deployments/{$json['deployment']}?secret={$json['secret']}");

            $deployment = json_decode($result['body'], true);

            if ($deployment['type'] === 'INSTALL' && $deployment['package_type'] === 'PLUGIN') {
                installPlugin($deployment['url'], $deployment['directory']);
                $file = pluginFileFromDir($deployment['directory']);
                $plugins = get_option('branch_plugins', array());
                $plugins[$file] = $file;
                update_option('branch_plugins', $plugins);
            } else if ($deployment['type'] === 'UPDATE' && $deployment['package_type'] === 'PLUGIN') {
                $file = pluginFileFromDir($deployment['directory']);
                updatePlugin($deployment['url'], $deployment['directory'], $file);
            } else if ($deployment['type'] === 'INSTALL' && $deployment['package_type'] === 'THEME') {
                installTheme($deployment['url'], $deployment['directory']);
                $themes = get_option('branch_themes', array());
                $themes[$deployment['directory']] = $deployment['directory'];
                update_option('branch_themes', $themes);
            } else if ($deployment['type'] === 'UPDATE' && $deployment['package_type'] === 'THEME') {
                updateTheme($deployment['url'], $deployment['directory']);
            }
        },
    ]);
});

function installTheme($url, $slug) {
    addUpgraderSourceSelectionFilter($slug);

    $upgrader = new Theme_Upgrader;

    $upgrader->install($url);

    // Make sure we get out of maintenance mode
    $upgrader->maintenance_mode(false);
}

function updateTheme($url, $slug) {
    add_filter("pre_site_transient_update_themes", function ($transient) use ($url, $slug) {
        $options = array('package' => $url);
        $transient->response[$slug] = $options;

        return $transient;
    }, 10, 3);
    addUpgraderSourceSelectionFilter($slug);

    $upgrader = new Theme_Upgrader;

    $upgrader->upgrade($slug);

    // Make sure we get out of maintenance mode
    $upgrader->maintenance_mode(false);
}

function installPlugin($url, $slug) {
    addUpgraderSourceSelectionFilter($slug);

    $upgrader = new Plugin_Upgrader;

    $upgrader->install($url);

    // Make sure we get out of maintenance mode
    $upgrader->maintenance_mode(false);
}

function updatePlugin($url, $slug, $pluginFile) {
    $reActivatePlugin = is_plugin_active($pluginFile);
    $reActivatePluginNetworkWide = is_plugin_active_for_network($pluginFile);

    add_filter("pre_site_transient_update_plugins", function ($transient) use ($url, $pluginFile) {
        $options = array('package' => $url);

        $transient->response[$pluginFile] = (object) $options;

        return $transient;
    }, 10, 3);
    addUpgraderSourceSelectionFilter($slug);

    $upgrader = new Plugin_Upgrader;

    $upgrader->upgrade($pluginFile);

    if ($reActivatePlugin) {
        if ( ! is_plugin_active($pluginFile))
            activate_plugin($pluginFile, null, $network_wide = $reActivatePluginNetworkWide, $silent = true);
    }

    // Make sure we get out of maintenance mode
    $upgrader->maintenance_mode(false);
}

function addUpgraderSourceSelectionFilter($slug) {
    add_filter('upgrader_source_selection', function ($source, $remote_source, $upgrader) use ($slug) {
        $newSource = trailingslashit($remote_source) . trailingslashit($slug);

        global $wp_filesystem;

        if (! $wp_filesystem->move($source, $newSource, true)) {
            return new \WP_Error();
        }

        return $newSource;
    }, 10, 3);
}

add_filter('http_request_args', function($args, $url)
{
    if (0 !== strpos($url, 'https://api.wordpress.org/plugins/update-check')) {
        return $args;
    }

    $plugins = json_decode($args['body']['plugins'], true);

    $pluginsToHide = get_option('branch_plugins', array());
    $pluginsToHide[plugin_basename(__FILE__)] = plugin_basename(__FILE__);

    foreach ($pluginsToHide as $plugin) {
        unset($plugins['plugins'][$plugin]);
        unset($plugins['active'][array_search($plugin, $plugins['active'])]);
    }

    $args['body']['plugins'] = json_encode($plugins);

    return $args;
}, 5, 2);

add_filter('http_request_args', function($args, $url)
{
    if (0 !== strpos($url, 'https://api.wordpress.org/themes/update-check')) {
        return $args;
    }

    $themes = json_decode($args['body']['themes'], true);

    $themesToHide = get_option('branch_themes', array());

    foreach ($themesToHide as $theme) {
        unset($themes['themes'][$theme]);
        if (isset($themes['active']) and in_array($themes['active'], $themesToHide)) {
            unset($themes['active']);
        }
    }

    $args['body']['themes'] = json_encode($themes);

    return $args;
}, 5, 2);

function pluginFileFromDir($dir) {
    $plugins = get_plugins();

    $searchResult = array_values(array_filter(array_keys($plugins), function ($key) use ($dir) {
        return strpos($key, "{$dir}/") === 0;
    }));

    if (empty($searchResult)) {
        // Couldn't find plugin
        return;
    }

    return $searchResult[0];
}
