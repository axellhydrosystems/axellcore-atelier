<?php
/**
 * Atelier landing page on classic themes (see Classic_Template).
 *
 * @package Axellcore_Atelierclub
 */

use Axellcore_Atelierclub\Classic_Template;

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'aac-classic' ); ?>>
<?php wp_body_open(); ?>
<?php load_template( Classic_Template::locate( 'header-atelier.php' ), false ); ?>
<main class="aac-page">
	<?php
	while ( have_posts() ) {
		the_post();
		the_content();
	}
	?>
</main>
<?php load_template( Classic_Template::locate( 'footer-atelier.php' ), false ); ?>
<?php wp_footer(); ?>
</body>
</html>
