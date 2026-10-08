<?php
/**
 * E-mail header, in the mold of WooCommerce's email-header.php: the outer
 * table with a 600px column, the brand in text (images such as the SVG logo
 * do not show in many e-mail clients), and the container with the heading.
 *
 * A theme can override this file in axellcore-atelierclub/emails/.
 *
 * Variables: $heading (string), $styles (email-styles.php).
 *
 * @package Axellcore_Atelierclub
 */

defined( 'ABSPATH' ) || exit;

$atelier_page = Axellcore_Atelierclub\Settings::page();
$atelier_url  = $atelier_page ? get_permalink( $atelier_page ) : home_url( '/' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<meta content="width=device-width, initial-scale=1.0" name="viewport">
		<meta name="color-scheme" content="light">
		<meta name="supported-color-schemes" content="light">
		<title><?php echo esc_html( $heading ); ?></title>
		<style>
			@media screen and (max-width: 600px) {
				#wrapper { padding: 24px 12px !important; }
				#header_wrapper { padding: 32px 24px 0 !important; }
				#body_content_inner { padding: 20px 24px 32px !important; }
				#header_wrapper h1 { font-size: 26px !important; }
			}
		</style>
	</head>
	<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0" style="<?php echo esc_attr( $styles['body'] ); ?>">
		<table width="100%" id="outer_wrapper" role="presentation" border="0" cellpadding="0" cellspacing="0" style="<?php echo esc_attr( $styles['outer_wrapper'] ); ?>">
			<tr>
				<td><!-- Deliberately empty to support consistent sizing and layout across multiple email clients. --></td>
				<td width="600">
					<div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>" style="<?php echo esc_attr( $styles['wrapper'] ); ?>">
						<table border="0" cellpadding="0" cellspacing="0" width="100%" id="inner_wrapper" role="presentation">
							<tr>
								<td align="center" valign="top">
									<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
										<tr>
											<td id="template_header_image" style="<?php echo esc_attr( $styles['logo_cell'] ); ?>">
												<p class="email-logo-text" style="<?php echo esc_attr( $styles['logo'] ); ?>"><a href="<?php echo esc_url( $atelier_url ); ?>" style="<?php echo esc_attr( $styles['logo_link'] ); ?>" target="_blank">Atelier Axell</a></p>
												<p style="<?php echo esc_attr( $styles['tagline'] ); ?>">Club</p>
											</td>
										</tr>
									</table>
									<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_container" role="presentation" style="<?php echo esc_attr( $styles['container'] ); ?>">
										<tr>
											<td align="center" valign="top">
												<!-- Header -->
												<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header" role="presentation">
													<tr>
														<td id="header_wrapper" style="<?php echo esc_attr( $styles['header'] ); ?>">
															<h1 style="<?php echo esc_attr( $styles['h1'] ); ?>"><?php echo esc_html( $heading ); ?></h1>
															<div style="<?php echo esc_attr( $styles['rule'] ); ?>">&nbsp;</div>
														</td>
													</tr>
												</table>
												<!-- End Header -->
											</td>
										</tr>
										<tr>
											<td align="center" valign="top">
												<!-- Body -->
												<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_body" role="presentation">
													<tr>
														<td valign="top" id="body_content">
															<!-- Content -->
															<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
																<tr>
																	<td valign="top" id="body_content_inner" style="<?php echo esc_attr( $styles['body_inner'] ); ?>">
																		<div id="body_content_inner_cell">
