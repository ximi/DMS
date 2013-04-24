<?php


class EditorTypography{

	var $default_font = '"Helvetica", Arial, serif';
	var $import_fonts = array();

	function __construct( PageLinesFoundry $foundry ){

		$this->foundry = $foundry;

 		add_filter('pl_settings_array', array(&$this, 'add_settings'));
		add_filter('pless_vars', array(&$this, 'add_less_vars'));
		add_action('wp_head', array(&$this, 'add_google_imports'));
	}

	function add_less_vars( $vars ){

		$vars['plFontSize'] = (pl_setting('base_font_size')) ? sprintf( '%spx', pl_setting('base_font_size' ) ) : '14px';
		$vars['plHeaderSize'] = (pl_setting('header_base_size')) ? sprintf( '%spx', pl_setting('header_base_size' ) ) : '14px';

		// Base Font
		$primary = $this->import_fonts[] = (pl_setting('font_primary')) ? pl_setting('font_primary') : $this->default_font;
		$hdr = $this->import_fonts[] = (pl_setting('font_headers')) ? pl_setting('font_headers') : $this->default_font;

		$vars['plBaseFont'] = $this->foundry->get_stack( $primary );
		$vars['plHeaderFont'] = $this->foundry->get_stack( $hdr );


		$vars['plBaseWeight'] = ( pl_setting('font_primary_weight') ) ? pl_setting('font_primary_weight') : 'normal';
		$vars['plHeaderWeight'] = ( pl_setting('font_headers_weight') ) ? pl_setting('font_headers_weight') : 'bold';

		return $vars;
	}

	function add_google_imports(){

		$import = $this->foundry->google_import( $this->import_fonts );

		printf('<style id="master_font_import" type="text/css">%2$s%1$s</style>%2$s', $import, "\n");
	}

	function add_settings( $settings ){

		$settings['typography'] = array(
				'name' 	=> 'Typography',
				'icon'	=> 'icon-font',
				'pos'	=> 3,
				'opts' 	=> $this->options()
		);

		return $settings;
	}

	function options(){

		$settings = array(
			
			array(
				'type' 	=> 	'multi',
				'title' => __( 'Primary Text', 'pagelines' ),
				'help' 		=> __( 'The base font size is a reference that will be scaled for text used throughout the site. ', 'pagelines' ),
				'opts'	=> array(
					array(
						'key'			=> 'font_primary',
						'type' 			=> 'type',
						'label' 		=> 'Select Font Face',
						'default'		=> 'droid_sans',
						
					),
					array(
						'key'			=> 'base_font_size',
						'type'			=> 'count_select',
						'compile'		=> true,
						'count_start'	=> 10,
						'count_number'	=> 30,
						'suffix'		=> 'px',
						'title'			=> __( 'Base Font Size', 'pagelines' ),
						'default'		=> 14, 
					),
					array(
						'key'			=> 'font_primary_weight',
						'type' 			=> 'select',
						'classes'		=> 'font-weight',
						'label'			=> 'Font Weight',
						'opts'			=> array(
							'300'	=> array('name' => 'Light (300)*'),
							'400'	=> array('name' => 'Normal (400)'),
							'600'	=> array('name' => 'Semi-Bold (600)*'),
							'600'	=> array('name' => 'Bold (800)')
						),
						'default' 		=> '400',
						'help'			=> __( '*These weights don\'t alwaye have an effect.', 'pagelines' ),
					),
					
				),

			),
			array(
				'type' 	=> 	'multi',
				'title' 		=> __( 'Text Headers <small>(h1-h6)</small>', 'pagelines' ),
				'help' 		=> __( 'Configure the typography for the text headers across your site.', 'pagelines' ),
				'opts'	=> array(
					array(
						'key'			=> 'font_headers',
						'type' 			=> 'type',
						'label' 		=> 'Header Font',
						'default'		=> 'source_sans_pro',
						

					),
					array(
						'key'			=> 'header_base_size',
						'type'			=> 'count_select',
						'compile'		=> true,
						'count_start'	=> 10,
						'count_number'	=> 30,
						'suffix'		=> 'px',
						'title'			=> __( 'Base Font Size', 'pagelines' ),
						'default'		=> 16, 
					),
					array(
						'key'			=> 'font_headers_weight',
						'type' 			=> 'select',
						'classes'			=> 'font-weight',
						'label'			=> 'Font Weight',
						'opts'			=> array(
							'300'	=> array('name' => 'Light (300)'),
							'400'	=> array('name' => 'Normal (400)'),
							'600'	=> array('name' => 'Semi-Bold (600)'),
							'600'	=> array('name' => 'Bold (800)')
						),
						'default' 		=> '600',
					),
				),

			),

			// array(
			// 			'type' 	=> 	'multi',
			// 			'title' 		=> __( 'Secondary Text', 'pagelines' ),
			// 
			// 			'opts'	=> array(
			// 				array(
			// 					'key'			=> 'font_secondary',
			// 					'type' 			=> 'type',
			// 					'label' 		=> 'Header Font',
			// 					'default'		=> 'helvetica',
			// 					'help' 			=> __( 'Configure the typography for secondary text throughout your site. This font may be used in sub headers, or other various elements to add contrast.', 'pagelines' ),
			// 				),
			// 				array(
			// 					'key'			=> 'font_secondary_weight',
			// 					'type' 			=> 'select',
			// 					'label'			=> 'Font Weight',
			// 					'classes'		=> 'font-weight',
			// 					'opts'			=> array(
			// 						'400'	=> array('name' => 'Normal (400)'),
			// 						'600'	=> array('name' => 'Semi-Bold (600)'),
			// 						'800'	=> array('name' => 'Bold (800)')
			// 					),
			// 					'default' 		=> 'normal',
			// 					'compile'		=> true,
			// 				),
			// 				array(
			// 					'key'			=> 'font_secondary_style',
			// 					'type' 			=> 'select',
			// 					'label'			=> 'Font Style',
			// 					'classes'		=> 'font-style',
			// 					'opts'			=> array(
			// 						'normal'	=> array('name' => 'Normal'),
			// 						'italic'	=> array('name' => 'Italic'),
			// 						'uc'		=> array('name' => 'Uppercase'),
			// 						'italic-uc'	=> array('name' => 'Italic/Uppercase')
			// 					),
			// 					'default' 		=> 'normal',
			// 					'compile'		=> true,
			// 				),
			// 			),
			// 
			// 		),

		);


		return $settings;

	}

}





