<?php

// Credits: https://github.com/rayman813/smashing-updater-plugin

class Updater
{
    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $github_response;

    public function __construct($file)
    {
        $this->file = $file;

        add_action('admin_init', array($this, 'set_plugin_properties'));

        return $this;
    }

    public function set_plugin_properties()
    {
        $this->plugin = get_plugin_data($this->file);
        $this->basename = plugin_basename($this->file);
        $this->active = is_plugin_active($this->basename);
    }

    private function get_repository_info()
    {
        if (! is_null($this->github_response)) {
            return;
        }

        $request_uri = 'https://api.github.com/repos/usebranch/branch-helper/releases';

        $response = json_decode(wp_remote_retrieve_body(wp_remote_get($request_uri)), true);

        if (is_array($response)) {
            $response = current($response);
        }

        $this->github_response = $response;
    }

    public function initialize()
{
        add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
    }

    public function modify_transient($transient)
    {
        if (! property_exists($transient, 'checked')) {
            return;
        }

        if (! $checked = $transient->checked) {
            return;
        }

        $this->get_repository_info();

        $out_of_date = version_compare($this->github_response['tag_name'], $checked[ $this->basename ], 'gt');

        if ($out_of_date) {
            $new_files = $this->github_response['zipball_url'];

            $slug = current(explode('/', $this->basename));

            $plugin = array(
                'url' => $this->plugin["PluginURI"],
                'slug' => $slug,
                'package' => $new_files,
                'new_version' => $this->github_response['tag_name']
            );

            $transient->response[$this->basename] = (object) $plugin;
        }

        return $transient;
    }

    public function plugin_popup($result, $action, $args)
    {
        if (empty($args->slug)) {
            return $result;
        }

        if ($args->slug !== current(explode('/', $this->basename))) {
            return $result;
        }

        $this->get_repository_info();

        $plugin = array(
            'name' => $this->plugin["Name"],
            'slug' => $this->basename,
            'requires' => '4.9',
            'tested' => '5.1',
            'downloaded' => '14249',
            'added' => '2016-01-05',
            'version' => $this->github_response['tag_name'],
            'author' => $this->plugin["AuthorName"],
            'author_profile' => $this->plugin["AuthorURI"],
            'last_updated' => $this->github_response['published_at'],
            'homepage' => $this->plugin["PluginURI"],
            'short_description' => $this->plugin["Description"],
            'sections' => array(
                'Description' => $this->plugin["Description"],
                'Updates' => $this->github_response['body'],
            ),
            'download_link' => $this->github_response['zipball_url']
        );

        return (object) $plugin;
    }

    public function after_install($response, $hook_extra, $result)
    {
        global $wp_filesystem;

        $install_directory = plugin_dir_path($this->file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;

        if ($this->active) {
            activate_plugin($this->basename);
        }

        return $result;
    }
}
