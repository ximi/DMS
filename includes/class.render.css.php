<?php


class PageLinesRenderCSS {

	var $lessfiles;
	var $types;
	var $ctimeout;
	var $btimeout;
	var $blog_id;

	function __construct() {

		global $blog_id;
		$this->url_string = '%s/?pageless=%s';
		$this->ctimeout = 86400;
		$this->btimeout = 604800;
		$this->types = array( 'sections', 'core', 'custom' );
		$this->lessfiles = $this->get_core_lessfiles();
		self::actions();
	}

	/**
	 *
	 *  Load LESS files
	 *
	 *  @package PageLines Framework
	 *  @since 2.2
	 */
	function get_core_lessfiles(){

		$files[] = 'reset';

		if(pl_has_editor()){
			$files[] = 'pl-structure';
			$files[] = 'pl-editor';
		} 

		if(!pl_deprecate_v2()) {

			$files[] = 'pl-v2';
		}

		$bootstrap = array(
			'pl-wordpress',
			'pl-plugins',
			'grid',
			'alerts',
			'labels-badges',
			'tooltip-popover',
			'buttons',
			'typography',
			'dropdowns',
			'accordion',
			'carousel',
			'navs',
			'modals',
			'thumbnails',
			'component-animations',
			'utilities',
			'pl-objects',
			'pl-tables',
			'wells',
			'forms',
			'breadcrumbs',
			'close',
			'pager',
			'pagination',
			'progress-bars',
			'icons',
			'responsive'
		);

		return array_merge($files, $bootstrap);
	}

	/**
	 *
	 *  Dynamic mode, CSS is loaded to a file using wp_rewrite
	 *
	 *  @package PageLines Framework
	 *  @since 2.2
	 */
	private function actions() {


		if( pl_has_editor() && EditorLessHandler::is_draft() )
			return;

		global $pagelines_template;

		add_filter( 'query_vars', array( &$this, 'pagelines_add_trigger' ) );
		add_action( 'template_redirect', array( &$this, 'pagelines_less_trigger' ) , 15);
		add_action( 'template_redirect', array( &$this, 'less_file_mode' ) );
		add_action( 'wp_enqueue_scripts', array( &$this, 'load_less_css' ) );
		add_action( 'pagelines_head_last', array( &$this, 'draw_inline_custom_css' ) , 25 );
		add_action( 'wp_head', array( &$pagelines_template, 'print_template_section_head' ), 12 );
		add_action( 'pl_scripts_on_ready', array( &$pagelines_template, 'print_on_ready_scripts' ), 12 );
		add_action( 'wp_head', array( &$this, 'do_background_image' ), 13 );
		add_action( 'extend_flush', array( &$this, 'flush_version' ), 1 );
		add_filter( 'pagelines_insert_core_less', array( &$this, 'pagelines_insert_core_less_callback' ) );
		add_action( 'admin_notices', array(&$this,'less_error_report') );
		add_action( 'wp_before_admin_bar_render', array( &$this, 'less_css_bar' ) );
		if ( defined( 'PL_CSS_FLUSH' ) )
			do_action( 'extend_flush' );
		do_action( 'pagelines_max_mem' );
	}

