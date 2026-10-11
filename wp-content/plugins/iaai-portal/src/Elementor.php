<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;

final class Elementor {
    const VIEWS = ['auth', 'dashboard', 'profile', 'reports', 'upload', 'treatment', 'enquiries', 'sharing', 'settings', 'guidance', 'navigation', 'contact', 'calculators', 'termplanner', 'posts', 'staff'];
    public static function boot(): void {
        Kit::boot();
        add_filter('elementor/frontend/print_google_fonts', '__return_false');
        add_action('elementor/widgets/register', static function ($manager) {
            require_once IAAI_PATH . 'src/ElementorWidget.php';
            foreach (array_diff(self::VIEWS, ['staff']) as $view) { $class = __NAMESPACE__ . '\\' . ucfirst($view) . 'Widget'; $manager->register(new $class()); }
        });
    }
    /** Public journal: only published posts; drafts/private records never enter this query. */
    public static function posts(array $settings): string {
        $hi = Core::locale() === 'hi';
        $count = max(1, min(24, (int) ($settings['posts_per_page'] ?? 6)));
        $pagination = ($settings['show_pagination'] ?? '') === 'yes';
        $raw_page = $_GET['iaai_blog_page'] ?? 1;
        $page = $pagination && is_scalar($raw_page) ? max(1, min(10000, absint($raw_page))) : 1;
        $args = ['post_type' => 'post', 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => $count,
            'paged' => $page, 'ignore_sticky_posts' => true, 'orderby' => 'date', 'order' => 'DESC',
            'no_found_rows' => !$pagination];
        $category = absint($settings['category_id'] ?? 0);
        if ($category) { $args['cat'] = $category; }
        $query = new \WP_Query($args);
        if (!$query->posts) {
            $empty = ($settings['empty_message'] ?? '') ?: ($hi ? 'अभी कोई ब्लॉग प्रकाशित नहीं हुआ है।' : 'No articles published yet.');
            return '<p class="iaai-journal-empty">' . esc_html($empty) . '</p>';
        }
        $html = '<div class="iaai-journal-grid">';
        foreach ($query->posts as $post) {
            $link = get_permalink($post);
            $title = get_the_title($post);
            $image = get_the_post_thumbnail($post, 'large', ['loading' => 'lazy']);
            if (!$image) { $image = '<img src="' . esc_url(IAAI_URL . 'assets/images/planning.jpg') . '" alt="" loading="lazy" width="1100" height="734">'; }
            $excerpt = wp_trim_words(wp_strip_all_tags(get_the_excerpt($post)), 24);
            $html .= '<article class="iaai-journal-card"><a class="iaai-journal-image" href="' . esc_url($link) . '" aria-label="' . esc_attr($title) . '">' . $image . '</a><div class="iaai-journal-body">';
            $html .= '<time datetime="' . esc_attr(get_the_date('c', $post)) . '">' . esc_html(get_the_date('', $post)) . '</time>';
            $html .= '<h3><a href="' . esc_url($link) . '">' . esc_html($title) . '</a></h3><p>' . esc_html($excerpt) . '</p>';
            $html .= '<a class="iaai-journal-more" href="' . esc_url($link) . '">' . esc_html(($settings['read_more'] ?? '') ?: ($hi ? 'लेख पढ़ें →' : 'Read article →')) . '</a></div></article>';
        }
        $html .= '</div>';
        if ($pagination && $query->max_num_pages > 1) {
            $links = paginate_links(['base' => add_query_arg('iaai_blog_page', '%#%', get_permalink()),
                'format' => '', 'current' => $page, 'total' => $query->max_num_pages,
                'type' => 'list', 'prev_text' => $hi ? '← पिछले' : '← Previous', 'next_text' => $hi ? 'अगले →' : 'Next →']);
            $html .= '<nav class="iaai-journal-pagination" aria-label="' . ($hi ? 'ब्लॉग पृष्ठ' : 'Blog pages') . '">' . wp_kses_post($links) . '</nav>';
        }
        return $html;
    }
    public static function termplanner(array $settings): string {
        $hi = Core::locale() === 'hi';
        $l = static fn($en, $hiText) => $hi ? $hiText : $en;
        $uid = wp_unique_id('iaai-term-');
        ob_start(); ?>
        <form class="iaai-term-planner" data-iaai-term-planner>
          <header><span class="iaai-planner-badge"><?php echo esc_html($l('PLAN YOUR PROTECTION', 'अपनी सुरक्षा की योजना')); ?></span><h3><?php echo esc_html(($settings['heading'] ?? '') ?: $l('Term cover planner', 'टर्म कवर प्लानर')); ?></h3><p><?php echo esc_html(($settings['intro'] ?? '') ?: $l('A clear starting point for your family’s future.', 'परिवार के भविष्य की बेहतर तैयारी की शुरुआत।')); ?></p></header>
          <div class="iaai-planner-options">
            <fieldset><legend><?php echo esc_html($l('Your gender', 'आपका लिंग')); ?></legend><div class="iaai-choice-row">
            <?php foreach (['Male'=>'पुरुष','Female'=>'महिला'] as $value=>$label): ?><label><input type="radio" name="gender" value="<?php echo esc_attr($value); ?>" required><span><?php echo esc_html($l($value,$label)); ?></span></label><?php endforeach; ?>
            </div></fieldset>
            <fieldset><legend><?php echo esc_html($l('Do you smoke / use tobacco?', 'क्या आप धूम्रपान / तंबाकू उपयोग करते हैं?')); ?></legend><div class="iaai-choice-row">
            <?php foreach (['No'=>'नहीं','Yes'=>'हां'] as $value=>$label): ?><label><input type="radio" name="tobacco" value="<?php echo esc_attr($value); ?>" required><span><?php echo esc_html($l($value,$label)); ?></span></label><?php endforeach; ?>
            </div></fieldset>
          </div>
          <label class="iaai-age-label" for="<?php echo esc_attr($uid); ?>"><?php echo esc_html($l('How old are you?', 'आपकी उम्र कितनी है?')); ?> <output data-age-value>30</output></label>
          <input id="<?php echo esc_attr($uid); ?>" type="range" name="age" min="18" max="60" value="30" step="1" aria-label="<?php echo esc_attr($l('Age in years','उम्र वर्ष में')); ?>">
          <div class="iaai-age-scale" aria-hidden="true"><span>18</span><span>25</span><span>35</span><span>45</span><span>55</span><span>60</span></div>
          <fieldset class="iaai-cover-field"><legend><?php echo esc_html($l('I would like to explore life cover of', 'मैं इतना जीवन कवर समझना चाहता/चाहती हूं')); ?></legend><div class="iaai-choice-row iaai-cover-choices">
          <?php foreach (['5000000'=>'₹50 lakh','10000000'=>'₹1 crore','20000000'=>'₹2 crore','30000000'=>'₹3 crore','50000000'=>'₹5 crore'] as $value=>$label): ?><label><input type="radio" name="cover" value="<?php echo esc_attr($value); ?>" <?php checked($value,'10000000'); ?> required><span><?php echo esc_html($label); ?></span></label><?php endforeach; ?>
          </div></fieldset>
          <div class="iaai-planner-summary"><p><?php echo esc_html($l('Selected life cover', 'चुना गया जीवन कवर')); ?></p><output data-cover-value>₹1,00,00,000</output><p><?php echo esc_html($l('Premium is confirmed by the insurer after assessment. This is a cover selector, not a premium quote.', 'प्रीमियम कंपनी की जांच के बाद तय होगा। यह कवर चयन है, प्रीमियम quote नहीं।')); ?></p>
          <button type="submit"><?php echo esc_html(($settings['continue_label'] ?? '') ?: $l('Continue →','आगे बढ़ें →')); ?></button>
          <div class="iaai-planner-next" hidden tabindex="-1"><p role="status" data-planner-result></p><a class="iaai-planner-whatsapp" href="https://wa.me/919179826317" target="_blank" rel="noopener"><?php echo esc_html($l('Open WhatsApp with these details ↗','इन विवरणों के साथ WhatsApp खोलें ↗')); ?></a><p><?php echo esc_html($l('WhatsApp opens with your selections. Review the message before sending it to our team.', 'WhatsApp में आपके चुने विवरण खुलेंगे। टीम को भेजने से पहले संदेश जांचें।')); ?></p></div>
          </div>
        </form>
        <?php return ob_get_clean();
    }
    public static function calculators(array $settings): string {
        $hi = Core::locale() === 'hi';
        $specs = [
            'tax' => ['Income tax', 'आयकर', [['Annual taxable income ₹', 'वार्षिक कर योग्य आय ₹', 1200000]], 'FY 2025–26 / AY 2026–27, new regime. Resident individual, ordinary-rate income only, up to ₹50 lakh. Enter income after eligible deductions. Includes rebate, marginal relief and 4% cess. Excludes special-rate income and surcharge.', 'Estimated annual tax'],
            'term' => ['Term protection needs', 'टर्म सुरक्षा की जरूरत', [['Annual family expenses ₹','परिवार का वार्षिक खर्च ₹',600000],['Years to support','सहायता के वर्ष',20],['Debts ₹','कर्ज ₹',1000000],['Future goals ₹','भविष्य के लक्ष्य ₹',1500000],['Available assets + existing cover ₹','उपलब्ध संपत्ति और मौजूदा कवर ₹',2000000]], 'Simple needs estimate: expenses × years + debts + goals − assets and existing cover. Does not account for inflation or investment growth. Not a premium quote.', 'Estimated additional protection'],
            'ulip' => ['ULIP illustration', 'ULIP उदाहरण', [['Annual contribution ₹','वार्षिक योगदान ₹',100000],['Whole years','पूर्ण वर्ष',10],['Assumed gross growth %','अनुमानित वृद्धि %',8],['Assumed annual charge drag %','अनुमानित वार्षिक शुल्क प्रभाव %',2]], 'Contributions at the start of each year. Uses gross growth minus assumed annual charge drag. Actual mortality, allocation, fund, tax and other charges vary. Market-linked returns are not guaranteed; not an insurer benefit illustration.', 'Illustrative future value'],
            'salary' => ['Take-home salary', 'हाथ में आने वाला वेतन', [['Monthly gross salary ₹','मासिक सकल वेतन ₹',80000],['Monthly employee PF ₹','मासिक कर्मचारी PF ₹',1800],['Monthly TDS ₹','मासिक TDS ₹',4000],['Other monthly deductions ₹','अन्य मासिक कटौती ₹',200]], 'Monthly gross minus the deductions you enter. Enter gross cash salary, excluding employer PF, gratuity and non-cash CTC components. Does not automatically determine TDS or statutory deductions.', 'Estimated monthly take-home'],
        ];
        $html = '<div class="iaai-calculator-grid">';
        foreach ($specs as $kind => $spec) {
            $title = $settings[$kind . '_title'] ?? ''; $title = $title ?: ($hi ? $spec[1] : $spec[0]);
            $html .= '<form class="iaai-calculator" data-iaai-calculator="' . esc_attr($kind) . '"><h3>' . esc_html($title) . '</h3>';
            foreach ($spec[2] as $i => $field) { $field_label = $settings[$kind . '_field_' . $i] ?? ''; $field_label = $field_label ?: ($hi ? $field[1] : $field[0]); $html .= '<label>' . esc_html($field_label) . '<input type="number" min="0" step="any" required value="' . esc_attr($field[2]) . '"></label>'; }
            $note = $settings[$kind . '_note'] ?? ''; $note = $note ?: $spec[3];
            $html .= '<button type="submit">' . esc_html($settings['calculate_label'] ?? ($hi ? 'गणना करें' : 'Calculate')) . '</button><p>' . esc_html(($settings[$kind . '_result'] ?? '') ?: $spec[4]) . '</p><output aria-live="polite">—</output><p class="iaai-calc-note">' . esc_html($note) . '</p></form>';
        }
        return $html . '</div>';
    }
    public static function shell(string $view, array $settings): string {
        if (!in_array($view, self::VIEWS, true)) { return ''; }
        if ($view === 'calculators') { return self::calculators($settings); }
        if ($view === 'termplanner') { return self::termplanner($settings); }
        if ($view === 'posts') { return self::posts($settings); }
        if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            return '<div class="iaai-app"><span class="iaai-eyebrow">Synthetic editor preview</span><h3>' . esc_html($settings['heading'] ?? ucwords($view)) . '</h3><p>Sample layout only. No personal records are loaded in Elementor.</p><div class="iaai-empty">Use the Style tab to edit colors, type, layout and spacing.</div></div>';
        }
        if ($view === 'navigation') {
            $hi = Core::locale() === 'hi';
            $key = get_post_meta(get_queried_object_id(), '_iaai_key', true);
            global $wpdb;
            $target = $wpdb->get_var($wpdb->prepare('SELECT post_id FROM ' . Core::table('managed_templates') . ' WHERE deployment_key=%s AND locale=%s', $key ?: 'home', $hi ? 'en' : 'hi'));
            $switch = $target ? get_permalink($target) : home_url($hi ? '/' : '/hi/');
            $row = static function (string $href, string $label, string $icon): string {
                return '<a class="iaai-nav-row" href="' . esc_url($href) . '"><span aria-hidden="true">' . $icon . '</span>' . esc_html($label) . '</a>';
            };
            $language = '<details name="iaai-header" class="iaai-nav-dropdown iaai-language"><summary aria-label="' . ($hi ? 'भाषा चुनें' : 'Choose language') . '">' . ($hi ? 'हि' : 'En') . '</summary><div class="iaai-nav-popover">';
            foreach (['en' => 'English', 'hi' => 'हिन्दी'] as $code => $label) {
                $current = $code === ($hi ? 'hi' : 'en');
                $language .= $current ? '<span class="iaai-nav-row" aria-current="true"><span aria-hidden="true">✓</span>' . $label . '</span>' : '<a class="iaai-nav-row" href="' . esc_url($switch) . '" data-iaai-locale="' . $code . '"><span aria-hidden="true">◎</span>' . $label . '</a>';
            }
            $language .= '</div></details>';
            $account = '<details name="iaai-header" class="iaai-nav-dropdown"><summary>' . (is_user_logged_in() ? ($hi ? 'अकाउंट' : 'Account') : ($hi ? 'लॉग इन' : 'Login')) . '</summary><div class="iaai-nav-popover">';
            if (is_user_logged_in()) {
                foreach ([['my-dashboard', 'Dashboard', 'डैशबोर्ड', '▦'], ['my-profile', 'My Profile', 'मेरी प्रोफ़ाइल', '♙'], ['my-reports', 'My Reports', 'मेरी रिपोर्ट', '▤'], ['account-settings', 'Account Settings', 'अकाउंट सेटिंग्स', '⚙']] as $item) {
                    $account .= $row(home_url('/' . $item[0] . '/'), $hi ? $item[2] : $item[1], $item[3]);
                }
            } else {
                $account .= '<button type="button" class="iaai-nav-row" data-iaai-auth><span aria-hidden="true">♙</span>' . ($hi ? 'ग्राहक लॉग इन' : 'Customer Login') . '</button>';
                $account .= $row(admin_url(), $hi ? 'टीम लॉग इन' : 'Team Login', '▣');
            }
            $account .= '</div></details>';
            $css = '<style>
.iaai-account-control{display:flex;gap:12px;align-items:center;justify-content:flex-end;font-family:inherit;color:#242124}
.iaai-nav-dropdown{position:relative;font-size:16px;line-height:1.4;font-weight:400}
.iaai-nav-dropdown>summary{display:flex;gap:17px;align-items:center;justify-content:center;list-style:none;cursor:pointer;border:1px solid #bbb8bd;border-radius:999px;background:#fff;color:#242124;padding:11px 21px;min-height:46px;font-family:inherit;font-weight:500;font-size:16px;line-height:1.4;white-space:nowrap}
.iaai-nav-dropdown>summary::-webkit-details-marker{display:none}
.iaai-nav-dropdown>summary:after{content:"";width:9px;height:9px;border-right:2px solid;border-bottom:2px solid;transform:rotate(45deg);margin-top:-5px}
.iaai-nav-dropdown[open]>summary:after{transform:rotate(225deg);margin-top:5px}
.iaai-nav-popover{position:absolute;top:calc(100% + 10px);right:0;z-index:9999;display:flex!important;flex-direction:column;gap:0!important;width:270px;max-width:calc(100vw - 32px);padding:0!important;border:1px solid #dedce0;border-radius:14px;background:#fff;box-shadow:0 10px 30px #24133f18;overflow:hidden;text-align:left}
.iaai-language .iaai-nav-popover{left:0;right:auto;width:180px}
.iaai-account-control .iaai-nav-row{box-sizing:border-box;display:flex;align-items:center;gap:17px;width:100%;padding:19px 22px;margin:0;border:0;border-bottom:1px solid #e4e2e5;background:#fff;color:#242124!important;font-family:inherit;font-weight:400;font-size:18px;line-height:1.5;text-decoration:none!important;text-align:left;white-space:normal;cursor:pointer}
.iaai-account-control .iaai-nav-row:last-child{border-bottom:0}
.iaai-account-control .iaai-nav-row>span{display:inline-flex;justify-content:center;width:25px;flex:none;font-size:23px;color:#242124}
.iaai-account-control .iaai-nav-row:hover{background:#f5f0fc;color:#6d28d9!important}
.iaai-account-control :focus-visible{outline:3px solid #7c3aed;outline-offset:3px}
.iaai-menu>summary{border:0;border-radius:8px;padding:10px;width:46px}
.iaai-menu>summary:after{display:none}
.iaai-hamburger{display:block;width:25px;height:18px;border-top:2px solid;border-bottom:2px solid;position:relative}
.iaai-hamburger:after{content:"";position:absolute;left:0;right:0;top:6px;border-top:2px solid}
.iaai-public-header nav[aria-label="Main"]{display:flex;gap:18px;flex-wrap:wrap;justify-content:center}
.iaai-public-header .e-con{overflow:visible}
@media(max-width:767px){.iaai-account-control{gap:8px;justify-content:center}.iaai-nav-dropdown>summary{padding:10px 15px;font-size:15px}.iaai-nav-popover{width:245px}.iaai-language .iaai-nav-popover{width:160px}.iaai-account-control .iaai-nav-row{padding:17px;font-size:17px}.iaai-menu>summary{padding:9px;width:40px}}
</style>';
            return $css . '<div class="iaai-account-control">' . $language . $account . '</div>';
        }
        if ($view === 'contact') {
            $html = '<div class="iaai-panel">';
            foreach (['phone', 'whatsapp', 'contact_email', 'hours'] as $field) {
                $value = get_option('iaai_' . $field, '');
                if ($value) { $html .= '<p><strong>' . esc_html(ucwords(str_replace('_', ' ', $field))) . ':</strong> ' . esc_html($value) . '</p>'; }
            }
            return $html . '</div>';
        }
        return '<div class="iaai-app" data-iaai-view="' . esc_attr($view) . '" data-iaai-settings="' . esc_attr(wp_json_encode($settings)) . '"><div class="iaai-loading" role="status">Loading…</div></div>';
    }
}
