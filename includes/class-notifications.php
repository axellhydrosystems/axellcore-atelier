<?php
/**
 * Transactional e-mails, set in Atelier > Settings > E-mails, in the mold of
 * WooCommerce's (WC_Email): each one turned on by itself, with its subject,
 * heading and text (plain text with {placeholders}), sent as a basic HTML
 * e-mail (templates/emails/) with the plain text as its alternative.
 *
 * The default subject, heading and text live here (translated); Settings
 * stores a field only when it differs from the default in the site's
 * language, so an untouched field follows the translation.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Atelier's transactional e-mails.
 */
final class Notifications {

	/**
	 * The e-mails: key => who receives it (team or member).
	 */
	const EMAILS = array(
		'team_new'        => 'team',
		'member_pending'  => 'member',
		'member_created'  => 'member',
		'member_approved' => 'member',
	);

	/**
	 * Editable fields of each e-mail.
	 */
	const FIELDS = array( 'subject', 'heading', 'body' );

	/**
	 * The admin-post action of the preview.
	 */
	const PREVIEW_ACTION = 'aa_email_preview';

	/**
	 * Singleton instance.
	 *
	 * @var Notifications|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Notifications
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'axellcore_atelierclub_member_created', array( $this, 'member_created' ) );
		add_action( 'axellcore_atelierclub_member_approved', array( $this, 'member_approved' ) );
		add_action( 'admin_post_' . self::PREVIEW_ACTION, array( $this, 'preview' ) );
	}

	/**
	 * Each e-mail's name and when it is sent, for Settings.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function titles() {
		return array(
			'team_new'        => array( __( 'New application (team)', 'axellcore-atelierclub' ), __( 'Sent to the team when someone applies on the form.', 'axellcore-atelierclub' ) ),
			'member_pending'  => array( __( 'Application received', 'axellcore-atelierclub' ), __( 'Sent to whoever applies, when the member is created as pending.', 'axellcore-atelierclub' ) ),
			'member_created'  => array( __( 'Welcome', 'axellcore-atelierclub' ), __( 'Sent to whoever applies, when the member is created already approved.', 'axellcore-atelierclub' ) ),
			'member_approved' => array( __( 'Membership approved', 'axellcore-atelierclub' ), __( 'Sent to a pending member once approved.', 'axellcore-atelierclub' ) ),
		);
	}

	/**
	 * The default subject, heading and text of an e-mail, in the current
	 * language.
	 *
	 * @param string $key E-mail.
	 * @return array{subject:string,heading:string,body:string}
	 */
	public static function defaults( $key ) {
		switch ( $key ) {
			case 'team_new':
				return array(
					/* translators: {fullname} and the other {placeholders} are replaced when sending; keep them as they are. */
					'subject' => __( 'New membership application: {fullname}', 'axellcore-atelierclub' ),
					'heading' => __( 'New membership application', 'axellcore-atelierclub' ),
					'body'    => __( "A new application to the Atelier Axell Club has arrived.\n\nName: {fullname}\nOffice / Studio: {company}\nE-mail: {email}\nPhone: {phone}\nRegistration (CAU / CREA / ABD): {professional_registration}\nMain practice: {primary_focus}\nPortfolio (URL): {url}\n\nRegistration type: {profile_type}\n{document_label}: {document}\n\nStreet: {address_street}\nNumber: {address_number}\nAddress line 2: {address_2}\nNeighborhood: {neighborhood}\nLandmark: {landmark}\nCity: {state} {city}\nPostal code: {postal}\n\nPartner stores:\n{stores}\n\nReview the application: {member_admin_url}", 'axellcore-atelierclub' ),
				);
			case 'member_pending':
				return array(
					'subject' => __( 'We received your application, {first_name}', 'axellcore-atelierclub' ),
					'heading' => __( 'Application received', 'axellcore-atelierclub' ),
					'body'    => __( "Hello, {first_name}.\n\nWe received your application to the Atelier Axell Club. Our curators will review it, and we will let you know by e-mail as soon as it is approved.\n\nThank you for your interest.\nAtelier Axell", 'axellcore-atelierclub' ),
				);
			case 'member_created':
				return array(
					'subject' => __( 'Welcome to the Atelier Axell Club, {first_name}', 'axellcore-atelierclub' ),
					'heading' => __( 'Welcome to the Atelier', 'axellcore-atelierclub' ),
					'body'    => __( "Hello, {first_name}.\n\nYour membership in the Atelier Axell Club is confirmed. From now on you are part of a circle of architects and designers who specify Axell.\n\nGet to know the club: {atelier_url}\n\nAtelier Axell", 'axellcore-atelierclub' ),
				);
			case 'member_approved':
				return array(
					'subject' => __( 'Your Atelier Axell Club membership is approved', 'axellcore-atelierclub' ),
					'heading' => __( 'Membership approved', 'axellcore-atelierclub' ),
					'body'    => __( "Hello, {first_name}.\n\nGood news: our curators approved your application, and you are now a member of the Atelier Axell Club.\n\nGet to know the club: {atelier_url}\n\nAtelier Axell", 'axellcore-atelierclub' ),
				);
		}
		return array(
			'subject' => '',
			'heading' => '',
			'body'    => '',
		);
	}