	function less_file_mode() {

		global $blog_id;
		if ( ! get_theme_mod( 'pl_save_version' ) )
			return;

		if( defined( 'LESS_FILE_MODE' ) && false == LESS_FILE_MODE )
			return;

		if( defined( 'PL_NO_DYNAMIC_URL' ) && true == PL_NO_DYNAMIC_URL )
			return;

		$folder = $this->get_css_dir( 'path' );
		$url = $this->get_css_dir( 'url' );

		$file = sprintf( 'compiled-css-%s.css', get_theme_mod( 'pl_save_version' ) );

		if( file_exists( trailingslashit( $folder ) . $file ) ){
			define( 'DYNAMIC_FILE_URL', trailingslashit( $url ) . $file );
			return;
		}

		if( false == $this->check_posix() )
			return;

		$a = $this->get_compiled_core();
		$b = $this->get_compiled_sections();
		$gfonts = preg_match( '#(@import[^;]*;)#', $a['type'], $g );
		$out = '';
		if ( $gfonts ) {
			$a['core'] = sprintf( "%s\n%s", $g[1], $a['core'] );
			$a['type'] = str_replace( $g[1], '', $a['type'] );
		}
		$out .= $this->minify( $a['core'] );
		$out .= $this->minify( $b['sections'] );
		$out .= $this->minify( $a['type'] );
		$out .= $this->minify( $a['dynamic'] );
		$mem = ( function_exists('memory_get_usage') ) ? round( memory_get_usage() / 1024 / 1024, 2 ) : 0;
		if ( is_multisite() )
			$blog = sprintf( ' on blog [%s]', $blog_id );
		else
			$blog = '';
		$out .= sprintf( __( '%s/* CSS was compiled at %s and took %s seconds using %sMB of unicorn dust%s.*/', 'pagelines' ), "\n", date( DATE_RFC822, $a['time'] ), $a['c_time'], $mem, $blog );
		$this->write_css_file( $out );
	}

	function check_posix() {

		if ( true == apply_filters( 'render_css_posix_', false ) )
			return true;

		if ( ! function_exists( 'posix_geteuid') || ! function_exists( 'posix_getpwuid' ) )
			return false;

		$User = posix_getpwuid( posix_geteuid() );
		$File = posix_getpwuid( fileowner( __FILE__ ) );
		if( $User['name'] !== $File['name'] )
			return false;

		return true;
	}

	static function get_css_dir( $type = '' ) {

		$folder = apply_filters( 'pagelines_css_upload_dir', wp_upload_dir() );

		if( 'path' == $type )
			return trailingslashit( $folder['basedir'] ) . 'pagelines';
		else
			return trailingslashit( $folder['baseurl'] ) . 'pagelines';
	}

	function write_css_file( $txt ){



		add_filter('request_filesystem_credentials', '__return_true' );

		$method = 'direct';
		$url = 'themes.php?page=pagelines';

		$folder = $this->get_css_dir( 'path' );
		$file = sprintf( 'compiled-css-%s.css', get_theme_mod( 'pl_save_version' ) );

		if( !is_dir( $folder ) ) {
			if( true !== wp_mkdir_p( $folder ) )
				return false;
		}

		include_once( ABSPATH . 'wp-admin/includes/file.php' );

		if ( is_writable( $folder ) ){
			$creds = request_filesystem_credentials($url, $method, false, false, null);
			if ( ! WP_Filesystem($creds) )
				return false;
		}

			global $wp_filesystem;
			if( is_object( $wp_filesystem ) )
				$wp_filesystem->put_contents( trailingslashit( $folder ) . $file, $txt, FS_CHMOD_FILE);
			else
				return false;
			$url = $this->get_css_dir( 'url' );

			define( 'DYNAMIC_FILE_URL', sprintf( '%s/%s', $url, $file ) );
	}

	function do_background_image() {

		global $pagelines_ID;
		if ( is_archive() || is_home() )
			$pagelines_ID = null;
		$oset = array( 'post_id' => $pagelines_ID );
		$oid = 'page_background_image';
		$sel = cssgroup('page_background_image');
		if( !ploption('supersize_bg', $oset) && ploption( $oid . '_url', $oset )){

			$bg_repeat = (ploption($oid.'_repeat', $oset)) ? ploption($oid.'_repeat', $oset) : 'no-repeat';
			$bg_attach = (ploption($oid.'_attach', $oset)) ? ploption($oid.'_attach', $oset): 'scroll';
			$bg_pos_vert = (ploption($oid.'_pos_vert', $oset) || ploption($oid.'_pos_vert', $oset) == 0 ) ? (int) ploption($oid.'_pos_vert', $oset) : '0';
			$bg_pos_hor = (ploption($oid.'_pos_hor', $oset) || ploption($oid.'_pos_hor', $oset) == 0 ) ? (int) ploption($oid.'_pos_hor', $oset) : '50';
			$bg_selector = (ploption($oid.'_selector', $oset)) ? ploption($oid.'_selector', $oset) : $sel;
			$bg_url = ploption($oid.'_url', $oset);

			$css = sprintf('%s{ background-image:url(%s);', $bg_selector, $bg_url);
			$css .= sprintf('background-repeat: %s;', $bg_repeat);
			$css .= sprintf('background-attachment: %s;', $bg_attach);
			$css .= sprintf('background-position: %s%% %s%%;}', $bg_pos_hor, $bg_pos_vert);
			echo inline_css_markup( 'pagelines-page-bg', $css );

		}
	}


