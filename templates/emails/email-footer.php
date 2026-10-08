<?php
/**
 * E-mail footer, in the mold of WooCommerce's email-footer.php: closes the
 * body and the container, and the credit (site name and the Atelier page).
 *
 * A theme can override this file in axellcore-atelierclub/emails/.
 *
 * Variables: $styles (email-styles.php).
 *
 * @package Axellcore_Atelierclub
 */

defined( 'ABSPATH' ) || exit;

$atelier_page = Axellcore_Atelierclub\Settings::page();
$atelier_url  = $atelier_page ? get_permalink( $atelier_page ) : home_url( '/' );
$site_name    = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
?>
																		</div>
																	</td>
																</tr>
															</table>
															<!-- End Content -->
														</td>
													</tr>
												</table>
												<!-- End Body -->
											</td>
										</tr>
									</table>
								</td>
							</tr>
							<tr>
								<td align="center" valign="top">
									<!-- Footer -->
									<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_footer" role="presentation">
										<tr>
											<td valign="top" id="credit" style="<?php echo esc_attr( $styles['footer'] ); ?>">
												<p class="aa-muted" style="<?php echo esc_attr( $styles['credit'] ); ?>"><?php echo esc_html( $site_name ); ?> · <a class="aa-link" href="<?php echo esc_url( $atelier_url ); ?>" style="<?php echo esc_attr( $styles['credit_link'] ); ?>" target="_blank">Atelier Axell Club</a></p>
											</td>
										</tr>
									</table>
									<!-- End Footer -->
								</td>
							</tr>
						</table>
					</div>
				</td>
				<td><!-- Deliberately empty to support consistent sizing and layout across multiple email clients. --></td>
			</tr>
		</table>
	</body>
</html>