	/**
	 * A default in the site's language (Settings > General): what Settings
	 * shows and compares, and what is sent while the field is not changed.
	 *
	 * @param string $key   E-mail.
	 * @param string $field subject, heading or body.
	 * @return string
	 */
	public static function site_default( $key, $field ) {
		$switched = function_exists( 'switch_to_locale' ) && switch_to_locale( get_locale() );
		$value    = self::defaults( $key )[ $field ] ?? '';
		if ( $switched ) {
			restore_previous_locale();
		}
		return $value;
	}

	/**
	 * A field as sent: the one saved in Settings, else the default.
	 *
	 * @param string $key   E-mail.
	 * @param string $field subject, heading or body.
	 * @return string
	 */
	public static function text( $key, $field ) {
		$saved = (string) Settings::get( self::setting( $key, $field ) );
		return '' !== $saved ? $saved : self::site_default( $key, $field );
	}

	/**
	 * The setting key of an e-mail's field.
	 *
	 * @param string $key   E-mail.
	 * @param string $field enabled, subject, heading, body or to.
	 * @return string
	 */
	public static function setting( $key, $field ) {
		return 'email_' . $key . '_' . $field;
	}

	/**
	 * The header's brand in text (when there is no logo): its text and
	 * description, as saved or by default in the site's language.
	 *
	 * @return array{0:string,1:string}
	 */
	public static function brand() {
		$fields = array();
		foreach ( array( 'brand', 'tagline' ) as $field ) {
			$saved    = (string) Settings::get( 'email_' . $field );
			$fields[] = '' !== $saved ? $saved : self::site_brand_default( $field );
		}
		return $fields;
	}

	/**
	 * The brand's defaults, in the current language.
	 *
	 * @return array{brand:string,tagline:string}
	 */
	public static function brand_defaults() {
		return array(
			'brand'   => __( 'Atelier Axell', 'axellcore-atelierclub' ),
			'tagline' => __( 'The Axell World', 'axellcore-atelierclub' ),
		);
	}

	/**
	 * A brand default in the site's language.
	 *
	 * @param string $field brand or tagline.
	 * @return string
	 */
	public static function site_brand_default( $field ) {
		$switched = function_exists( 'switch_to_locale' ) && switch_to_locale( get_locale() );
		$value    = self::brand_defaults()[ $field ] ?? '';
		if ( $switched ) {
			restore_previous_locale();
		}
		return $value;
	}

	/**
	 * The header's logo: the theme's (Customizer > Site Identity) or one
	 * chosen in Settings; null for none (the brand in text then).
	 *
	 * @return array{0:string,1:string}|null URL and type (image/png…).
	 */
	public static function logo() {
		switch ( (string) Settings::get( 'email_logo' ) ) {
			case 'site':
				$id = (int) get_theme_mod( 'custom_logo' );
				break;
			case 'custom':
				$id = (int) Settings::get( 'email_logo_id' );
				break;
			default:
				return null;
		}
		$url = $id ? wp_get_attachment_image_url( $id, 'full' ) : false;
		return $url ? array( (string) $url, (string) get_post_mime_type( $id ) ) : null;
	}

	/**
	 * Whether an e-mail is turned on.
	 *
	 * @param string $key E-mail.
	 * @return bool
	 */
	public static function enabled( $key ) {
		return (bool) Settings::get( self::setting( $key, 'enabled' ) );
	}