	function less_css_bar() {
		foreach ( $this->types as $t ) {
			if ( ploption( "pl_less_error_{$t}" ) ) {

				global $wp_admin_bar;
				$wp_admin_bar->add_menu( array(
					'parent' => false,
					'id' => 'less_error',
					'title' => sprintf( '<span class="label label-warning pl-admin-bar-label">%s</span>', __( 'LESS Compile error!', 'pagelines' ) ),
					'href' => admin_url( PL_SETTINGS_URL ),
					'meta' => false
				));
				$wp_admin_bar->add_menu( array(
					'parent' => 'less_error',
					'id' => 'less_message',
					'title' => sprintf( __( 'Error in %s Less code: %s', 'pagelines' ), $t, ploption( "pl_less_error_{$t}" ) ),
					'href' => admin_url( PL_SETTINGS_URL ),
					'meta' => false
				));
			}
		}
	}

	function less_error_report() {

		$default = '<div class="updated fade update-nag"><div style="text-align:left"><h4>PageLines %s LESS/CSS error.</h4>%s</div></div>';

		foreach ( $this->types as $t ) {
			if ( ploption( "pl_less_error_{$t}" ) )
				printf( $default, ucfirst( $t ), ploption( "pl_less_error_{$t}" ) );
		}
	}

	/**
	 *
	 * Get custom CSS
	 *
	 *  @package PageLines Framework
	 *  @since 2.2
	 */
	function draw_inline_custom_css() {
		// always output this, even if empty - container is needed for live compile
		$a = $this->get_compiled_custom();
		return inline_css_markup( 'pagelines-custom', rtrim( $this->minify( $a['custom'] ) ) );
	}

	/**
	 *
	 *  Draw dynamic CSS inline.
	 *
	 *  @package PageLines Framework
	 *  @since 2.2
	 */
	function draw_inline_dynamic_css() {

		if( has_filter( 'disable_dynamic_css' ) )
			return;

		$css = $this->get_dynamic_css();
		inline_css_markup('dynamic-css', $css['dynamic'] );
	}

	/**
	 *
	 *  Get Dynamic CSS
	 *
	 *  @package PageLines Framework
	 *  @since 2.2
	 *
	 */
	function get_dynamic_css(){

		$pagelines_dynamic_css = new PageLinesCSS;

		$pagelines_dynamic_css->typography();

		$typography = $pagelines_dynamic_css->css;

		unset( $pagelines_dynamic_css->css );
		$pagelines_dynamic_css->layout();
		$pagelines_dynamic_css->options();

		$out = array(
			'type'		=>	$typography,
			'dynamic'	=>	apply_filters('pl-dynamic-css', $pagelines_dynamic_css->css)
		);
		return $out;
	}

	/**
	 *
	 *  Enqueue the dynamic css file.
	 *
	 *  @package PageLines Framework
	 *  @since 2.2
	 */
	function load_less_css() {

		wp_register_style( 'pagelines-less',  $this->get_dynamic_url(), false, null, 'all' );
		wp_enqueue_style( 'pagelines-less' );
	}

	function get_dynamic_url() {

		global $blog_id;
		$version = get_theme_mod( "pl_save_version" );

		if ( ! $version )
			$version = '1';

		if ( is_multisite() )
			$id = $blog_id;
		else
			$id = '1';

		$version = sprintf( '%s_%s', $id, $version );

		$parent = apply_filters( 'pl_parent_css_url', PL_PARENT_URL );
		
		$url = add_query_arg( 'pageless', $version, trailingslashit( site_url() ) );
		
		if ( defined( 'DYNAMIC_FILE_URL' ) )
			$url = DYNAMIC_FILE_URL;

		if ( has_action( 'pl_force_ssl' ) )
			$url = str_replace( 'http://', 'https://', $url );

		return apply_filters( 'pl_dynamic_css_url', $url );
	}

