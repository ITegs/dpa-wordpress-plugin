<?php
/*
 * Plugin Name: dpa-presseportal-to-wordpress
 * Description: Fetches data from the presseportal API and creates a new post with the data
 * Version: 1.0
 * Author: Johannes Pahle
 * Author URI:   https://github.com/ITegs/dpa-wordpress-plugin
*/

//If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

if (!class_exists('presseportal_to_wordpress')) {
    class presseportal_to_wordpress
    {

        public function __construct()
        {
            add_action('init', array($this, 'setup'));

            register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        }

        public function setup()
        {
            $this->load_dependencies();
            $this->register_settings();

            add_action('update_option_dpa', array($this, 'update_settings'));
            add_filter('cron_schedules', array($this, 'dpa_schedule'));
            add_action('dpa_cron', array($this, 'fetchAndPost'));
        }

        public function deactivate()
        {
            // Remove cron
            $next_task = wp_next_scheduled('dpa_cron');
            wp_unschedule_event($next_task, 'dpa_cron');

            // Remove settings
            unregister_setting('dpa', 'dpa');
            delete_option('dpa');
            delete_option('dpa_stats');

            error_log('dpa-Plugin deactivated');
        }

        public function dpa_schedule($schedules)
        {
            $config = get_option('dpa');

            // Add new schedule
            $schedules['dpa-schedule'] = array(
                'interval' => $config['dpa_cron_time'] * 60,
                'display' => 'Every ' . $config['dpa_cron_time'] . ' minutes'
            );

            return $schedules;
        }

        public function update_settings()
        {
            error_log('dpa-Plugin settings updated');

            $config = get_option('dpa');

            // Reschedule cron
            $next_task = wp_next_scheduled('dpa_cron');
            if ($config['dpa_active'] === true) {
                if ($next_task) {
                    wp_schedule_event($next_task, 'dpa-schedule', 'dpa_cron');
                    error_log('Rescheduled dpa-cron');
                } else {
                    wp_schedule_event(time(), 'dpa-schedule', 'dpa_cron');
                    error_log('Added dpa-cron');
                }
            } elseif ($next_task) {
                error_log('Removed dpa-cron');
                wp_unschedule_event($next_task, 'dpa_cron');
            }
        }

        private function register_settings()
        {
            // Set default settings
            register_setting('dpa', 'dpa', array(
                'default' => array(
                    'dpa_endpoint' => null,
                    'dpa_key' => null,
                    'dpa_fetch_limit' => 10,
                    'dpa_cron_time' => 60,
                    'dpa_post_type' => 'publish',
                    'dpa_author' => 1,
                    'dpa_active' => false,
                ),
                'sanitize_callback' => array($this, 'validate_input')
            ));

            // Set default stats
            add_option('dpa_stats', array(
                'last_fetch' => '-',
            ));
        }

        public function validate_input($input)
        {
            // Check nonce
            if (
                !isset($_POST['_wpnonce']) ||
                !wp_verify_nonce($_POST['_wpnonce'], 'dpa-options')
            ) {
                add_settings_error('dpa', 'invalid_nonce', 'Formular-Validierung fehlgeschlagen', 'error');
            }

            // Sanitize input
            $output = array(
                'dpa_endpoint' => $input['dpa_endpoint'],
                'dpa_key' => $input['dpa_key'],
                'dpa_fetch_limit' => $input['dpa_fetch_limit'],
                'dpa_cron_time' => $input['dpa_cron_time'],
                'dpa_post_type' => $input['dpa_post_type'],
                'dpa_author' => $input['dpa_author'],
            );
            if (!empty($input['dpa_active']) && !empty($output['dpa_endpoint'])) {
                $output['dpa_active'] = $this->validate_active($input['dpa_active']);
            } else {
                $output['dpa_active'] = false;
            }

            return apply_filters('dpa', $output, $input);
        }

        private function validate_active($input)
        {
            return apply_filters('dpa_active', $input === 'on', $input);
        }

        private function load_dependencies()
        {
            require_once ABSPATH . '/wp-admin/includes/taxonomy.php';
            require_once plugin_dir_path(__FILE__) . '/includes/presseportal_admin.php';
            $this->admin_page = new PresseportalAdminPage();
        }

        function fetchAndPost()
        {
            // Get config
            $current_config = get_option('dpa');

            $endpoints = preg_split("/\r\n|\n|\r/", $current_config['dpa_endpoint']);

            foreach ($endpoints as $endpoint) {
                $this->fetchAndPostEndpoint($endpoint, $current_config);
            }
        }

        function fetchAndPostEndpoint($endpoint, $current_config)
        {
            // Fetch data from API
            $response = wp_remote_get($endpoint . '?api_key=' . $current_config['dpa_key'] . '&limit=' . $current_config['dpa_fetch_limit'] . '&lang=de');
            $data = json_decode(wp_remote_retrieve_body($response));

            $story_array = $data->content;

            // Check for duplicates
            $existing_posts = get_option('dpa_existing_id', array()); // retrieve the list of previously posted articles from the WordPress options table
            foreach ($story_array->story as $article) {
                $dpa_stats['last_fetch'] = date('H:i:s d.m.Y');
                update_option('dpa_stats', $dpa_stats);
                if (in_array($article->id, $existing_posts)) {
                    continue; // skip if the article has already been posted
                }

                // add endpoint category to keywords
                $cat = substr($endpoint, strrpos($endpoint, '/') + 1);
                $cat = ucfirst($cat);
                array_push($article->keywords->keyword, $cat);

                // remove text before ':' in the title (only police reports)
                if ($cat == 'Police') {
                    $article->title = substr($article->title, strpos($article->title, ':') + 1);
                    // only use text until 'Rückfragen bitte an:' in the body
                    $article->body = substr($article->body, 0, strpos($article->body, 'Rückfragen bitte an:'));
                }

                // add short link to content
                $article->body .= '<hr><a href="' . $article->short . '">' . $article->short . '</a>';

                // Create a new post
                $post_id = wp_insert_post(array(
                    'post_author' => $current_config['dpa_author'],
                    'post_date' => $article->published,
                    'post_title' => $article->title,
                    'post_content' => $article->body,
                    'tags_input' => $article->keywords->keyword,
                    'post_status' => $current_config['dpa_post_type'],
                ));

                // Add the categories
                if (isset($article->keywords->keyword)) {
                    $catArray = array();
                    foreach ($article->keywords->keyword as $category) {
                        $catId = (int) category_exists($category);
                        if ($catId == null) {
                            $catId = wp_create_category($category);
                        }

                        array_push($catArray, $catId);
                    }
                }
                wp_set_post_categories($post_id, $catArray);

                // Add the featured image
                if (isset($article->media->image[0]->url)) {
                    Generate_Featured_Image($article->media->image[0]->url, $post_id, $article->title);
                }

                // Add the article ID to the list of existing posts
                $existing_posts[] = $article->id;
            }

            // Update the list of existing posts in the WordPress options table
            update_option('dpa_existing_id', $existing_posts);
        }
    }

    function Generate_Featured_Image($image_url, $post_id, $article_title)
    {
        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents($image_url);
        $fileNameParts = explode('.', $image_url);
        $filename = $article_title . '.' . end($fileNameParts);
        if (wp_mkdir_p($upload_dir['path']))
            $file = $upload_dir['path'] . '/' . $filename;
        else
            $file = $upload_dir['basedir'] . '/' . $filename;
        file_put_contents($file, $image_data);

        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => $article_title,
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment($attachment, $file, $post_id);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        $res1 = wp_update_attachment_metadata($attach_id, $attach_data);
        $res2 = set_post_thumbnail($post_id, $attach_id);
    }

    $plugin = new presseportal_to_wordpress();
}
