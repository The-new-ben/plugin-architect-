<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CAI_Generator {

    const OPTION = 'cai_gen_settings';
    const CRON_HOOK = 'cai_cron_generate';

    public function __construct(){
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
        add_action('admin_post_cai_save_sources', [$this, 'save_sources']);
        add_action('wp_ajax_cai_analyze_site', [$this, 'ajax_analyze_site']);
        add_action('wp_ajax_cai_apply_arch', [$this, 'ajax_apply_arch']);
        add_action('wp_ajax_cai_generate_now', [$this, 'ajax_generate_now']);
        add_action('wp_ajax_cai_generate_from_topic', [$this, 'ajax_generate_from_topic']);
        add_action('wp_ajax_cai_toggle_autogen', [$this, 'ajax_toggle_autogen']);

        // Cron
        add_action(self::CRON_HOOK, [$this, 'cron_generate']);
        add_filter('cron_schedules', [$this, 'cron_schedules']);
        $opt = get_option(self::OPTION, []);
        if (!empty($opt['enable_cron']) && !wp_next_scheduled(self::CRON_HOOK)){
            wp_schedule_event(time() + 60, $opt['cron_interval'] ?? 'twicedaily', self::CRON_HOOK);
        }
    }

    public function menu(){
        add_submenu_page('cai', __('גנרציית תוכן', 'content-architect-ai'), __('Content Generation', 'content-architect-ai'), 'manage_options', 'cai-generation', [$this, 'page']);
    }

    public function settings(){
        register_setting('cai_gen_group', self::OPTION);
        add_settings_section('gen_main', __('הגדרות גנרציה', 'content-architect-ai'), null, self::OPTION);

        add_settings_field('language', __('שפת תוכן', 'content-architect-ai'), function(){
            $opt = get_option(self::OPTION, []);
            $v = $opt['language'] ?? 'he';
            echo '<input name="'.esc_attr(self::OPTION).'[language]" value="'.esc_attr($v).'" class="regular-text">';
        }, self::OPTION, 'gen_main');

        add_settings_field('status', __('סטטוס ברירת מחדל', 'content-architect-ai'), function(){
            $opt = get_option(self::OPTION, []);
            $v = $opt['status'] ?? 'draft';
            echo '<select name="'.esc_attr(self::OPTION).'[status]">';
            foreach (['draft'=>__('טיוטה'), 'publish'=>__('פרסום')] as $k=>$label){
                echo '<option value="'.$k.'" '.selected($v,$k,false).'>'.esc_html($label).'</option>';
            }
            echo '</select>';
        }, self::OPTION, 'gen_main');

        add_settings_field('posts_per_run', __('מקסימום פוסטים להרצה', 'content-architect-ai'), function(){
            $opt = get_option(self::OPTION, []);
            $v = intval($opt['posts_per_run'] ?? 5);
            echo '<input type="number" name="'.esc_attr(self::OPTION).'[posts_per_run]" value="'.esc_attr($v).'" min="1" max="50">';
        }, self::OPTION, 'gen_main');

        add_settings_section('gen_sources', __('מקורות לרעיונות (ניתוח רשת)', 'content-architect-ai'), function(){
            echo '<p>'.esc_html__('ניתן לספק RSS/Atom/Sitemap/URL של אתרים רלוונטיים. התוסף ישאב כותרות/תיאורים וינתח אותם ליצירת רעיונות פוסטים.', 'content-architect-ai').'</p>';
        }, self::OPTION);

        add_settings_field('sources', __('מקורות (אחד בכל שורה)', 'content-architect-ai'), function(){
            $opt = get_option(self::OPTION, []);
            $v = is_array($opt['sources'] ?? null) ? implode("\n", $opt['sources']) : '';
            echo '<textarea name="'.esc_attr(self::OPTION).'[sources]" rows="6" class="large-text">'.esc_textarea($v).'</textarea>';
        }, self::OPTION, 'gen_sources');

        add_settings_section('gen_cron', __('אוטומציה', 'content-architect-ai'), null, self::OPTION);
        add_settings_field('enable_cron', __('הפעל גנרציה מתמשכת', 'content-architect-ai'), function(){
            $opt = get_option(self::OPTION, []);
            $checked = !empty($opt['enable_cron']) ? 'checked' : '';
            echo '<label><input type="checkbox" name="'.esc_attr(self::OPTION).'[enable_cron]" value="1" '.$checked.'> '.__('הפעל', 'content-architect-ai').'</label>';
        }, self::OPTION, 'gen_cron');

        add_settings_field('cron_interval', __('תדירות', 'content-architect-ai'), function(){
            $opt = get_option(self::OPTION, []);
            $v = $opt['cron_interval'] ?? 'twicedaily';
            echo '<select name="'.esc_attr(self::OPTION).'[cron_interval]">';
            foreach (['hourly'=>__('כל שעה'), 'twicedaily'=>__('פעמיים ביום'), 'daily'=>__('פעם ביום')] as $k=>$label){
                echo '<option value="'.$k.'" '.selected($v,$k,false).'>'.esc_html($label).'</option>';
            }
            echo '</select>';
        }, self::OPTION, 'gen_cron');
    }

    public function cron_schedules($schedules){
        if (!isset($schedules['twicedaily'])){
            $schedules['twicedaily'] = ['interval'=>12*3600, 'display'=>__('Twice Daily')];
        }
        return $schedules;
    }

    public function page(){
        $opt = get_option(self::OPTION, []);
        echo '<div class="wrap"><h1>'.__('Content Generation', 'content-architect-ai').'</h1>';

        echo '<form method="post" action="options.php">';
        settings_fields('cai_gen_group');
        do_settings_sections(self::OPTION);
        submit_button(__('שמור הגדרות', 'content-architect-ai'));
        echo '</form>';

        echo '<hr><h2>'.__('אשף מהיר: ניתוח אתר ויצירת ארכיטקטורה', 'content-architect-ai').'</h2>';
        echo '<p><button class="button button-primary" id="cai-analyze-site">'.__('נתח אתר והצע אשכולות', 'content-architect-ai').'</button> ';
        echo '<button class="button" id="cai-apply-arch">'.__('יישם ארכיטקטורה והכן קטגוריות', 'content-architect-ai').'</button></p>';
        echo '<textarea id="cai-plan" class="large-text code" rows="12" placeholder="'.esc_attr__('התוכנית תופיע כאן בפורמט JSON', 'content-architect-ai').'"></textarea>';

        echo '<h2>'.__('גנרציית תוכן מיידית', 'content-architect-ai').'</h2>';
        echo '<p><label>'.__('פוסטים פר אשכול', 'content-architect-ai').' <input type="number" id="cai-ppc" value="3" min="1" max="10"></label> ';
        echo '<button class="button button-primary" id="cai-generate-now">'.__('צור עכשיו', 'content-architect-ai').'</button></p>';
        echo '<div id="cai-generate-log" style="max-height:260px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:8px"></div>';

        echo '<h2>'.__('גנרציה לפי נושא (כפתור גנרציה)', 'content-architect-ai').'</h2>';
        echo '<p><input type="text" id="cai-topic" class="regular-text" placeholder="'.esc_attr__('נושא/כיוון לפוסט', 'content-architect-ai').'"> ';
        echo '<button class="button" id="cai-generate-topic">'.__('יצירת פוסט יחיד', 'content-architect-ai').'</button></p>';
        echo '</div>';
    }

    public function ajax_analyze_site(){
        check_ajax_referer('cai-admin','nonce');
if (!current_user_can('manage_options')) wp_send_json_error('forbidden', 403);
        $site = [
            'name'        => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'language'    => get_bloginfo('language'),
            'home_url'    => home_url('/'),
            'taxonomies'  => array_values(get_taxonomies(['public'=>true, 'show_ui'=>true], 'names')),
            'categories'  => wp_list_pluck(get_terms(['taxonomy'=>'category','hide_empty'=>false]), 'name'),
            'menus'       => $this->get_menus_snapshot(),
        ];
        $prompt = 'אתה אדריכל מידע ו-SEO. נתח את נתוני האתר הבאים והצע ארכיטקטורת תוכן תואמת שוק בעברית: '
                . wp_json_encode($site, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
                . ' החזר JSON בלבד במבנה: { "clusters":[{"name":"","description":"","keywords":[""],"ideas":[{"title":"","slug":"","target_keyword":"","summary":""}]}], "categories":["",""] }';
        $json = CAI_AI::chat($prompt, 'Return compact JSON only. Language: Hebrew.', 600);
        if (!$json) wp_send_json_error('no ai');
        set_transient('cai_architecture_plan', $json, 12*HOUR_IN_SECONDS);
        wp_send_json_success(['plan'=>$json]);
    }

    private function get_menus_snapshot(){
        $out = [];
        $menus = wp_get_nav_menus();
        foreach ($menus as $menu){
            $items = wp_get_nav_menu_items($menu->term_id);
            $out[] = [
                'menu' => $menu->name,
                'items'=> array_map(function($it){ return ['title'=>$it->title, 'url'=>$it->url]; }, $items ?? [])
            ];
        }
        return $out;
    }

    public function ajax_apply_arch(){
        check_ajax_referer('cai-admin','nonce');
if (!current_user_can('manage_options')) wp_send_json_error('forbidden', 403);
        $plan_json = isset($_POST['plan']) ? wp_unslash($_POST['plan']) : get_transient('cai_architecture_plan');
        if (!$plan_json) wp_send_json_error('missing plan');
        $plan = json_decode($plan_json, true);
        if (!is_array($plan) || empty($plan['clusters'])) wp_send_json_error('bad plan');

        $created = ['clusters'=>[], 'categories'=>[]];
        foreach ($plan['clusters'] as $c){
            $name = sanitize_text_field($c['name'] ?? '');
            if (!$name) continue;
            $term = term_exists($name, 'topic_cluster');
            if (!$term || is_wp_error($term)) $term = wp_insert_term($name, 'topic_cluster');
            if (!is_wp_error($term)) $created['clusters'][] = $name;
            // create category too
            $cat = term_exists($name, 'category');
            if (!$cat || is_wp_error($cat)) $cat = wp_insert_term($name, 'category');
            if (!is_wp_error($cat)) $created['categories'][] = $name;
        }

        if (!empty($plan['categories']) && is_array($plan['categories'])){
            foreach ($plan['categories'] as $name){
                $name = sanitize_text_field($name);
                if (!$name) continue;
                if (!term_exists($name, 'category')){
                    $cat = wp_insert_term($name, 'category');
                    if (!is_wp_error($cat)) $created['categories'][] = $name;
                }
            }
        }

        update_option('cai_architecture_plan', $plan);
        wp_send_json_success(['created'=>$created]);
    }

    public function ajax_generate_now(){
        check_ajax_referer('cai-admin','nonce');
if (!current_user_can('manage_options')) wp_send_json_error('forbidden', 403);
        $plan = get_option('cai_architecture_plan');
        if (!$plan) {
            $plan_json = get_transient('cai_architecture_plan');
            if ($plan_json) $plan = json_decode($plan_json, true);
        }
        if (!$plan) wp_send_json_error('no plan');

        $ppc = max(1, intval($_POST['ppc'] ?? 3));
        $count = 0; $post_ids = [];
        foreach ($plan['clusters'] as $cluster){
            $cluster_name = sanitize_text_field($cluster['name'] ?? '');
            if (!$cluster_name) continue;
            $ideas = array_slice($cluster['ideas'] ?? [], 0, $ppc);
            foreach ($ideas as $idea){
                $pid = $this->generate_single_post([
                    'title' => $idea['title'] ?? '',
                    'summary' => $idea['summary'] ?? '',
                    'target_keyword' => $idea['target_keyword'] ?? '',
                    'cluster' => $cluster_name,
                ]);
                if ($pid) { $post_ids[] = $pid; $count++; }
            }
        }
        wp_send_json_success(['created'=>$count, 'ids'=>$post_ids]);
    }

    public function ajax_generate_from_topic(){
        check_ajax_referer('cai-admin','nonce');
if (!current_user_can('edit_posts')) wp_send_json_error('forbidden', 403);
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $cluster = sanitize_text_field($_POST['cluster'] ?? '');
        if (!$topic) wp_send_json_error('missing topic');
        $pid = $this->generate_single_post(['title'=>$topic, 'cluster'=>$cluster]);
        if ($pid) wp_send_json_success(['post_id'=>$pid, 'edit_link'=>get_edit_post_link($pid, '')]);
        wp_send_json_error('failed');
    }

    public function ajax_toggle_autogen(){
        check_ajax_referer('cai-admin','nonce');
if (!current_user_can('manage_options')) wp_send_json_error('forbidden', 403);
        $opt = get_option(self::OPTION, []);
        $enable = !empty($_POST['enable']) ? 1 : 0;
        $interval = sanitize_text_field($_POST['interval'] ?? 'twicedaily');
        $opt['enable_cron'] = $enable;
        $opt['cron_interval'] = $interval;
        update_option(self::OPTION, $opt);
        wp_clear_scheduled_hook(self::CRON_HOOK);
        if ($enable){
            wp_schedule_event(time()+60, $interval, self::CRON_HOOK);
        }
        wp_send_json_success(['enabled'=>$enable, 'interval'=>$interval]);
    }

    private function generate_single_post($args){
        $opt = get_option(self::OPTION, []);
        $status = in_array($opt['status'] ?? 'draft', ['draft','publish']) ? $opt['status'] : 'draft';
        $language = $opt['language'] ?? 'he';

        $topic = $args['title'] ?: 'מאמר חדש';
        $cluster = $args['cluster'] ?? '';
        $summary = $args['summary'] ?? '';
        $target_kw = $args['target_keyword'] ?? '';

        $site = [
            'name'=> get_bloginfo('name'),
            'description'=> get_bloginfo('description'),
            'url'=> home_url('/')
        ];

        $prompt = 'כתוב פוסט בלוג איכותי ומקצועי בעברית על הנושא: "'.$topic.'".'
                .' שמור על מבנה SEO: כותרת H1, פתיח קצר, כותרות H2/H3 עם פסקאות מסודרות, רשימות תבליטים, וקישורי קריאה להמשך (טקסט בלבד).'
                .' הוסף בסוף FAQ של 4-6 שאלות ותשובות קצרות.'
                .' החזר JSON בלבד עם המפתחות: title, slug, target_keyword, excerpt, meta_title, meta_desc, faq_json (JSON-LD של FAQPage), content_html.'
                .' פרטים על האתר: '.wp_json_encode($site, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
                .($summary ? ' רמזים/סיכום: '.sanitize_text_field($summary) : '')
                .($target_kw ? ' מילת יעד מועדפת: '.sanitize_text_field($target_kw) : '')
                .' כתוב בעברית תקינה, טון מקצועי, בלי סלנג, ושמור על אורך 800-1400 מילים.';

        $json = CAI_AI::chat($prompt, 'Return valid JSON only. Language: Hebrew.', 1200);
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['content_html'])) return 0;

        $title = sanitize_text_field($data['title'] ?: $topic);
        $content = wp_kses_post($data['content_html']);
        $slug = sanitize_title($data['slug'] ?? $title);
        $excerpt = sanitize_text_field($data['excerpt'] ?? '');
        $kw = sanitize_text_field($data['target_keyword'] ?? $target_kw);

        $postarr = [
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status'  => $status,
            'post_type'    => 'post',
        ];
        $pid = wp_insert_post($postarr, true);
        if (is_wp_error($pid)) return 0;

        if (!empty($kw)) update_post_meta($pid, '_cai_target_keyword', $kw);
        if (!empty($data['meta_title'])) update_post_meta($pid, '_cai_meta_title', sanitize_text_field($data['meta_title']));
        if (!empty($data['meta_desc'])) update_post_meta($pid, '_cai_meta_desc', sanitize_textarea_field($data['meta_desc']));
        if (!empty($data['faq_json'])) update_post_meta($pid, '_cai_faq_json', wp_kses_post($data['faq_json']));

        // Assign cluster/category
        if ($cluster){
            $t = term_exists($cluster, 'topic_cluster');
            if (!$t || is_wp_error($t)) $t = wp_insert_term($cluster, 'topic_cluster');
            if (!is_wp_error($t)) wp_set_object_terms($pid, intval($t['term_id'] ?? $t), 'topic_cluster', false);

            $cat = term_exists($cluster, 'category');
            if (!$cat || is_wp_error($cat)) $cat = wp_insert_term($cluster, 'category');
            if (!is_wp_error($cat)) wp_set_post_terms($pid, [intval($cat['term_id'] ?? $cat)], 'category', false);
        }

        // Index & link & schema via existing modules
        CAI_Clustering::index_post($pid);
        CAI_Clustering::assign_cluster($pid);
        CAI_Cannibalization::check_post($pid);

        return $pid;
    }

    public function cron_generate(){
        $opt = get_option(self::OPTION, []);
        $max = intval($opt['posts_per_run'] ?? 3);
        $topics = $this->discover_topics_from_sources();
        if (empty($topics)) return;

        $created = 0;
        foreach ($topics as $t){
            if ($created >= $max) break;
            $pid = $this->generate_single_post([
                'title'=>$t['title'],
                'summary'=>$t['summary'] ?? '',
                'cluster'=>$t['cluster'] ?? ''
            ]);
            if ($pid) $created++;
        }
    }

    private function discover_topics_from_sources(){
        $opt = get_option(self::OPTION, []);
        $sources = $opt['sources'] ?? [];
        if (empty($sources)) return [];

        $items = [];
        require_once( ABSPATH . WPINC . '/feed.php' );
        foreach ($sources as $url){
            try {
                $feed = fetch_feed(esc_url_raw($url));
                if (is_wp_error($feed)) continue;
                foreach ($feed->get_items(0, 8) as $item){
                    $items[] = [
                        'title' => wp_strip_all_tags($item->get_title()),
                        'summary' => wp_strip_all_tags($item->get_description() ?: $item->get_content())
                    ];
                }
            } catch (\Throwable $e){ continue; }
        }
        if (empty($items)) return [];
        $prompt = 'קבל רשימת כותרות/תקצירים והחזר עד 10 רעיונות לפוסטים ייחודיים בעברית המתאימים לאתר, כולל שיוך לאשכול מתאים אם אפשר. '
                .'פורמט JSON בלבד: [{"title":"","summary":"","cluster":""}]'
                .' נתונים: ' . wp_json_encode($items, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $json = CAI_AI::chat($prompt, 'Return JSON array only. Language: Hebrew.', 800);
        $arr = json_decode($json, true);
        return is_array($arr) ? $arr : [];
    }
}
