<?php
/**
 * E-mail styles, as WooCommerce's email-styles.php but returned inline (the
 * plugin has no CSS inliner): the Atelier's colors (Design_Tokens::PALETTE)
 * in light mode, with the system's serif and sans fonts.
 *
 * A theme can override this file in axellcore-atelierclub/emails/.
 *
 * @package Axellcore_Atelierclub
 */

defined( 'ABSPATH' ) || exit;

$ink       = '#0B0E12';
$ivory     = '#EFECE4';
$line      = '#DED6C2';
$stone_dk  = '#5F584C';
$bronze    = '#B4996A';
$bronze_dk = '#7D6138';
$serif     = 'Georgia, "Times New Roman", Times, serif';
$sans      = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';

return array(
	'body'          => "margin:0;padding:0;background-color:$ivory;-webkit-text-size-adjust:100%;",
	'outer_wrapper' => "background-color:$ivory;",
	'wrapper'       => 'margin:0 auto;padding:40px 0;max-width:600px;',
	'logo_cell'     => 'padding:0 0 28px;text-align:center;',
	'logo'          => "margin:0;font-family:$serif;font-size:30px;line-height:1.1;letter-spacing:0.02em;color:$ink;",
	'logo_link'     => "color:$ink;text-decoration:none;",
	'tagline'       => "margin:6px 0 0;font-family:$sans;font-size:11px;line-height:1.4;letter-spacing:0.28em;text-transform:uppercase;color:$stone_dk;",
	'container'     => "background-color:#ffffff;border:1px solid $line;border-radius:4px;",
	'header'        => 'padding:40px 48px 0;text-align:left;',
	'h1'            => "margin:0;font-family:$serif;font-size:30px;font-weight:400;line-height:1.2;color:$ink;",
	'rule'          => "margin:20px 0 0;width:48px;height:2px;line-height:2px;font-size:0;background-color:$bronze;",
	'body_inner'    => "padding:24px 48px 40px;font-family:$sans;font-size:16px;line-height:1.6;color:$ink;text-align:left;",
	'p'             => "margin:0 0 16px;font-family:$sans;font-size:16px;line-height:1.6;color:$ink;",
	'link'          => "color:$bronze_dk;text-decoration:underline;",
	'footer'        => 'padding:24px 48px 0;text-align:center;',
	'credit'        => "margin:0;font-family:$sans;font-size:13px;line-height:1.6;color:$stone_dk;",
	'credit_link'   => "color:$bronze_dk;text-decoration:none;",
);