	/**
	 * The team's recipients: the saved ones, else the site's admin e-mail.
	 *
	 * @return string[]
	 */
	public static function team_recipients() {
		$saved = array_filter( array_map( 'trim', explode( ',', (string) Settings::get( self::setting( 'team_new', 'to' ) ) ) ), 'is_email' );
		return $saved ? array_values( $saved ) : array( (string) get_option( 'admin_email' ) );
	}

	/**
	 * The placeholders and their meaning, for Settings.
	 *
	 * @return array<string,string>
	 */
	public static function placeholder_help() {
		return array(
			'{fullname}'                  => __( 'Full name', 'axellcore-atelierclub' ),
			'{first_name}'                => __( 'First name', 'axellcore-atelierclub' ),
			'{company}'                   => __( 'Office / Studio', 'axellcore-atelierclub' ),
			'{email}'                     => __( 'E-mail', 'axellcore-atelierclub' ),
			'{phone}'                     => __( 'Phone', 'axellcore-atelierclub' ),
			'{professional_registration}' => __( 'Registration (CAU / CREA / ABD)', 'axellcore-atelierclub' ),
			'{primary_focus}'             => __( 'Main practice', 'axellcore-atelierclub' ),
			'{url}'                       => __( 'Portfolio (URL)', 'axellcore-atelierclub' ),
			'{profile_type}'              => __( 'Registration type', 'axellcore-atelierclub' ),
			'{document_label}'            => __( 'CPF or CNPJ, as the document is', 'axellcore-atelierclub' ),
			'{document}'                  => __( 'CPF / CNPJ', 'axellcore-atelierclub' ),
			'{address_street}'            => __( 'Street', 'axellcore-atelierclub' ),
			'{address_number}'            => __( 'Number', 'axellcore-atelierclub' ),
			'{address_2}'                 => __( 'Address line 2', 'axellcore-atelierclub' ),
			'{neighborhood}'              => __( 'Neighborhood', 'axellcore-atelierclub' ),
			'{landmark}'                  => __( 'Landmark', 'axellcore-atelierclub' ),
			'{state}'                     => __( 'State code', 'axellcore-atelierclub' ),
			'{city}'                      => __( 'City', 'axellcore-atelierclub' ),
			'{postal}'                    => __( 'Postal code', 'axellcore-atelierclub' ),
			'{stores}'                    => __( 'Partner stores, one per line', 'axellcore-atelierclub' ),
			'{member_admin_url}'          => __( 'The member on Atelier > Members (team e-mail)', 'axellcore-atelierclub' ),
			'{atelier_url}'               => __( 'The Atelier page', 'axellcore-atelierclub' ),
			'{site_name}'                 => __( 'Site name', 'axellcore-atelierclub' ),
			'{site_url}'                  => __( 'Site address', 'axellcore-atelierclub' ),
			'{date}'                      => __( 'Today\'s date', 'axellcore-atelierclub' ),
		);
	}

	/**
	 * The placeholders' values for a member.
	 *
	 * @param int $user_id Member.
	 * @return array<string,string>
	 */
	public static function member_vars( $user_id ) {
		$user_id = (int) $user_id;
		$user    = get_userdata( $user_id );
		$name    = $user instanceof \WP_User ? (string) $user->display_name : '';
		$type    = Members::get( $user_id, 'profile_type' );
		$stores  = array_filter( array_map( array( Members::class, 'reseller_title' ), Members::reseller_ids( $user_id ) ) );
		$vars    = array(
			'{fullname}'                  => $name,
			'{first_name}'                => Members::split_name( $name )[0],
			'{company}'                   => Members::get( $user_id, 'company' ),
			'{email}'                     => $user instanceof \WP_User ? (string) $user->user_email : '',
			'{phone}'                     => Format::phone( Members::get( $user_id, 'phone' ) ),
			'{professional_registration}' => Members::get( $user_id, 'professional_registration' ),
			'{primary_focus}'             => Members::get( $user_id, 'primary_focus' ),
			'{url}'                       => $user instanceof \WP_User ? (string) $user->user_url : '',
			'{profile_type}'              => Members_Export::profile_types()[ $type ] ?? '',
			'{document_label}'            => self::document_label( $type ),
			'{document}'                  => Format::document( Members::get( $user_id, 'br_revenue_id' ) ),
			'{address_street}'            => Members::get( $user_id, 'address_street' ),
			'{address_number}'            => Members::get( $user_id, 'address_number' ),
			'{address_2}'                 => Members::get( $user_id, 'address_2' ),
			'{neighborhood}'              => Members::get( $user_id, 'neighborhood' ),
			'{landmark}'                  => Members::get( $user_id, 'landmark' ),
			'{state}'                     => Members::get( $user_id, 'state' ),
			'{city}'                      => Members::get( $user_id, 'city' ),
			'{postal}'                    => Format::postcode( Members::get( $user_id, 'postal' ) ),
			'{stores}'                    => implode( "\n", $stores ),
		);
		// An empty field reads as a dash ("Landmark: —").
		$vars = array_map( static fn( $value ) => '' !== trim( (string) $value ) ? (string) $value : '—', $vars );
		return $vars + array(
			'{member_admin_url}' => admin_url( 'admin.php?page=' . Member::ADMIN_PAGE . '&member=' . $user_id ),
		) + self::site_vars();
	}

