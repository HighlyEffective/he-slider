<?php
/*
 Plugin Name: Highly Effective CPT Slider
 Plugin URI: www.highly-effective.com
 Description: Plugin to add slider to a theme via shortcode (and add slides via CPT)
 Version: 0.9
 Author: Timo Mulder
 Author Email: info@highly-effective.com
 Author URI: www.highly-effective.com
 Text Domain: slider
 */

if( ! class_exists('HE_slider') ) 
{
	class HE_slider 
	{
		// MAKE SURE THE PLUGIN HEADER HAS THE SAME VERSION NUMBER!
		const VERSION = '0.2'; 
		
		private $plugin_url; 
		private $localization_domain = 'HE_slider';
		private $locale; 
		private $slider_settings_key = 'slideroptions';
	
		/**
		 * __construct, first function that will be called within the class and sets the basics.
		 * Also initiates the first function set_actions_filters();
		 */
		function __construct() 
		{
			$this->plugin_url =  WP_PLUGIN_URL .'/' . str_replace( basename( __FILE__ ), "", plugin_basename( __FILE__ ) );
			
			// language setup
			$this->locale = get_locale();
			$mo     = dirname(__FILE__) . '/languages/' . $this->locale . '.mo';
			load_textdomain($this->localization_domain, $mo);
			  
			// setup actions and filters
			$this->set_actions_filters();
		}
		
		 /** 
     	 * set_actions_filters this function is the container of all the add_action s
		 * so no more add_actions below the function : )
     	 */
    	public function set_actions_filters() 
    	{
      		add_action('init',  array( $this, 'do_init') ); //create the custom post type slider via the init		
      		add_filter('manage_slider_posts_columns', array($this, 'slider_add_columns'));
      		add_action('manage_slider_posts_custom_column', array($this, 'slider_custom_column'), 10, 2);
			add_action('do_meta_boxes', array($this, 'reposition_image_box')); //reposition imagebox			
			add_theme_support( 'post-thumbnails', array( 'medewerker','slider' )); //adding support for thumbnails
			add_filter('enter_title_here', array($this, 'slider_change_title' ));			
			add_action('edit_form_after_title', array($this, 'reposition_metaboxes')); 
			add_action( 'admin_menu',  array($this, 'slider_settings_page' ));  //add settings page to menu			
			add_shortcode('slider', array($this, 'slider_shortcode')); //create slider shortcode
			add_action('save_post', array($this, 'featured_image_slider_required')); //check if image is set
			add_action('admin_notices', array($this, 'featured_image_slider_required_error'));	
			add_action( 'wp_enqueue_scripts', array( $this, 'slider_styles' ) ); // Slider styles			
			add_action( 'wp_enqueue_scripts', array( $this, 'slider_scripts' ) ); // Slider scripts
			
			add_filter( 'post_type_labels_slider', array($this, 'change_featured_image_labels'), 10, 1 );
			add_action( 'init', array( $this, 'load_settings' ) );
			add_action( 'admin_init', array( $this, 'register_slidersettings' ) );
		}
		
		public function load_settings()
		{
			$this->slider_settings = (array) get_option( $this->slider_settings_key );
			
			$this->slider_settings = array_merge(array(
		   	'slider_effect' => '',
		   	'slider_arrows' => '',
		   	'slider_pagination' => '',
		   	'slider_autoplay' => '',
		   ),$this->slider_settings);
		}
		
		public function register_slidersettings()
		{
			$this->plugin_settings_tabs[$this->slider_settings_key] = 'Slider instellingen';
			
		    register_setting( $this->slider_settings_key, $this->slider_settings_key );
		    $slider_basic_settings = array(
		    		"slider_effect",
		   			"slider_arrows",
					"slider_pagination",
		    		"slider_autoplay"
			);
			
		    add_settings_section( 'section_slider_basic_settings', 'Slider settings', '', $this->slider_settings_key );
		    for($i=0;$i<sizeof($slider_basic_settings);$i++)
			{
				add_settings_field( $slider_basic_settings[$i], ucfirst(str_replace("_"," ",$slider_basic_settings[$i])), array( $this, 'blogsettings_field_option' ), $this->slider_settings_key, 'section_slider_basic_settings',$slider_basic_settings[$i] );	
			}
		}

		public function blogsettings_field_option($value) 
		{
		    if($value == "slider_effect")
			{
		    	$output ='
				<fieldset>
					<label><input name="'.$this->slider_settings_key.'['.$value.']" type="radio" value="slide" '.checked( 'slide', $this->slider_settings[$value], false ) .' /> Slide</label><br />
					<label><input name="'.$this->slider_settings_key.'['.$value.']" type="radio" value="fade" '.checked( 'fade', $this->slider_settings[$value], false ).' /> Fade</label><br />
				</fieldset>
				';
		    }
			elseif($value == "slider_autoplay")
			{
				$output = '<fieldset>
			    	<select id="slider_autoplay" name="'.$this->slider_settings_key.'['.$value.']">
						<option value="null" '.selected( $this->slider_settings[$value], null, false ).'>Geen</option>
						<option value="7000" '.selected( $this->slider_settings[$value], 7000, false ).' >Normaal</option>
						<option value="14000" '.selected( $this->slider_settings[$value], 14000, false ).' >Langzaam</option>
					</select>		
			    </fieldset>
			    ';
			}
			else
			{
				$output ='
				<fieldset>
					<label><input name="'.$this->slider_settings_key.'['.$value.']" type="radio" value="on" '.checked( 'on', $this->slider_settings[$value], false).' /> Aan</label><br />
					<label><input name="'.$this->slider_settings_key.'['.$value.']" type="radio" value="off" '.checked( 'off', $this->slider_settings[$value], false ).' /> Uit</label><br />
			    </fieldset>
		    	';	
			}
		    echo $output;
		}
		
		public function slider_settings_page() 
      	{
      		add_submenu_page(
      			'edit.php?post_type=slider', 
      			'Slider instellingen', 
      			'Slider instellingen', 
      			'edit_posts', 
      			basename(__FILE__), 
      			array($this, 'plugin_options_page'));
		}

		public function plugin_options_page() 
		{
			$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->slider_settings_key;
		    ?>
		    <div class="wrap">
		        <form method="post" action="options.php">
		            <?php wp_nonce_field( 'update-options' ); ?>
		            <?php settings_fields( $tab ); ?>
		            <?php do_settings_sections( $tab ); ?>
		            <?php submit_button(); ?>
		        </form>
		    </div>
		    <?php
		}
		
		public function change_featured_image_labels( $labels ) 
		{
			$labels->featured_image = 'Slide afbeelding';
			$labels->set_featured_image = 'Slider afbeelding toevoegen';
			$labels->remove_featured_image = 'Verwijder afbeelding';
			$labels->use_featured_image = 'Gebruik voor slider';

			return $labels;
		} 

      	public function reposition_metaboxes() 
		{
    		global $post, $wp_meta_boxes;
    		do_meta_boxes(get_current_screen(), 'advanced', $post);
    		unset($wp_meta_boxes[get_post_type($post)]['advanced']);
		} 
		 
		 /**
		 * changing the default placeholder for the title field
		 */
		public function slider_change_title( $input ) 
		{
		    global $post_type;
		
		    if ( $post_type == 'slider' )
		        return __( 'Naam van de slide', $this->localization_domain );
		
		    return $input;
		}
		  
		 /*
		 * do_init called from the set_actions_filters function
		 * triggers two functions that will create the custom_post_type and taxonomy
		 */
		 
		public function do_init() 
		{
      		$this->slider_register_posttype();
      	}
		
		public function reposition_image_box()
		{
			remove_meta_box('postexcerpt', 'slider', 'normal');				
			remove_meta_box('postimagediv', 'slider', 'side');   // what does this do?
			add_meta_box('postimagediv', __('Slider afbeelding'), 'post_thumbnail_meta_box', 'slider', 'advanced', 'high');			
		}
		
		public function slider_register_posttype()
		{
			$labels = array(
					'name' 					=> __('Slider', $this->localization_domain), 
					'menu_name' 			=> __('Slider', $this->localization_domain), 
					'add_new' 				=> __('Slide toevoegen', $this->localization_domain), 
					'add_new_item' 			=> __('Slide toevoegen', $this->localization_domain), 
					'edit_item' 			=> __('Slide wijzigen', $this->localization_domain), 
					'new_item' 				=> __('Slide toevoegen', $this->localization_domain), 
					'not_found'           	=> __('Geen slides gevonden', $this->localization_domain ),
          			'not_found_in_trash'  	=> __('Geen slides gevonden in de prullebak', $this->localization_domain ),
					'view_item' 			=> __('Bekijk slide', $this->localization_domain), );

			$args = array('labels' => $labels, 
							'hierarchical' => false, 
							'description' => 'Voeg sliders toe aan uw website', 
							'supports' => array('title', 'thumbnail', 'page-attributes'), 
							'taxonomies' => array('sliders'), 
							'public' => true, 
							'show_ui' => true, 
							'show_in_menu' => true, 
							'menu_position' => 27, 
							'menu_icon' => 'dashicons-images-alt', 
							'show_in_nav_menus' => false, 
							'publicly_queryable' => true, 
							'exclude_from_search' => false, 
							'has_archive' => true, 
							'query_var' => true, 
							'can_export' => true, 
							'rewrite' => true, 
							'capability_type' => 'post');
		
			register_post_type('slider', $args);
		}


		public function featured_image_slider_required($post_id) {

		    // change to any custom post type 
		    if(get_post_type($post_id) != 'slider')
		        return;
		    
		    if ( !has_post_thumbnail( $post_id ) ) {
		        // set a transient to show the users an admin message
		        set_transient( "has_post_thumbnail", "no" );
		        // unhook this function so it doesn't loop infinitely
		        remove_action('save_post', array($this,'featured_image_slider_required'));
		        // update the post set it to draft
		        wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
		       	add_action('save_post', array($this, 'featured_image_slider_required'));
		    } else {
		        delete_transient( "has_post_thumbnail" );
		    }
		}

		public function featured_image_slider_required_error()
		{
		    // check if the transient is set, and display the error message
		    if ( get_transient( "has_post_thumbnail" ) == "no" ) {
		        echo "<div id='message' class='error'><p><strong>Selecteer a.u.b. een afbeelding voor uw slide</strong></p></div>";
		        delete_transient( "has_post_thumbnail" );
		    }

		}
		
		public function slider_add_columns($columns)
		{
			unset( $columns['date'] );
			$columns['thumbnail'] = "Afbeelding";		
			return $columns;
		}
		
		public function slider_custom_column($column, $post_id)
		{
			switch ( $column ) 
			{
				case 'thumbnail' :
					echo get_the_post_thumbnail($post_id, array(100,100));
				break;
			}
		}

	    /* Register styles */
	    public function slider_styles() {
			$settings = get_option('slider_settings');
				wp_register_style( 'he_slider_styles', plugins_url( '/css/swiper.min.css', __FILE__ ), array(), '', 'all' );
				wp_register_style( 'he_slider_custom_styles', plugins_url( '/css/swiper-edits.min.css', __FILE__ ), array(), '', 'all' );
				wp_enqueue_style( 'he_slider_styles');
				wp_enqueue_style( 'he_slider_custom_styles');
	    }
		
		/* Register scripts */
	    public function slider_scripts() {        
			wp_register_script( 'he_slider_script', plugins_url('/js/swiper.min.js', __FILE__));
			wp_enqueue_script( 'he_slider_script' );
	    }

		public function dynamic_slider_scripts() {
			$slider_options = get_option('slideroptions');
			
			$effect = (empty($slider_options["slider_settings"])) ? 'slide' : $slider_options["slider_settings"];
			
			?>
			<script type="text/javascript">
				jQuery(document).ready(function() {
						var swiper = new Swiper('.swiper-container', {
							effect: '<?php echo $slider_options['slider_effect']; ?>',							
				            spaceBetween: 0,
				            centeredSlides: true,
				            slideToClickedSlide: true,
				            <?php
								if ($slider_options['slider_arrows'] == "on") {
						            echo "nextButton: '.swiper-button-next',";
						            echo "prevButton: '.swiper-button-prev',";
				        		}
				        		if ($slider_options['slider_autoplay'] > 0) {
						            echo "autoplay: ", $slider_options['slider_autoplay'], ",";
				        		}
				        		if ($slider_options['slider_pagination'] == "on") {
						            echo "pagination: '.swiper-pagination',";
						            echo "paginationClickable: true ,";
				        		}
				        	?>				        	
				        });
				});
			</script>
			<?php
	    }    

	    public function slider_shortcode() {	    

	    // ====== Call shortcode like this in a theme: ====== 
	    //	
		// if (  get_theme_mod( 'he_slider_settings' ) == 'on' ) {
		// 	echo do_shortcode("[slider]"); 
		// }
		// else {
		// 	echo '<div class="">output something else</div>';
		// }
	    	// one issue i still found: when you add slides you can enable it in the customizer; but then when you remove all slides it will show an empty slider
			
			$slider_options = get_option('slideroptions');
			
	    	$output = '';	
			$args = array(
					'post_type' => 'slider',
					'post_status' => 'publish',
					'posts_per_page' => 10,
					'orderby'=>'menu_order',
					'order' => 'ASC'
				);
			
			$the_query = new WP_Query( $args );
			if ( $the_query->have_posts() ) 
			{
				add_action( 'wp_footer', array( &$this, 'dynamic_slider_scripts' ) );
				$output .= '<div class="swiper-container">';
					$output .= '<div class="swiper-wrapper">';
					while ( $the_query->have_posts() ) : $the_query->the_post();
					if ( has_post_thumbnail() ) 
					{
						$output .= '<div class="swiper-slide">';	
							$image_src = wp_get_attachment_image_src( get_post_thumbnail_id( $the_query->ID), 'full' );	
							$output .= '<img src="' . $image_src[0]  . '" />';
						$output .= '</div>';
					}				
					endwhile;
					$output .= '</div>';
					
					if ($slider_options["slider_arrows"] == "on") {
						$output .= '<div class="swiper-button-prev"></div>';
						$output .= '<div class="swiper-button-next"></div>';
					}
					if ($slider_options["slider_pagination"] == "on") {
						$output .= '<div class="swiper-pagination"></div>';
					}
				$output .= '</div>';
			}
			return $output;
	    }
		
	} //END OF CLASS
	$he_slider = new HE_slider();	
}
else 
{
	error_log( "HE_slider could not be instantiated" );
}


?>