	function get_base_url() {

		if(function_exists('icl_get_home_url')) {
		    return icl_get_home_url();
		  }

		return get_home_url();
	}

	function check_compat() {

		if( defined( 'LESS_FILE_MODE' ) && false == LESS_FILE_MODE && is_multisite() )
			return true;

		if ( function_exists( 'icl_get_home_url' ) )
			return true;

		if ( defined( 'PLL_INC') )
			return true;

		if ( ! VPRO )
			return true;

		if ( defined( 'PL_NO_DYNAMIC_URL' ) )
			return true;

		if ( is_multisite() && in_array( $GLOBALS['pagenow'], array( 'wp-signup.php' ) ) )
			return true;

		if( site_url() !== get_home_url() )
			return true;

		if ( 'nginx' == substr($_SERVER['SERVER_SOFTWARE'], 0, 5) )
			return false;

		global $is_apache;
		if ( ! $is_apache )
			return true;
	}
	function check_draft() {
		global $pldraft;

		if( is_object($pldraft) )
			$mode = $pldraft->mode;
		else
			$mode = false;

		return( 'draft' == $mode ) ? true : false;
	}
	/**
	 *
	 *  Get compiled/cached CSS
	 *
	 *  @package PageLines Framework
	 *  @since 2.2
	 */
	function get_compiled_core() {

		if ( ! $this->check_draft() && is_array( $a = get_transient( 'pagelines_core_css' ) ) ) {
			return $a;
		} else {

			$start_time = microtime(true);
			build_pagelines_layout();

			$dynamic = $this->get_dynamic_css();

			$core_less = $this->get_core_lesscode();

			$pless = new PagelinesLess();

			$core_less = $pless->raw_less( $core_less  );

			$end_time = microtime(true);
			$a = array(
				'dynamic'	=> $dynamic['dynamic'],
				'type'		=> $dynamic['type'],
				'core'		=> $core_less,
				'c_time'	=> round(($end_time - $start_time),5),
				'time'		=> time()
			);
			if ( strpos( $core_less, 'PARSE ERROR' ) === false ) {
				set_transient( 'pagelines_core_css', $a, $this->ctimeout );
				set_transient( 'pagelines_core_css_backup', $a, $this->btimeout );
				return $a;
			} else {
				return get_transient( 'pagelines_core_css_backup' );
			}
		}
	}

	/**
	 *
	 *  Get compiled/cached CSS
	 *
	 *  @package PageLines Framework
	 *  @since 2.2
	 */
	function get_compiled_sections() {

		if ( ! $this->check_draft() && is_array( $a = get_transient( 'pagelines_sections_css' ) ) ) {
			return $a;
		} else {

			$start_time = microtime(true);
			build_pagelines_layout();

			$sections = $this->get_all_active_sections();

			$pless = new PagelinesLess();
			$sections =  $pless->raw_less( $sections, 'sections' );
			$end_time = microtime(true);
			$a = array(
				'sections'	=> $sections,
				'c_time'	=> round(($end_time - $start_time),5),
				'time'		=> time()
			);
			if ( strpos( $sections, 'PARSE ERROR' ) === false ) {
				set_transient( 'pagelines_sections_css', $a, $this->ctimeout );
				set_transient( 'pagelines_sections_css_backup', $a, $this->btimeout );
				return $a;
			} else {
				return get_transient( 'pagelines_sections_css_backup' );
			}
		}
	}