	/**
	 * The document's name: CPF for an individual, CNPJ for a company,
	 * "CPF / CNPJ" when the type is not known.
	 *
	 * @param string $profile_type individual, legal_entity or ''.
	 * @return string
	 */
	public static function document_label( $profile_type ) {
		switch ( $profile_type ) {
			case 'individual':
				return 'CPF';
			case 'legal_entity':
				return 'CNPJ';
		}
		return 'CPF / CNPJ';
	}

	/**
	 * The site's placeholders.
	 *
	 * @return array<string,string>
	 */
	public static function site_vars() {
		$page = Settings::page();
		return array(
			'{site_name}'   => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'{site_url}'    => home_url( '/' ),
			'{atelier_url}' => $page ? (string) get_permalink( $page ) : home_url( '/' ),
			'{date}'        => (string) wp_date( (string) get_option( 'date_format' ) ),
		);
	}

	/**
	 * An e-mail ready to send: subject, HTML and plain text, in the site's
	 * language.
	 *
	 * @param string               $key  E-mail.
	 * @param array<string,string> $vars Placeholders' values.
	 * @return array{subject:string,html:string,text:string}
	 */
	public static function compose( $key, array $vars ) {
		$switched = function_exists( 'switch_to_locale' ) && switch_to_locale( get_locale() );
		$subject  = strtr( self::text( $key, 'subject' ), $vars );
		$heading  = strtr( self::text( $key, 'heading' ), $vars );
		$body     = strtr( self::text( $key, 'body' ), $vars );
		$html     = self::html( $heading, $body );
		if ( $switched ) {
			restore_previous_locale();
		}
		return array(
			'subject' => $subject,
			'html'    => $html,
			'text'    => $heading . "\n\n" . $body,
		);
	}

	/**
	 * The HTML e-mail: header, the text as paragraphs, footer
	 * (templates/emails/, as WooCommerce's email-header.php and
	 * email-footer.php; a theme can override them in
	 * axellcore-atelierclub/emails/).
	 *
	 * @param string $heading Heading.
	 * @param string $body    Plain text.
	 * @return string
	 */
	public static function html( $heading, $body ) {
		$styles  = include self::template( 'email-styles.php' );
		$content = self::body_html( $body, $styles );
		ob_start();
		include self::template( 'email-header.php' );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by body_html().
		include self::template( 'email-footer.php' );
		return (string) ob_get_clean();
	}

	/**
	 * The plain text as HTML: escaped, a blank line starts a paragraph, a
	 * line break stays one, and addresses become links.
	 *
	 * @param string               $body   Plain text.
	 * @param array<string,string> $styles Inline styles (email-styles.php).
	 * @return string
	 */
	public static function body_html( $body, array $styles ) {
		$paragraphs = preg_split( '/\n\s*\n/', str_replace( array( "\r\n", "\r" ), "\n", trim( (string) $body ) ) );
		$html       = '';
		foreach ( (array) $paragraphs as $paragraph ) {
			$paragraph = make_clickable( nl2br( esc_html( trim( $paragraph ) ), false ) );
			$paragraph = str_replace( '<a ', '<a class="aa-link" style="' . esc_attr( $styles['link'] ) . '" ', $paragraph );
			$html     .= '<p class="aa-text" style="' . esc_attr( $styles['p'] ) . '">' . $paragraph . "</p>\n";
		}
		return $html;
	}

	/**
	 * A template file: the theme's copy, else the plugin's.
	 *
	 * @param string $file File name in templates/emails/.
	 * @return string
	 */
	public static function template( $file ) {
		$theme = function_exists( 'locate_template' ) ? locate_template( 'axellcore-atelierclub/emails/' . $file ) : '';
		return '' !== $theme ? $theme : AXELLCORE_ATELIERCLUB_PATH . 'templates/emails/' . $file;
	}

