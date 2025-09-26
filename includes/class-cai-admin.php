<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CAI_Admin {

    public function __construct(){
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
        add_action('add_meta_boxes', [$this, 'metaboxes']);
        add_action('save_post', [$this, 'save_post'], 10, 2);
    }

    public function menu(){
        add_menu_page(
            __('Content AI', 'content-architect-ai'),
            'Content AI',
            'manage_options',
            'cai',
            [$this, 'dashboard'],
            'dashicons-networking',
            58
        );
        add_submenu_page('cai', __('הגדרות AI', 'content-architect-ai'), __('הגדרות', 'content-architect-ai'), 'manage_options', 'cai-settings', [$this, 'settings_page']);
        add_submenu_page('cai', __('קיבוץ תוכן', 'content-architect-ai'), __('Clustering', 'content-architect-ai'), 'manage_options', 'cai-clustering', [$this, 'clustering_page']);
        add_submenu_page('cai', __('קניבליזציה', 'content-architect-ai'), __('Cannibalization', 'content-architect-ai'), 'manage_options', 'cai-cannibal', [$this, 'cannibal_page']);
    }

    public function settings(){
        register_setting('cai_settings_group', 'cai_settings');

        add_settings_section('cai_api', __('OpenAI API', 'content-architect-ai'), function(){
            echo '<p>'.esc_html__('חיבור ל-OpenAI לצורך עיבוד שפה, סיווג וקיבוץ.', 'content-architect-ai').'</p>';
        }, 'cai_settings');

        add_settings_field('openai_api_key', __('OpenAI API Key', 'content-architect-ai'), function(){
            $opt = get_option('cai_settings', []);
            printf('<input type="password" name="cai_settings[openai_api_key]" value="%s" class="regular-text" />', esc_attr($opt['openai_api_key'] ?? ''));
        }, 'cai_settings', 'cai_api');

        add_settings_field('chat_model', __('דגם לטקסט', 'content-architect-ai'), function(){
            $opt = get_option('cai_settings', []);
            $model = $opt['chat_model'] ?? 'gpt-4o-mini';
            echo '<input name="cai_settings[chat_model]" value="'.esc_attr($model).'" class="regular-text" />';
            echo '<p class="description">'.esc_html__('לדוגמה: gpt-4o-mini / gpt-4.1 / gpt-5-mini (לפי זמינות בחשבון).', 'content-architect-ai').'</p>';
        }, 'cai_settings', 'cai_api');

        add_settings_field('test_ai', __('בדיקת חיבור ל‑AI', 'content-architect-ai'), function(){ echo '<button class=\"button\" id=\"cai-test-connection\">בדוק חיבור</button> <span id="cai-test-result"></span>'; }, 'cai_settings', 'cai_api');
        add_settings_field('embedding_model', __('דגם Embeddings', 'content-architect-ai'), function(){
            $opt = get_option('cai_settings', []);
            $model = $opt['embedding_model'] ?? 'text-embedding-3-small';
            echo '<input name="cai_settings[embedding_model]" value="'.esc_attr($model).'" class="regular-text" />';
            echo '<p class="description">'.esc_html__('לדוגמה: text-embedding-3-small או text-embedding-3-large.', 'content-architect-ai').'</p>';
        }, 'cai_settings', 'cai_api');

        add_settings_section('cai_automation', __('אוטומציה', 'content-architect-ai'), null, 'cai_settings');
        foreach (['auto_index_on_save'=>'אינדוקס & קיבוץ אוטומטי בעת שמירה', 'auto_internal_links'=>'הוספת קישורים פנימיים אוטומטית', 'auto_schema'=>'סכמת JSON-LD אוטומטית', 'auto_breadcrumbs_jsonld'=>'BreadcrumbList אוטומטי'] as $key=>$label){
            add_settings_field($key, esc_html__($label, 'content-architect-ai'), function() use ($key){
                $opt = get_option('cai_settings', []);
                $checked = !empty($opt[$key]) ? 'checked' : '';
                echo '<label><input type="checkbox" name="cai_settings['.esc_attr($key).']" value="1" '.$checked.'> </label>';
            }, 'cai_settings', 'cai_automation');
        }

        add_settings_section('cai_home', __('עמוד בית דינמי', 'content-architect-ai'), null, 'cai_settings');
        add_settings_section('cai_brand', __('מיתוג וארגון', 'content-architect-ai'), null, 'cai_settings');
        add_settings_field('org_name', __('שם ארגון', 'content-architect-ai'), function(){
            $opt = get_option('cai_settings', []);
            echo '<input name="cai_settings[org_name]" value="'.esc_attr($opt['org_name'] ?? '').'" class="regular-text" />';
        }, 'cai_settings', 'cai_brand');
        add_settings_field('org_logo', __('לוגו (URL)', 'content-architect-ai'), function(){
            $opt = get_option('cai_settings', []);
            echo '<input name="cai_settings[org_logo]" value="'.esc_attr($opt['org_logo'] ?? '').'" class="regular-text" />';
        }, 'cai_settings', 'cai_brand');
        add_settings_field('home_sections', __('מספר מקטעים (אשכולות)', 'content-architect-ai'), function(){
            $opt = get_option('cai_settings', []);
            $v = (int)($opt['home_sections'] ?? 3);
            echo '<input type="number" name="cai_settings[home_sections]" value="'.esc_attr($v).'" min="1" max="10" />';
        }, 'cai_settings', 'cai_home');
        add_settings_field('posts_per_cluster', __('כמה פוסטים לכל מקטע', 'content-architect-ai'), function(){
            $opt = get_option('cai_settings', []);
            $v = (int)($opt['posts_per_cluster'] ?? 4);
            echo '<input type="number" name="cai_settings[posts_per_cluster]" value="'.esc_attr($v).'" min="1" max="12" />';
        }, 'cai_settings', 'cai_home');
    }

    public function dashboard(){
        echo '<div class="wrap"><h1>Content Architect AI</h1>';
        echo '<p>'.__('תוסף זה מוסיף ארכיטקטורת תוכן חכמה, קיבוץ, SEO ופיצ\'רים מבוססי AI.', 'content-architect-ai').'</p>';
        echo '<p><strong>'.__('קיצורי דרך:', 'content-architect-ai').'</strong> ';
        echo '<code>[cai_breadcrumbs]</code> | <code>[cai_related]</code> | <code>[cai_dynamic_home]</code></p>';
        echo '</div>';
    }

    public function settings_page(){
        echo '<div class="wrap"><h2>'.esc_html__('הגדרות', 'content-architect-ai').'</h2>';
        echo '<form method="post" action="options.php">';
        settings_fields('cai_settings_group');
        do_settings_sections('cai_settings');
        submit_button();
        echo '</form></div>';
    }

    public function clustering_page(){
        echo '<div class="wrap"><h2>'.esc_html__('קיבוץ מחדש (Re-Index)', 'content-architect-ai').'</h2>';
        echo '<p>'.esc_html__('נקה ובנה מחדש embeddings והקצאת אשכולות לכל התוכן.', 'content-architect-ai').'</p>';
        echo '<button class="button button-primary" id="cai-reindex">'.esc_html__('הפעל אינדוקס', 'content-architect-ai').'</button>';
        echo '<div id="cai-reindex-log" style="margin-top:10px;"></div>';
        echo '</div>';
    }

    public function cannibal_page(){
        echo '<div class="wrap"><h2>'.esc_html__('איתור קניבליזציה', 'content-architect-ai').'</h2>';
        echo '<div id="cai-cannibal-results"></div>';
        echo '</div>';
    }

    public function metaboxes(){
        add_meta_box('cai-seo', __('AI & SEO', 'content-architect-ai'), [$this,'metabox_seo'], ['post','page'], 'side', 'high');
    }

    public function metabox_seo($post){
        $kw = get_post_meta($post->ID, '_cai_target_keyword', true);
        $title = get_post_meta($post->ID, '_cai_meta_title', true);
        $desc = get_post_meta($post->ID, '_cai_meta_desc', true);
        wp_nonce_field('cai_save_meta', 'cai_nonce');
        echo '<p><label>'.__('מילת מפתח יעד', 'content-architect-ai').'<br>';
        echo '<input type="text" name="cai_target_keyword" value="'.esc_attr($kw).'" class="widefat"></label></p>';
        echo '<p><label>'.__('כותרת SEO', 'content-architect-ai').'<br>';
        echo '<input type="text" name="cai_meta_title" value="'.esc_attr($title).'" class="widefat" maxlength="65"></label></p>';
        echo '<p><label>'.__('תיאור מטא', 'content-architect-ai').'<br>';
        echo '<textarea name="cai_meta_desc" class="widefat" maxlength="160">'.esc_textarea($desc).'</textarea></label></p>';
        echo '<p><a href="#" class="button" id="cai-generate-meta" data-post="'.intval($post->ID).'">'.__('צור מטא עם AI', 'content-architect-ai').'</a></p>';echo '<p><label>'.__('Canonical URL', 'content-architect-ai').'<br><input type="url" name="cai_canonical" value="'.esc_attr(get_post_meta($post->ID,'_cai_canonical',true)).'" class="widefat"></label></p>';echo '<p><label><input type="checkbox" name="cai_noindex" value="1" '.(get_post_meta($post->ID,'_cai_noindex',true)?'checked':'').'> '.__('Noindex לדף זה','content-architect-ai').'</label></p>';echo '<hr><p><label>'.__('יצירת תוכן לפי נושא', 'content-architect-ai').'<br>';echo '<input type="text" id="cai-topic-input" class="widefat" placeholder="'.esc_attr__('לדוגמה: מדריך SEO למתחילים', 'content-architect-ai').'"></label></p>';echo '<p><a href="#" class="button button-primary" id="cai-generate-content">'.__('צור תוכן עם AI', 'content-architect-ai').'</a></p>';
    }

    public function save_post($post_id, $post){
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['cai_nonce']) || !wp_verify_nonce($_POST['cai_nonce'], 'cai_save_meta')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $kw = sanitize_text_field($_POST['cai_target_keyword'] ?? '');
        $title = sanitize_text_field($_POST['cai_meta_title'] ?? '');
        $desc = sanitize_textarea_field($_POST['cai_meta_desc'] ?? '');
        update_post_meta($post_id, '_cai_target_keyword', $kw);
        update_post_meta($post_id, '_cai_meta_title', $title);
        update_post_meta($post_id, '_cai_meta_desc', $desc);

        $opt = get_option('cai_settings', []);
        if (!empty($opt['auto_index_on_save'])){
            // index & cluster
            CAI_Clustering::index_post($post_id);
            CAI_Clustering::assign_cluster($post_id);
        }
        // cannibalization check
        CAI_Cannibalization::check_post($post_id);
    }
}