	/**
	 *
	 *  Get compiled/cached CSS
	 *
	 *  @package PageLines Framework
	 *  @since 2.2
	 */
	function get_compiled_custom() {

		if ( ! $this->check_draft() && is_array(  $a = get_transient( 'pagelines_custom_css' ) ) ) {
			return $a;
		} else {

			$start_time = microtime(true);
			build_pagelines_layout();

			$custom = stripslashes( pl_setting( 'custom_less' ) );

			$pless = new PagelinesLess();
			$custom =  $pless->raw_less( $custom, 'custom' );
			$end_time = microtime(true);
			$a = array(
				'custom'	=> $custom,
				'c_time'	=> round(($end_time - $start_time),5),
				'time'		=> time()
			);
			if ( strpos( $custom, 'PARSE ERROR' ) === false ) {
				set_transient( 'pagelines_custom_css', $a, $this->ctimeout );
				set_transient( 'pagelines_custom_css_backup', $a, $this->btimeout );
				return $a;
			} else {
				return get_transient( 'pagelines_custom_css_backup' );
			}
		}
	}

	/**
	 *
	 *  Get Core LESS code
	 *
	 *  @package PageLines Framework
	 *  @since 2.2
	 */
	function get_core_lesscode() {

			return $this->load_core_cssfiles( apply_filters( 'pagelines_core_less_files', $this->lessfiles ) );
	}

	/**
	 *
	 *  Helper for get_core_less_code()
	 *
	 *  @package PageLines Framework
	 *  @since 2.2
	 */
	function load_core_cssfiles( $files ) {

		$code = '';
		foreach( $files as $less ) {

			$code .= PageLinesLess::load_less_file( $less );
		}
		return apply_filters( 'pagelines_insert_core_less', $code );
	}

	function pagelines_add_trigger( $vars ) {
	    $vars[] = 'pageless';
	    return $vars;
	}

	function pagelines_less_trigger() {
		global $blog_id;
		if( intval( get_query_var( 'pageless' ) ) ) {
			header( 'Content-type: text/css' );
			header( 'Expires: ' );
			header( 'Cache-Control: max-age=604100, public' );

			$a = $this->get_compiled_core();
			$b = $this->get_compiled_sections();
			$gfonts = preg_match( '#(@import[^;]*;)#', $a['type'], $g );

			if ( $gfonts ) {
				$a['core'] = sprintf( "%s\n%s", $g[1], $a['core'] );
				$a['type'] = str_replace( $g[1], '', $a['type'] );
			}
			echo $this->minify( $a['core'] );
			echo $this->minify( $b['sections'] );
			echo $this->minify( $a['type'] );
			echo $this->minify( $a['dynamic'] );
			$mem = ( function_exists('memory_get_usage') ) ? round( memory_get_usage() / 1024 / 1024, 2 ) : 0;
			if ( is_multisite() )
				$blog = sprintf( ' on blog [%s]', $blog_id );
			else
				$blog = '';
			echo sprintf( __( '%s/* CSS was compiled at %s and took %s seconds using %sMB of unicorn dust%s.*/', 'pagelines' ), "\n", date( DATE_RFC822, $a['time'] ), $a['c_time'],  $mem, $blog );
			die();
		}
	}

	/**
	 *
	 *  Minify
	 *
	 *  @package PageLines Framework
	 *  @since 2.2
	 */
	function minify( $css ) {
		if( is_pl_debug() )
			return $css;

		if( ! ploption( 'pl_minify') )
			return $css;

		$data = $css;

	    $data = preg_replace( '#/\*.*?\*/#s', '', $data );
	    // remove new lines \\n, tabs and \\r
	    $data = preg_replace('/(\t|\r|\n)/', '', $data);
	    // replace multi spaces with singles
	    $data = preg_replace('/(\s+)/', ' ', $data);
	    //Remove empty rules
	    $data = preg_replace('/[^}{]+{\s?}/', '', $data);
	    // Remove whitespace around selectors and braces
	    $data = preg_replace('/\s*{\s*/', '{', $data);
	    // Remove whitespace at end of rule
	    $data = preg_replace('/\s*}\s*/', '}', $data);
	    // Just for clarity, make every rules 1 line tall
	    $data = preg_replace('/}/', "}\n", $data);
	    $data = str_replace( ';}', '}', $data );
	    $data = str_replace( ', ', ',', $data );
	    $data = str_replace( '; ', ';', $data );
	    $data = str_replace( ': ', ':', $data );
	    $data = preg_replace( '#\s+#', ' ', $data );

		if ( ! preg_last_error() )
			return $data;
		else
			return $css;
	}