	/**
	 * Send an e-mail when it is on. A failure never stops the application or
	 * the approval.
	 *
	 * @param string   $key      E-mail.
	 * @param string[] $to       Recipients.
	 * @param int      $user_id  Member it is about.
	 * @param string[] $headers  Extra headers.
	 * @return bool Whether it was sent.
	 */
	public static function send( $key, array $to, $user_id, array $headers = array() ) {
		if ( ! self::enabled( $key ) || ! array_filter( $to ) ) {
			return false;
		}
		$email = self::compose( $key, self::member_vars( $user_id ) );
		// The plain text as the HTML's alternative, for this e-mail only.
		$alt = static function ( $phpmailer ) use ( $email ) {
			$phpmailer->AltBody = $email['text']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer's property.
		};
		add_action( 'phpmailer_init', $alt );
		$sent = wp_mail( $to, $email['subject'], $email['html'], array_merge( array( 'Content-Type: text/html; charset=UTF-8' ), $headers ) );
		remove_action( 'phpmailer_init', $alt );
		if ( ! $sent ) {
			error_log( 'axellcore-atelierclub: e-mail ' . $key . ' not sent to ' . implode( ', ', $to ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- no UI here.
		}
		return (bool) $sent;
	}

	/**
	 * A new application: the team's e-mail, and the member's (pending or
	 * created approved).
	 *
	 * @param int $user_id New member.
	 */
	public function member_created( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user instanceof \WP_User ) {
			return;
		}
		self::send( 'team_new', self::team_recipients(), $user->ID, array( 'Reply-To: ' . $user->display_name . ' <' . $user->user_email . '>' ) );
		$key = in_array( Member::ROLE_PENDING, (array) $user->roles, true ) ? 'member_pending' : 'member_created';
		self::send( $key, array( $user->user_email ), $user->ID );
	}

	/**
	 * A pending member approved: the member's e-mail.
	 *
	 * @param int $user_id Member.
	 */
	public function member_approved( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( $user instanceof \WP_User ) {
			self::send( 'member_approved', array( $user->user_email ), $user->ID );
		}
	}

	/**
	 * Preview of an e-mail's HTML with a sample member (Settings > E-mails).
	 */
	public function preview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'axellcore-atelierclub' ), 403 );
		}
		check_admin_referer( self::PREVIEW_ACTION );
		$key = isset( $_GET['email'] ) ? sanitize_key( wp_unslash( $_GET['email'] ) ) : '';
		if ( ! isset( self::EMAILS[ $key ] ) ) {
			wp_die( esc_html__( 'Unknown e-mail.', 'axellcore-atelierclub' ), 404 );
		}
		$email = self::compose( $key, self::sample_vars() );
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo $email['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the e-mail's HTML, escaped while built.
		exit;
	}

	/**
	 * A sample member, for the preview.
	 *
	 * @return array<string,string>
	 */
	public static function sample_vars() {
		return array(
			'{fullname}'                  => 'Ana Souza',
			'{first_name}'                => 'Ana',
			'{company}'                   => 'Souza Arquitetura',
			'{email}'                     => 'ana@escritorio.com.br',
			'{phone}'                     => '(11) 98765-4321',
			'{professional_registration}' => 'A12345-6',
			'{primary_focus}'             => 'Arquitetura residencial de alto padrão',
			'{url}'                       => 'https://souzaarquitetura.com.br',
			'{profile_type}'              => Members_Export::profile_types()['individual'],
			'{document_label}'            => self::document_label( 'individual' ),
			'{document}'                  => '529.982.247-25',
			'{address_street}'            => 'Rua Oscar Freire',
			'{address_number}'            => '1000',
			'{address_2}'                 => 'Sala 12',
			'{neighborhood}'              => 'Jardins',
			'{landmark}'                  => '—',
			'{state}'                     => 'SP',
			'{city}'                      => 'São Paulo',
			'{postal}'                    => '01426-001',
			'{stores}'                    => "A Casa Acabamentos · RS Caxias do Sul\nCasa Blanca · RJ Niterói",
			'{member_admin_url}'          => admin_url( 'admin.php?page=' . Member::ADMIN_PAGE ),
		) + self::site_vars();
	}
}
