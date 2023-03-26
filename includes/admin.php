<?php

class AdminPage
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_options_page'));
        add_action("admin_init", array($this, "add_options"));
    }

    public function add_options_page()
    {
        add_submenu_page(
            'options-general.php',
            'Presseportal Importer',
            'Presseportal Importer',
            'manage_options',
            'dpa',
            array(&$this, 'admin_page_html')
        );
    }

    public function add_options()
    {
        $cur_settings = get_option("dpa");

        add_settings_section(
            "dpa-section",
            "Presseportal Importer - Einstellungen",
            array(&$this, "admin_page_description"),
            "dpa"
        );

        add_settings_field(
            "dpa[dpa_endpoint]",
            "Endpunkt",
            array(&$this, "form_endpoint_html"),
            "dpa",
            "dpa-section",
            $cur_settings["dpa_endpoint"]
        );

        add_settings_field(
            "dpa[dpa_key]",
            "KEY",
            array(&$this, "form_key_html"),
            "dpa",
            "dpa-section",
            $cur_settings["dpa_key"]
        );

        add_settings_field(
            "dpa[dpa_fetch_limit]",
            "Anzahl der Artikel pro Abfrage",
            array(&$this, "form_fetch_limit_html"),
            "dpa",
            "dpa-section",
            $cur_settings["dpa_fetch_limit"]
        );

        add_settings_field(
            "dpa[dpa_cron_time]",
            "Abfragezyklus in Minuten",
            array(&$this, "form_cron_time_html"),
            "dpa",
            "dpa-section",
            $cur_settings["dpa_cron_time"]
        );

        add_settings_field(
            "dpa[dpa_post_type]",
            "Artikelstatus",
            array(&$this, "form_post_type_html"),
            "dpa",
            "dpa-section",
            $cur_settings["dpa_post_type"]
        );

        add_settings_field(
            "dpa[dpa_author]",
            "Autoren-ID",
            array(&$this, "form_author_html"),
            "dpa",
            "dpa-section",
            $cur_settings["dpa_author"]
        );

        add_settings_field(
            "dpa[dpa_active]",
            "Aktiviert",
            array(&$this, "form_active_html"),
            "dpa",
            "dpa-section",
            $cur_settings["dpa_active"]
        );
    }

    public function admin_page_description()
    {

        echo '<p>Die Einstellungen für den Presseportal Importer.</p>';
    }

    public function form_endpoint_html($cur_val)
    {
        echo '<input type="text" name="dpa[dpa_endpoint]" id="dpa_endpoint" value="' . $cur_val . '">';
    }

    public function form_key_html($cur_val)
    {
        echo '<input type="text" name="dpa[dpa_key]" id="dpa_key" value="' . $cur_val . '">';
    }

    public function form_fetch_limit_html($cur_val)
    {
        if (empty($cur_val)) {
            $cur_val = 10;
        }
        echo '<input type="number" min="1" max="360" name="dpa[dpa_fetch_limit]" id="dpa_fetch_limit" value="' . $cur_val . '">';
    }

    public function form_cron_time_html($cur_val)
    {
        if (empty($cur_val)) {
            $cur_val = 5;
        }
        echo '<input type="number" min="1" max="360" name="dpa[dpa_cron_time]" id="dpa_cron_time" value="' . $cur_val . '">';
    }

    public function form_post_type_html($cur_val)
    {
        $post_types = ['publish', 'draft', 'pending', 'private', 'trash'];
        echo '<select name="dpa[dpa_post_type]" id="dpa_post_type">';
        foreach ($post_types as $post_type) {
            echo '<option value="' . $post_type . '" ' . selected($post_type, $cur_val, false) . '>' . $post_type . '</option>';
        }
        echo '</select>';
    }

    public function form_author_html($cur_val)
    {
        if (empty($cur_val)) {
            $cur_val = 1;
        }
        echo '<input type="number" min="1" name="dpa[dpa_author]" id="dpa_author" value="' . $cur_val . '">';
    }

    public function form_active_html($cur_val)
    {
        echo '<input type="checkbox" name="dpa[dpa_active]" id="dpa_active" ' . checked(true, $cur_val, false) . ' />';
    }

    public function admin_page_html()
    {
        // check user capabilities
        $dpa_stats = get_option('dpa_stats')
?>
        <div class="wrap">
            <form method="post" action="options.php">
                <?php settings_fields("dpa") ?>
                <?php do_settings_sections('dpa') ?>
                <?php submit_button(); ?>
            </form>
            <p>Letzte Abfrage: <?php echo $dpa_stats['last_fetch'] ?></p>
            <hr>
            <p><a href="https://github.com/ITegs">Johannes Pahle | ITegs</a></p>
        </div>
<?php
    }
}


?>