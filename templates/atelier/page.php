<?php
/**
 * Atelier landing page on classic themes (see Classic_Template and
 * Classic_Styles): the block template's markup, rendered before wp_head().
 *
 * @package Axellcore_Atelierclub
 */

use Axellcore_Atelierclub\Classic_Template;

the_post();
$axellcore_atelierclub_page = Classic_Template::render_page();

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'aa-classic' ); ?>>
<?php wp_body_open(); ?>
<?php echo $axellcore_atelierclub_page; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered blocks. ?>
<?php wp_footer(); ?>
</body>
</html>
