<?php
/**
* Plugin Name: Placeholder
* Plugin URI: http://placeholder.wordpress.com/
* Description: Generate Posts with "Lorem Ipsum" text for the purpose of testing content layout.
* Version: 1.0
* Author: Travis Freeman
* Author URI: http://www.travisfreeman.ca/placeholder
* Text Domain: placeholder
* Domain Path: /languages
* License: GPLv2 or later
*/
?>
<?php
defined( 'ABSPATH' ) or die( '!This page can not be loaded outside of WordPress' );
global $wp_version;
if ( version_compare( $wp_version, '4.1', '<' ) )
{
	exit( 'Placeholder requires WordPress 4.1 OR newer. <a href="http://codex.wordpress.org/Upgrading_WordPress">Please update!</a>' );
}
if( !class_exists( 'PLACEHOLDER' ) ):
class PLACEHOLDER
{
	var $DIR;
	var $URI;
	var $text_domain;
	var $hook;
	var $title;
	var $permissions;
	var $slug;
	var $page;
	var $icon;

	function __construct(  )
	{
		$this->DIR				= plugin_dir_path( __FILE__ );
		$this->URI				= plugin_dir_url( __FILE__ );
		$this->text_domain		= 'placeholder';
		$this->hook			= 'options.php';
		$this->title			= __( 'Placeholder', $this->text_domain );
		$this->permissions		= 'manage_options';
		$this->slug			= 'placeholder';
		$this->icon			= 'placeholder.png';
		return;
	}
	
	/* MISC */
	
	private function scrub( $post )
	{
		$post = strip_tags( $post );
		$post = stripslashes( $post );
		$post = trim( $post );
		return $post;
	}
	
	private function word_count( $words, $max = 10 )
	{
		$word = explode( ' ',  $words );
		if	( count( $word ) > 10 ):
			for( $x = 0; $x < 10; $x++ ):
				$revised_words[] = $word[$x];
			endfor;
			$words = implode( ' ', $revised_words );
		endif;
		return $words;
	}
	
	public function add_a_wp_menu( )
	{
		add_action( 'admin_menu', array( $this,'add_page' ) );
		add_action( 'admin_menu', array( &$this, 'add_page_panels' ) );
	}
	
	public function add_page( )
	{
		/* Add the page */
		$this->page = add_menu_page( $this->title, $this->title, $this->permissions, $this->slug, array( &$this, 'render_page' ), $this->URI . $this->icon );
		/* Add callbacks for this screen only */
		add_action( 'admin_print_scripts-' . $this->page, array( &$this, 'header_scripts' ) );
		add_action( 'load-' . $this->page,  array( &$this, 'page_actions' ), 9 );
		add_action( 'admin_footer-' . $this->page, array( &$this, 'footer_scripts' ) );
	}
	
	public function add_page_panels( )
	{
		add_meta_box( 'generate_content', __( 'Generate Placeholder Content', $this->text_domain ), array( &$this, 'generate_content' ), $this->page, 'normal', 'high' );
		add_meta_box( 'general_info', __( 'History', $this->text_domain ), array( &$this, 'general_info' ), $this->page, 'side', 'high' );
	}
	
