<?php
/**
 * Atelier header partial (plugin default; themes can override via header-atelier.php).
 *
 * @package Axellcore_Atelier
 */

echo \Axellcore_Atelier\Template_Parts::instance()->render( 'axellcore-header' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
