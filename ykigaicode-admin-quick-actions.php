<?php
/**
 * Plugin Name: Admin Quick Actions by YkigaiCode
 * Description: A Spotlight-style command palette for the WordPress admin with deep admin menu search, recents, and a dashboard help page — by YkigaiCode.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: YkigaiCode
 * Author URI: https://ykigai.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ykigaicode-admin-quick-actions
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class YKIGAI_AQA_Plugin {
	const SLUG = 'ykigaicode-admin-quick-actions';
	const NONCE_ACTION = 'ykigaicode_aqa_nonce';
	const VERSION = '0.1.0';

	public static function init() : void {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
		add_action( 'wp_ajax_ykigaicode_aqa_search', [ __CLASS__, 'ajax_search' ] );

		add_action( 'admin_menu', [ __CLASS__, 'register_admin_page' ] );
		add_action( 'wp_dashboard_setup', [ __CLASS__, 'register_dashboard_widget' ] );

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ __CLASS__, 'plugin_action_links' ] );
	}

	public static function enqueue_admin_assets( string $hook ) : void {
		wp_register_style(
			'ykigaicode-aqa-css',
			plugins_url( 'assets/css/command-palette.css', __FILE__ ),
			[],
			self::VERSION
		);
		wp_register_script(
			'ykigaicode-aqa-js',
			plugins_url( 'assets/js/command-palette.js', __FILE__ ),
			[],
			self::VERSION,
			true
		);

		wp_enqueue_style( 'ykigaicode-aqa-css' );
		wp_enqueue_script( 'ykigaicode-aqa-js' );

		$payload = [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
			'pluginBy'  => 'by YkigaiCode',
			'strings'   => [
				'title'       => 'Admin Quick Actions',
				'subtitle'    => 'by YkigaiCode',
				'placeholder' => 'Type a command or search… (e.g., “new page”, “menus”, “settings”)',
				'empty'       => 'No results. Try another keyword.',
				'hint'        => 'Mac: Cmd+K • Win/Linux: Ctrl+Shift+K • Fallback: Alt+K / Ctrl+Space',
				'recents'     => 'Recent items',
				'actions'     => 'Quick actions',
				'admin'       => 'Admin menu',
				'content'     => 'Content',
			],
			'actions'   => self::get_static_actions(),
		];

		wp_localize_script( 'ykigaicode-aqa-js', 'YKIGAI_AQA', $payload );
	}

	private static function get_static_actions() : array {
		$actions = [
			[ 'id' => 'new_page', 'label' => 'New Page', 'keywords' => [ 'new page', 'create page', 'add page' ], 'url' => admin_url( 'post-new.php?post_type=page' ), 'icon' => '➕' ],
			[ 'id' => 'new_post', 'label' => 'New Post', 'keywords' => [ 'new post', 'create post', 'add post' ], 'url' => admin_url( 'post-new.php' ), 'icon' => '➕' ],
			[ 'id' => 'all_pages', 'label' => 'All Pages', 'keywords' => [ 'pages', 'all pages' ], 'url' => admin_url( 'edit.php?post_type=page' ), 'icon' => '📄' ],
			[ 'id' => 'all_posts', 'label' => 'All Posts', 'keywords' => [ 'posts', 'all posts', 'blog' ], 'url' => admin_url( 'edit.php' ), 'icon' => '📝' ],
			[ 'id' => 'media', 'label' => 'Media Library', 'keywords' => [ 'media', 'library', 'images' ], 'url' => admin_url( 'upload.php' ), 'icon' => '🖼️' ],
			[ 'id' => 'menus', 'label' => 'Menus', 'keywords' => [ 'menu', 'menus', 'navigation', 'nav' ], 'url' => admin_url( 'nav-menus.php' ), 'icon' => '🧭' ],
			[ 'id' => 'customize', 'label' => 'Customizer (Theme)', 'keywords' => [ 'customize', 'theme customize', 'header', 'footer' ], 'url' => admin_url( 'customize.php' ), 'icon' => '🎛️' ],
			[ 'id' => 'plugins', 'label' => 'Plugins', 'keywords' => [ 'plugins', 'plugin' ], 'url' => admin_url( 'plugins.php' ), 'icon' => '🔌' ],
			[ 'id' => 'themes', 'label' => 'Themes', 'keywords' => [ 'themes', 'theme' ], 'url' => admin_url( 'themes.php' ), 'icon' => '🎨' ],
			[ 'id' => 'settings', 'label' => 'Settings', 'keywords' => [ 'settings', 'general settings', 'permalinks' ], 'url' => admin_url( 'options-general.php' ), 'icon' => '⚙️' ],
		];

		if ( class_exists( 'WooCommerce' ) ) {
			$actions[] = [ 'id' => 'woo_orders', 'label' => 'WooCommerce Orders', 'keywords' => [ 'woo orders', 'orders', 'woocommerce orders' ], 'url' => admin_url( 'edit.php?post_type=shop_order' ), 'icon' => '🛒' ];
			$actions[] = [ 'id' => 'woo_products', 'label' => 'WooCommerce Products', 'keywords' => [ 'woo products', 'products', 'woocommerce products' ], 'url' => admin_url( 'edit.php?post_type=product' ), 'icon' => '🏷️' ];
			$actions[] = [ 'id' => 'woo_settings', 'label' => 'WooCommerce Settings', 'keywords' => [ 'woo settings', 'woocommerce settings' ], 'url' => admin_url( 'admin.php?page=wc-settings' ), 'icon' => '⚙️' ];
		}

		return $actions;
	}

	public static function ajax_search() : void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		$q = trim( $q );

		$results = [];

		// Actions (server-side too)
		$needle = mb_strtolower( $q );
		foreach ( self::get_static_actions() as $a ) {
			$hay = mb_strtolower( $a['label'] . ' ' . implode( ' ', (array) $a['keywords'] ) );
			if ( $needle === '' || mb_strpos( $hay, $needle ) !== false ) {
				$results[] = [
					'type'  => 'Action',
					'title' => $a['label'],
					'url'   => esc_url_raw( $a['url'] ),
					'icon'  => $a['icon'] ?? '⚡',
					'meta'  => 'by YkigaiCode',
				];
			}
		}

		// Content search
		if ( $needle !== '' ) {
			$post_types = [ 'page', 'post' ];
			if ( class_exists( 'WooCommerce' ) ) {
				$post_types[] = 'product';
				if ( current_user_can( 'manage_woocommerce' ) ) {
					$post_types[] = 'shop_order';
				}
			}

			$query = new WP_Query( [
				's'             => $q,
				'post_type'      => $post_types,
				'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
				'posts_per_page' => 10,
				'no_found_rows'  => true,
				'fields'         => 'ids',
			] );

			if ( $query->have_posts() ) {
				foreach ( $query->posts as $post_id ) {
					if ( ! current_user_can( 'edit_post', $post_id ) ) continue;

					$pt   = get_post_type( $post_id );
					$obj  = get_post_type_object( $pt );
					$label = $obj && isset( $obj->labels->singular_name ) ? $obj->labels->singular_name : ucfirst( (string) $pt );

					$title = get_the_title( $post_id );
					if ( $title === '' ) $title = '(no title)';

					$results[] = [
						'type'  => 'Content',
						'title' => $title,
						'url'   => esc_url_raw( get_edit_post_link( $post_id, 'raw' ) ),
						'icon'  => $pt === 'page' ? '📄' : ( $pt === 'product' ? '🏷️' : ( $pt === 'shop_order' ? '🧾' : '📝' ) ),
						'meta'  => $label . ' • by YkigaiCode',
					];
				}
			}
		}

		// Dedup by URL
		$seen = [];
		$final = [];
		foreach ( $results as $r ) {
			$u = $r['url'] ?? '';
			if ( ! $u ) continue;
			if ( isset( $seen[ $u ] ) ) continue;
			$seen[ $u ] = true;
			$final[] = $r;
		}

		wp_send_json_success( [
			'query'   => $q,
			'results' => array_slice( $final, 0, 50 ),
		] );
	}

	public static function register_admin_page() : void {
		add_dashboard_page(
			'Admin Quick Actions by YkigaiCode',
			'Quick Actions by YkigaiCode',
			'edit_posts',
			self::SLUG,
			[ __CLASS__, 'render_admin_page' ]
		);
	}

	public static function render_admin_page() : void {
		?>
		<div class="wrap">
			<h1>Admin Quick Actions <span style="opacity:.75;font-weight:400">by YkigaiCode</span></h1>

			<div style="max-width: 920px">
				<p>
					This plugin adds a Spotlight-style command palette inside wp-admin — <strong>by YkigaiCode</strong>.
					Open it, type a command or a page/post title, and jump straight to what you need.
				</p>

				<h2>Open the Command Palette</h2>
				<ul style="list-style: disc; padding-left: 22px">
					<li><strong>Mac:</strong> <code>Cmd + K</code></li>
					<li><strong>Windows/Linux:</strong> <code>Ctrl + Shift + K</code></li>
					<li><strong>Fallbacks:</strong> <code>Alt + K</code> or <code>Ctrl + Space</code></li>
				</ul>

				<div class="notice notice-info inline">
					<p>
						<strong>About Ctrl + K:</strong> Many browsers reserve <code>Ctrl + K</code> for the address bar / search.
						Because of that, <code>Ctrl + K</code> may open your browser search instead of the palette.
						Use <code>Ctrl + Shift + K</code>, <code>Alt + K</code>, or <code>Ctrl + Space</code> instead — by YkigaiCode.
					</p>
				</div>

				<p>
					<button type="button" class="button button-primary" id="ykigaicode-aqa-open">Open Quick Actions</button>
					<span style="margin-left:10px;opacity:.8">Inside the palette: <code>Cmd/Ctrl + Enter</code> opens in a new tab — by YkigaiCode.</span>
				</p>

				<h2>What you can search</h2>
				<ul style="list-style: disc; padding-left: 22px">
					<li><strong>Admin Menu items</strong> (including deep submenu links)</li>
					<li><strong>Quick actions</strong> (Pages, Posts, Menus, Settings, Plugins, Themes, etc.)</li>
					<li><strong>Content</strong>: Pages & Posts (plus Products/Orders when WooCommerce is active and you have permission)</li>
					<li><strong>Recent items</strong>: your last opened results show up first next time</li>
				</ul>

				<h2>Troubleshooting</h2>
				<ul style="list-style: disc; padding-left: 22px">
					<li>If the hotkey doesn’t work, try <code>Alt + K</code> or <code>Ctrl + Space</code>.</li>
					<li>Some browser extensions can block shortcuts. Temporarily disable them and test again.</li>
					<li>Other admin plugins can register global shortcuts. If needed, disable them to verify the conflict — by YkigaiCode.</li>
				</ul>
			</div>
		</div>
		<?php
	}

	public static function register_dashboard_widget() : void {
		wp_add_dashboard_widget(
			'ykigaicode_aqa_widget',
			'Admin Quick Actions by YkigaiCode',
			[ __CLASS__, 'render_dashboard_widget' ]
		);
	}

	public static function render_dashboard_widget() : void {
		?>
		<div class="ykigaicode-aqa-widget">
			<p><strong>Admin Quick Actions</strong> <span class="ykigaicode-aqa-by">by YkigaiCode</span></p>
			<p>Open with <code>Cmd + K</code> (Mac) or <code>Ctrl + Shift + K</code> (Windows/Linux). Fallback: <code>Alt + K</code> / <code>Ctrl + Space</code>.</p>
			<p><button type="button" class="button button-primary" id="ykigaicode-aqa-open">Open Quick Actions</button></p>
			<p class="description">Search admin menu, content, and jump anywhere — by YkigaiCode.</p>
		</div>
		<?php
	}

	public static function plugin_action_links( array $links ) : array {
		$links[] = '<span style="opacity:.85">by YkigaiCode</span>';
		return $links;
	}
}

YKIGAI_AQA_Plugin::init();