	public function generate_content( )
	{
		$categories = $post_ids = $post_types = array( );
		if ( wp_verify_nonce( $_POST['placeholder_nonce_field'], 'placeholder_action' )  )
		{
			$post_type		= $this->scrub( $_POST['post_type'] );
			$post_amount	= $this->scrub( $_POST['post_amount'] );
			if ( is_array( $_POST['categories'] ) ):
				foreach( $_POST['categories'] as $cat ):
					$categories[] = $cat;
				endforeach;
			endif;
			if ( $post_type && $post_amount && !empty( $categories ) ):
				$user_ID = get_current_user_id( );
				for( $x = 0; $x < $post_amount; $x++ ):
					$post_title	= 'Lorem ipsum dolor sit amet, consectetur adipiscing elit';
					$post_content	= 'Pellentesque rhoncus erat eget turpis convallis ullamcorper. Vestibulum rutrum tincidunt leo, ut tempus orci pharetra in. 
					Aenean lacinia sem id velit pharetra efficitur. Duis semper eleifend diam ac aliquet. Etiam elementum, diam sed fermentum mattis, metus felis sagittis nibh, eu aliquam neque neque ac tellus.
					Phasellus in felis in dolor vehicula mollis. Integer ut ante sed lectus sodales feugiat vel vulputate quam. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus.
					Proin pretium aliquam elit in accumsan. Maecenas facilisis tellus at ullamcorper semper.';
					$response = wp_remote_get( 'http://loripsum.net/api/plaintext' );
					if ( 200 == $response['response']['code'] && 'OK' == $response['response']['message'] ):
						$post_content	= $this->scrub( $response['body'] );
						$subject		= $post_content;
						$pattern		= '/[^.]+/';
						preg_match_all( $pattern, $subject, $matches );
						$found			= count( $matches[0] );
						$key			= ( $found > 1 ) ? rand( 0, ( $found - 1 ) ) : 0;
						$post_title	= $this->word_count( $matches[0][$key] );
						$post_title	= ( $post_title ) ? $post_title	 : 'Lorem ipsum dolor sit amet, consectetur adipiscing elit';
					endif;
					$my_post = array(
						'post_title'		=> $post_title,
						'post_type'		=> $post_type,
						'post_content'		=> $post_content,
						'post_status'		=> 'publish',
						'post_author'		=> $user_ID,
						'post_category'	=> $categories,
					);
					$post_ids[] = wp_insert_post( $my_post );
				endfor;
				$post_types[] = $post_type;
				$posts_with_placeholder = get_option( $this->slug );
				$data = array(
					'post_ids'		=> $post_ids,
					'post_types'	=> $post_types,
					'timestamp'	=> time( ),
				);
				if ( is_array( $posts_with_placeholder ) ):
					array_push( $posts_with_placeholder, $data );
				else:
					$posts_with_placeholder = array( $data );
				endif;
				update_option( $this->slug, $posts_with_placeholder );
				$post_type		= $post_amount = NULL;
				$categories	= array( );
				$msg = '<div id="message" class="updated fade"><p>' . __( 'The placeholder content has been generated.', $this->text_domain ) . '</p></div>';
			else:
				$msg = '<div id="message" class="error fade"><p>' . __( 'Please ensure that all required fields are checked.', $this->text_domain ) . '</div>';
			endif;
		}
		$post_type_options = array(
			'post' => array( 'label' => 'Post' ),
			'page' => array( 'label' => 'Page' )
		);
		$post_amount_options = range( 1, 10 );
		$args = array(
			'orderby'		=> 'name',
			'order'			=> 'ASC',
			'hide_empty'	=> 0,
		);
		$post_category_options = get_categories( $args );
		echo $msg;
		?>
        <form  action="" method="post" id="placeholder_submit">  
        <?php wp_nonce_field( 'placeholder_action', 'placeholder_nonce_field' ); ?>
        <div>
            <p class="blurb"><?php _e( 'There are occasions when you need to create multiple Posts to see how they look with your chosen WP Theme. This plugin assists in auto generating placeholder content instead of you doing so manually.  Simply follow the easy steps below to create that dummy content for your website. Please note that you should not use this for actual content of a live website.', $this->text_domain ); ?></p>
            <div class="section">
                <div class="header"><strong>1.</strong> <?php _e( 'Select the Post Type', $this->text_domain ); ?></div>
                <div>
                    <select name="post_type">
                        <?php foreach ( $post_type_options as $post_type_option => $info ): ?>
                        <option value="<?php echo $post_type_option; ?>" <?php echo ( ( $post_type == $post_type_option ) ? 'selected="selected"' : NULL ); ?>><?php echo $info['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="section">
                <div class="header"><strong>2.</strong> <?php _e( 'Select amount of posts to generate', $this->text_domain ); ?></div>
                <div>
                    <select name="post_amount">
                        <?php foreach ( $post_amount_options as $post_amount_option ): ?>
                        <option value="<?php echo $post_amount_option; ?>" <?php echo ( ( $post_amount == $post_amount_option ) ? 'selected="selected"' : NULL ); ?>><?php echo $post_amount_option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="section">
                <div class="header"><strong>3.</strong> <?php _e( 'Select from the categories to associate with your posts', $this->text_domain ); ?></div>
                <div>
                    <?php foreach ( $post_category_options as $post_category_option ): ?>
                    <div><label for="category_<?php echo $post_category_option->term_id; ?>">
                    <input type="checkbox" name="categories[]" id="category_<?php echo $post_category_option->term_id; ?>" value="<?php echo $post_category_option->term_id; ?>" <?php echo ( ( in_array( $post_category_option->term_id, $categories ) ) ? 'checked="checked"' : NULL ); ?> />&nbsp;<?php echo $post_category_option->name; ?>
                    </label></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="clear"></div>
            <br />
            <div><input type="submit" name="Submit" value="<?php _e( 'Publish', $this->text_domain ); ?>" class="button-primary" /></div>
        </div>
        </form>	
		<script type="text/javascript">
        var post_type_info = new Object( );
        <?php foreach ( $post_type_options as $post_type_option => $info ): ?>
        post_type_info.<?php echo $post_type_option; ?> = { label: '<?php echo $info['label']; ?>'};
        <?php endforeach; ?>
        </script>
		<?php	
	}
	
	public function general_info( )
	{
		if ( wp_verify_nonce( $_POST['placeholder_trash_nonce_field'], 'placeholder_trash_action' )  )
		{
			$timestamp	= $this->scrub( $_POST['timestamp'] );
			if ( $timestamp ):
				$posts_with_placeholder = get_option( $this->slug );
				if ( is_array( $posts_with_placeholder ) ):
					foreach( $posts_with_placeholder as $key => $p ):
						if ( $p['timestamp'] == $timestamp ):
							$post_ids = $p['post_ids'];
							foreach( $post_ids as $post_id ):
								wp_delete_post( $post_id );
							endforeach;
							unset( $posts_with_placeholder[$key] );
						endif;
					endforeach;
					$posts_with_placeholder = array_values( $posts_with_placeholder );
					update_option( $this->slug, $posts_with_placeholder );
					$msg = '<div id="message" class="updated fade"><p>' . __( 'The placeholder content has been generated.', $this->text_domain ) . '</p></div>';
				endif;
			endif;
		}
		echo $msg;
		$posts_with_placeholder = get_option( $this->slug );
		if ( is_array( $posts_with_placeholder ) && !empty( $posts_with_placeholder ) ):
			$posts_with_placeholder = array_reverse( $posts_with_placeholder );
			?>
			<form action="" method="post" id="placeholder_trash">  
				<?php wp_nonce_field( 'placeholder_trash_action', 'placeholder_trash_nonce_field' ); ?>
				<p><?php _e( 'Below is a list of times when you generated placeholder content. If those posts or pages still exist, you can remove them by selecting the instance and clicking the Trash button.', $this->text_domain ); ?></p>
				<div>
				<select name="timestamp" class="widefat">
					<?php
					foreach( $posts_with_placeholder as $p ):
					?>
					<option value="<?php echo $p['timestamp']; ?>"><?php echo count( $p['post_ids'] ); ?> <?php echo ucfirst( $p['post_types'][0] ); ?><?php echo ( count( $p['post_ids'] ) > 1 ) ? 's' : ''; ?> on <?php echo date( 'M j / y @ g:i a', ( $p['timestamp'] ) ); ?></option>
					<?php
				endforeach;
				?>
				</select>
				</div>
				<br />
				<input type="submit" name="Submit" value="<?php _e( 'Trash', $this->text_domain ); ?>" class="button-primary" />
			</form>
			<?php
		else:
		?>
        <p><?php _e( 'There is currently no record of any placeholder content generated.', $this->text_domain ); ?></p>
       <?php
		endif;
	}
	
	public function header_scripts( )
	{
		
		?>
<style type="text/css">
#generate_content .section { float:left; width:30%; margin-right:20px; }
	#generate_content .section .header { font-size:14px; margin-bottom:10px; }
	#generate_content .section select { width:100%; }
</style>        
		<?php
	}

	public function page_actions( )
	{
		do_action( 'add_meta_boxes_' . $this->page, NULL );
		do_action( 'add_meta_boxes', $this->page, NULL );
		add_screen_option( 'layout_columns', array( 'max' => 2, 'default' => 2 ) );
		wp_enqueue_script( 'postbox' );
	}
	
	public function footer_scripts( )
	{
		?>
<script type="text/javascript">
postboxes.add_postbox_toggles( pagenow );
jQuery( document ).ready( function( ) {
	jQuery( '#placeholder_submit' ).on( 'submit', function( e ) {
		post_type = jQuery( 'select[name="post_type"]' ).val( );
		post_amount = jQuery( 'select[name="post_amount"]' ).val( );
		confirmation = confirm( 'Are you sure you want to generate ' + post_amount + ' ' + post_type_info[post_type].label + ( ( post_amount > 1 ) ? 's' : '' ) + ' ?' );
		if ( confirmation ) return true;
		e.preventDefault( );
	});
	jQuery( '#placeholder_trash' ).on( 'submit', function( e ) {
		confirmation = confirm( 'Are you sure you want to trash those Posts ?' );
		if ( confirmation ) return true;
		e.preventDefault( );
	});
});
</script>
		<?php
	}

	public function render_page( )
	{
		?>
		<div class="wrap">
			<?php screen_icon( ); ?>
			<h2><?php _e( $this->title, $this->text_domain ); ?></h2>
			<?php
			wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
			wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
			?>
			<div id="poststuff">
                <div id="post-body" class="metabox-holder columns-<?php echo 1 == get_current_screen( )->get_columns( ) ? '1' : '2'; ?>"> 
                    <div id="postbox-container-2" class="postbox-container">
							<?php do_meta_boxes( '', 'normal', NULL );  ?>
							<?php do_meta_boxes( '', 'advanced', NULL ); ?>
                    </div>
                    <div id="postbox-container-1" class="postbox-container">
						<?php do_meta_boxes( '', 'side', NULL ); ?>
                    </div>    
                </div> <!-- #post-body -->
			</div> <!-- #poststuff -->
		</div><!-- .wrap -->
		<?php
	}
}
$PLACEHOLDER = new PLACEHOLDER( );
else :
	exit( "Class 'PLACEHOLDER' already exists" );
endif;
if ( isset( $PLACEHOLDER ) )
{
	if ( is_admin( ) )
	{
		@$PLACEHOLDER->add_a_wp_menu( );
	}
}
?>