	/**
	 *
	 *  Flush rewrites/cached css
	 *
	 *  @package PageLines Framework
	 *  @since 2.2
	 */
	static function flush_version( $rules = true ) {

		$types = array( 'sections', 'core', 'custom' );

		$folder = trailingslashit( self::get_css_dir( 'path' ) );

		$file = sprintf( 'compiled-css-%s.css', get_theme_mod( 'pl_save_version' ) );

		if( is_file( $folder . $file ) )
			@unlink( $folder . $file );

		// Attempt to flush super-cache and w3 cache.

		if( function_exists( 'prune_super_cache' ) ) {
			global $cache_path;
			$GLOBALS["super_cache_enabled"] = 1;
        	prune_super_cache( $cache_path . 'supercache/', true );
        	prune_super_cache( $cache_path, true );
		}
		

		if( $rules )
			flush_rewrite_rules( true );
		set_theme_mod( 'pl_save_version', time() );

		$types = array( 'sections', 'core', 'custom' );

		foreach( $types as $t ) {

			$compiled = get_transient( "pagelines_{$t}_css" );
			$backup = get_transient( "pagelines_{$t}_css_backup" );

			if ( ! is_array( $backup ) && is_array( $compiled ) && strpos( $compiled[$t], 'PARSE ERROR' ) === false )
				set_transient( "pagelines_{$t}_css_backup", $compiled, 604800 );

			delete_transient( "pagelines_{$t}_css" );
		}
	}

	function pagelines_insert_core_less_callback( $code ) {

		global $pagelines_raw_lesscode_external;
		$out = '';
		if ( is_array( $pagelines_raw_lesscode_external ) && ! empty( $pagelines_raw_lesscode_external ) ) {

			foreach( $pagelines_raw_lesscode_external as $file ) {

				if( is_file( $file ) )
					$out .= pl_file_get_contents( $file );
			}
			return $code . $out;
		}
		return $code;
	}

	function get_all_active_sections() {

		$out = '';
		global $load_sections;
		$available = $load_sections->pagelines_register_sections( true, true );

		$disabled = get_option( 'pagelines_sections_disabled', array() );

		/*
		* Filter out disabled sections
		*/
		foreach( $disabled as $type => $data )
			if ( isset( $disabled[$type] ) )
				foreach( $data as $class => $state )
					unset( $available[$type][ $class ] );

		/*
		* We need to reorder the array so sections css is loaded in the right order.
		* Core, then pagelines-sections, followed by anything else.
		*/
		$sections = array();
		$sections['parent'] = $available['parent'];
		$sections['child'] = array();
		unset( $available['parent'] );
		if( isset( $available['custom'] ) && is_array( $available['custom'] ) ) {
			$sections['child'] = $available['custom']; // load child theme sections that override.
			unset( $available['custom'] );	
		}
		// remove core section less if child theme has a less file
		foreach( $sections['child'] as $c => $cdata) {
			if( isset( $sections['parent'][$c] ) && is_file( $cdata['base_dir'] . '/style.less' ) )
				unset( $sections['parent'][$c] );
		}
		
		if ( is_array( $available ) ) {
			foreach( $available as $type => $data ) {
				if( ! empty( $data ) )
					$sections[$type] = $data;
			}
		}
		foreach( $sections as $t ) {
			foreach( $t as $key => $data ) {
				if ( $data['less'] && $data['loadme'] ) {
					if ( is_file( $data['base_dir'] . '/style.less' ) )
						$out .= pl_file_get_contents( $data['base_dir'] . '/style.less' );
					elseif( is_file( $data['base_dir'] . '/color.less' ))
						$out .= pl_file_get_contents( $data['base_dir'] . '/color.less' );
				}
			}
		}
		return apply_filters('pagelines_lesscode', $out);
	}

} //end of PageLinesRenderCSS

function pagelines_insert_core_less( $file ) {

	global $pagelines_raw_lesscode_external;

	if( !is_array( $pagelines_raw_lesscode_external ) )
		$pagelines_raw_lesscode_external = array();

	$pagelines_raw_lesscode_external[] = $file;
}