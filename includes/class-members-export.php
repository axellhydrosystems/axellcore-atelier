<?php
/**
 * Members CSV export (Atelier > Exportar). A port of axellcore's store
 * exporter (axellcore/includes/import-export: Axellcore_Exporter and the
 * export half of Axellcore_Admin), export only and over member users. It
 * does not use axellcore, and every name (actions, nonce, handles, upload
 * folder, CSS) is its own, so both plugins can be active.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export page, batch AJAX handler and download.
 */
final class Members_Export {

	const PAGE          = 'members-export';
	const CAPABILITY    = 'list_users';
	const NONCE         = 'axellcore-atelierclub-members-export';
	const AJAX_ACTION   = 'axellcore_atelierclub_members_export';
	const DOWNLOAD      = 'axellcore_atelierclub_members_download';
	const HANDLE        = 'axellcore-atelierclub-members-export';
	const UPLOAD_FOLDER = 'axellcore-atelierclub-export';
	const PER_PAGE      = 100;

	/**
	 * Singleton instance.
	 *
	 * @var Members_Export|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Members_Export
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
		// After Member::register_admin_page(), so it is the second item.
		add_action( 'admin_menu', array( $this, 'register_page' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_batch' ) );
		add_action( 'admin_post_' . self::DOWNLOAD, array( $this, 'handle_download' ) );
	}

	/**
	 * Columns: id => header label, in export order.
	 *
	 * @return array<string,string>
	 */
	public static function columns() {
		$columns              = array(
			'id'                        => 'ID',
			'status'                    => __( 'Status', 'axellcore-atelierclub' ),
			'registered'                => __( 'Enviado em', 'axellcore-atelierclub' ),
			'login'                     => __( 'Login', 'axellcore-atelierclub' ),
			'fullname'                  => __( 'Nome completo', 'axellcore-atelierclub' ),
			'email'                     => __( 'E-mail', 'axellcore-atelierclub' ),
			'company'                   => __( 'Escritório / Ateliê', 'axellcore-atelierclub' ),
			'phone'                     => __( 'Telefone', 'axellcore-atelierclub' ),
			'professional_registration' => __( 'Registro (CAU / CREA / ABD)', 'axellcore-atelierclub' ),
			'primary_focus'             => __( 'Atuação principal', 'axellcore-atelierclub' ),
			'url'                       => __( 'Portfólio (URL)', 'axellcore-atelierclub' ),
			'profile_type'              => __( 'Tipo de cadastro', 'axellcore-atelierclub' ),
			'br_revenue_id'             => __( 'CPF / CNPJ', 'axellcore-atelierclub' ),
			'country'                   => __( 'País', 'axellcore-atelierclub' ),
			'state'                     => __( 'UF', 'axellcore-atelierclub' ),
			'city'                      => __( 'Cidade', 'axellcore-atelierclub' ),
			'address_street'            => __( 'Logradouro', 'axellcore-atelierclub' ),
			'address_number'            => __( 'Número', 'axellcore-atelierclub' ),
			'address_2'                 => __( 'Complemento', 'axellcore-atelierclub' ),
			'neighborhood'              => __( 'Bairro', 'axellcore-atelierclub' ),
			'landmark'                  => __( 'Referência', 'axellcore-atelierclub' ),
			'postal'                    => __( 'CEP', 'axellcore-atelierclub' ),
		);
		$columns['resellers'] = __( 'Lojas parceiras', 'axellcore-atelierclub' );
		return $columns;
	}

	/**
	 * Columns exported when none is chosen: all but the technical ones (ID,
	 * Enviado em, Login, País), which can still be picked.
	 *
	 * @return string[]
	 */
	public static function default_columns() {
		return array_values( array_diff( array_keys( self::columns() ), array( 'id', 'registered', 'login', 'country' ) ) );
	}

	/**
	 * Document type labels by stored value.
	 *
	 * @return array<string,string>
	 */
	public static function profile_types() {
		return array(
			'individual'   => __( 'Pessoa Física · CPF', 'axellcore-atelierclub' ),
			'legal_entity' => __( 'Pessoa Jurídica · CNPJ', 'axellcore-atelierclub' ),
		);
	}

	/**
	 * The "Exportar" item of the Atelier menu.
	 */
	public function register_page() {
		add_submenu_page(
			Member::ADMIN_PAGE,
			__( 'Exportar membros', 'axellcore-atelierclub' ),
			__( 'Exportar', 'axellcore-atelierclub' ),
			self::CAPABILITY,
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue the page script and styles on this page only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue( $hook_suffix ) {
		if ( ! str_ends_with( (string) $hook_suffix, '_page_' . self::PAGE ) ) {
			return;
		}
		$asset_file = AXELLCORE_ATELIERCLUB_PATH . 'build/admin/members-export/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_style( self::HANDLE, AXELLCORE_ATELIERCLUB_URL . 'build/admin/members-export/style-index.css', array(), $asset['version'] );
		wp_enqueue_script( self::HANDLE, AXELLCORE_ATELIERCLUB_URL . 'build/admin/members-export/index.js', $asset['dependencies'], $asset['version'], true );
		wp_localize_script(
			self::HANDLE,
			'aaMembersExport',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'action'  => self::AJAX_ACTION,
				'i18n'    => array(
					'error' => __( 'Ocorreu um erro. Tente novamente.', 'axellcore-atelierclub' ),
				),
			)
		);
	}

	/**
	 * The export page: columns, filters, progress, Generate CSV.
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'axellcore-atelierclub' ), '', array( 'response' => 403 ) );
		}

		echo '<div class="wrap aa-export-wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Exportar membros', 'axellcore-atelierclub' ) . '</h1><hr class="wp-header-end">';
		echo '<form id="aa-export" class="aa-export-card">';
		echo '<h2 class="aa-export-card__title">' . esc_html__( 'Exportar membros para um arquivo CSV', 'axellcore-atelierclub' ) . '</h2>';
		echo '<p class="aa-export-help">' . esc_html__( 'Gera e baixa um arquivo CSV com a lista de membros.', 'axellcore-atelierclub' ) . '</p>';
		echo '<div id="aa-export-fields">';

		$columns = array();
		foreach ( self::columns() as $id => $label ) {
			$columns[ $id ] = $label;
		}
		self::select_row( 'aa-export-columns', 'columns[]', __( 'Quais colunas exportar?', 'axellcore-atelierclub' ), __( 'Exportar as colunas padrão', 'axellcore-atelierclub' ), $columns );
		self::select_row( 'aa-export-statuses', 'statuses[]', __( 'Quais status exportar?', 'axellcore-atelierclub' ), __( 'Exportar todos os status', 'axellcore-atelierclub' ), Member::roles() );
		echo '<div class="aa-export-row"><label for="aa-export-since">' . esc_html__( 'Cadastrados desde', 'axellcore-atelierclub' ) . '</label><div>';
		echo '<input type="date" id="aa-export-since" name="since" max="' . esc_attr( wp_date( 'Y-m-d' ) ) . '" aria-describedby="aa-export-since-help">';
		echo '<p class="aa-export-help" id="aa-export-since-help">' . esc_html__( 'Inclui o dia escolhido. Vazio exporta desde o primeiro cadastro. Para exportar só os novos, escolha o dia da última exportação (cadastros desse dia podem se repetir).', 'axellcore-atelierclub' ) . '</p>';
		echo '</div></div>';
		self::select_row( 'aa-export-states', 'states[]', __( 'Quais UFs exportar?', 'axellcore-atelierclub' ), __( 'Exportar todas as UFs', 'axellcore-atelierclub' ), self::existing_values( 'state' ) );
		self::select_row( 'aa-export-cities', 'cities[]', __( 'Quais cidades exportar?', 'axellcore-atelierclub' ), __( 'Exportar todas as cidades', 'axellcore-atelierclub' ), self::existing_values( 'city' ) );
		$focuses = array_intersect_key( Admin_Rest::PRIMARY_FOCUS_OPTIONS, self::existing_values( 'primary_focus' ) );
		self::select_row( 'aa-export-focuses', 'focuses[]', __( 'Quais atuações exportar?', 'axellcore-atelierclub' ), __( 'Exportar todas as atuações', 'axellcore-atelierclub' ), $focuses );

		echo '</div>';
		echo '<div id="aa-export-progress" hidden><div class="aa-export-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span></span></div><p id="aa-export-status" aria-live="polite"></p></div>';
		echo '<div class="aa-export-actions"><button type="submit" class="button button-primary">' . esc_html__( 'Gerar CSV', 'axellcore-atelierclub' ) . '</button></div>';
		echo '</form></div>';
	}

	/**
	 * Print one "label + multi-select" row (the script turns it into chips).
	 *
	 * @param string               $id          Select id.
	 * @param string               $name        Field name.
	 * @param string               $label       Row label.
	 * @param string               $placeholder Text shown when nothing is selected.
	 * @param array<string,string> $options     Label by value.
	 */
	private static function select_row( $id, $name, $label, $placeholder, array $options ) {
		echo '<div class="aa-export-row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><div>';
		printf( '<select id="%1$s" name="%2$s" multiple class="aa-export-columns" data-placeholder="%3$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $placeholder ) );
		foreach ( $options as $value => $option ) {
			printf( '<option value="%s">%s</option>', esc_attr( (string) $value ), esc_html( $option ) );
		}
		echo '</select></div></div>';
	}

	/**
	 * Distinct values of one member field, sorted (value => value).
	 *
	 * @param string $field Field (user meta) name.
	 * @return array<string,string>
	 */
	private static function existing_values( $field ) {
		$values = array();
		foreach ( self::member_ids() as $user_id ) {
			$value = (string) get_user_meta( $user_id, $field, true );
			if ( '' !== $value ) {
				$values[ $value ] = $value;
			}
		}
		ksort( $values, SORT_NATURAL | SORT_FLAG_CASE );
		return $values;
	}

	/**
	 * IDs of every member user.
	 *
	 * @return int[]
	 */
	private static function member_ids() {
		return array_map(
			'intval',
			get_users(
				array(
					'role__in' => array_keys( Member::roles() ),
					'fields'   => 'ID',
				)
			)
		);
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- ajax_batch() calls check_ajax_referer() first.
	/**
	 * AJAX: export the next page and, when done, return the download URL.
	 */
	public function ajax_batch() {
		check_ajax_referer( self::NONCE, 'security' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Você não tem permissão para fazer isso.', 'axellcore-atelierclub' ) ), 403 );
		}

		$page  = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
		$token = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
		if ( 1 === $page ) {
			$token = bin2hex( random_bytes( 16 ) );
		}
		$path = self::path_for( $token );
		if ( '' === $path || ( $page > 1 && ! is_file( $path ) ) ) {
			wp_send_json_error( array( 'message' => __( 'A exportação expirou. Comece de novo.', 'axellcore-atelierclub' ) ) );
		}

		$result = self::export_page(
			$path,
			$page,
			array(
				'columns'  => self::posted( 'columns' ),
				'statuses' => self::posted( 'statuses' ),
				'states'   => self::posted( 'states' ),
				'cities'   => self::posted( 'cities' ),
				'focuses'  => self::posted( 'focuses' ),
				'since'    => isset( $_POST['since'] ) ? sanitize_text_field( wp_unslash( $_POST['since'] ) ) : '',
			)
		);

		$result['token'] = $token;
		if ( $result['done'] >= $result['total'] ) {
			$result['percent'] = 100;
			$result['url']     = add_query_arg(
				array(
					'action'   => self::DOWNLOAD,
					'token'    => $token,
					'_wpnonce' => wp_create_nonce( self::DOWNLOAD . '-' . $token ),
				),
				admin_url( 'admin-post.php' )
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * A posted list (nonce already verified).
	 *
	 * @param string $key POST key.
	 * @return string[]
	 */
	private static function posted( $key ) {
		return isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ $key ] ) ) : array();
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	/**
	 * Export one page of members and append it to the file.
	 *
	 * @param string                  $file Absolute path of the temporary file.
	 * @param int                     $page 1-based page number.
	 * @param array<string,mixed>     $args columns, statuses, states, cities, focuses (lists) and
	 *                                      since (Y-m-d in the site timezone, '' for no limit).
	 * @return array{total:int,done:int,percent:int}
	 */
	public static function export_page( $file, $page, array $args ) {
		$columns = array_values( array_intersect( array_keys( self::columns() ), $args['columns'] ) );
		if ( ! $columns ) {
			$columns = self::default_columns();
		}

		$roles      = array_values( array_intersect( array_keys( Member::roles() ), $args['statuses'] ) );
		$meta_query = array();
		foreach ( array(
			'state'         => 'states',
			'city'          => 'cities',
			'primary_focus' => 'focuses',
		) as $field => $key ) {
			if ( $args[ $key ] ) {
				$meta_query[] = array(
					'key'     => $field,
					'value'   => $args[ $key ],
					'compare' => 'IN',
				);
			}
		}

		// Registered since a day (site timezone), that day included.
		$since      = (string) ( $args['since'] ?? '' );
		$date_query = array();
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $since ) ) {
			$date_query[] = array(
				'column'    => 'user_registered',
				'after'     => get_gmt_from_date( $since . ' 00:00:00' ),
				'inclusive' => true,
			);
		}

		$query = new \WP_User_Query(
			array(
				'role__in'    => $roles ? $roles : array_keys( Member::roles() ),
				'date_query'  => $date_query,
				'meta_query'  => $meta_query ? array_merge( array( 'relation' => 'AND' ), $meta_query ) : array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- only when the user filters.
				'number'      => self::PER_PAGE,
				'paged'       => $page,
				'orderby'     => 'ID',
				'order'       => 'ASC',
				'count_total' => true,
			)
		);

		$handle = fopen( $file, 1 === $page ? 'w' : 'a' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return array(
				'total'   => 0,
				'done'    => 0,
				'percent' => 100,
			);
		}
		if ( 1 === $page ) {
			fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			$labels = self::columns();
			fwrite( $handle, self::csv_line( array_map( static fn( $id ) => $labels[ $id ], $columns ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		}
		foreach ( $query->get_results() as $user ) {
			fwrite( $handle, self::csv_line( self::row_for( $user, $columns ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$total = (int) $query->get_total();
		$done  = min( $total, $page * self::PER_PAGE );
		return array(
			'total'   => $total,
			'done'    => $done,
			'percent' => $total ? (int) floor( $done / $total * 100 ) : 100,
		);
	}

	/**
	 * The CSV cells of one member.
	 *
	 * @param \WP_User $user    Member user.
	 * @param string[] $columns Column ids.
	 * @return string[]
	 */
	public static function row_for( \WP_User $user, array $columns ) {
		$meta  = static fn( $key ) => (string) get_user_meta( $user->ID, $key, true );
		$roles = Member::roles();
		$role  = in_array( Member::ROLE, (array) $user->roles, true ) ? Member::ROLE : Member::ROLE_PENDING;

		$values = array(
			'id'         => (string) $user->ID,
			'status'     => $roles[ $role ],
			'registered' => get_date_from_gmt( (string) $user->user_registered ),
			'login'      => (string) $user->user_login,
			'fullname'   => (string) $user->display_name,
			'email'      => (string) $user->user_email,
			'url'        => (string) $user->user_url,
		);
		foreach ( Members::TEXT_META_FIELDS as $field ) {
			$values[ $field ] = $meta( $field );
		}
		$values['state']         = $meta( 'state' );
		$values['city']          = $meta( 'city' );
		$values['primary_focus'] = Admin_Rest::PRIMARY_FOCUS_OPTIONS[ $values['primary_focus'] ] ?? $values['primary_focus'];
		$values['profile_type']  = self::profile_types()[ $values['profile_type'] ] ?? $values['profile_type'];
		$values['br_revenue_id'] = self::format_document( $values['br_revenue_id'] );
		$values['resellers']     = self::join_values(
			array_map( static fn( $field ) => $meta( $field . '_title' ), Members::RESELLER_FIELDS )
		);

		$row = array();
		foreach ( $columns as $id ) {
			$row[] = self::escape_cell( html_entity_decode( $values[ $id ] ?? '', ENT_QUOTES, 'UTF-8' ) );
		}
		return $row;
	}

	/**
	 * CPF (11 digits) or CNPJ (14 digits) with its mask.
	 *
	 * @param string $digits Document digits.
	 * @return string
	 */
	public static function format_document( $digits ) {
		if ( preg_match( '/^(\d{3})(\d{3})(\d{3})(\d{2})$/', $digits, $m ) ) {
			return "$m[1].$m[2].$m[3]-$m[4]";
		}
		if ( preg_match( '/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', $digits, $m ) ) {
			return "$m[1].$m[2].$m[3]/$m[4]-$m[5]";
		}
		return $digits;
	}

	/**
	 * Several values in one cell, joined with ", "; a comma inside a value is
	 * escaped with a backslash (axellcore's Axellcore_Fields::join_values()).
	 *
	 * @param string[] $values Values (empty ones are skipped).
	 * @return string
	 */
	public static function join_values( array $values ) {
		$out = array();
		foreach ( $values as $value ) {
			$value = trim( (string) $value );
			if ( '' !== $value ) {
				$out[] = str_replace( ',', '\\,', $value );
			}
		}
		return implode( ', ', $out );
	}

	/**
	 * One CSV line (axellcore's Axellcore_Fields::csv_line()).
	 *
	 * @param string[] $cells     Cells.
	 * @param string   $delimiter Delimiter.
	 * @return string
	 */
	public static function csv_line( array $cells, $delimiter = ',' ) {
		$out = array();
		foreach ( $cells as $cell ) {
			$cell         = (string) $cell;
			$needs_quotes = '' !== $cell && (
				str_contains( $cell, $delimiter )
				|| str_contains( $cell, '"' )
				|| str_contains( $cell, "\n" )
				|| str_contains( $cell, "\r" )
				|| trim( $cell ) !== $cell
			);
			$out[]        = $needs_quotes ? '"' . str_replace( '"', '""', $cell ) . '"' : $cell;
		}
		return implode( $delimiter, $out ) . "\n";
	}

	/**
	 * Neutralise spreadsheet formulas with a leading quote
	 * (axellcore's Axellcore_Fields::escape_cell()).
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	public static function escape_cell( $value ) {
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Folder for temporary files (created and protected on demand; files
	 * older than a day are removed).
	 *
	 * @return string Absolute path with trailing slash.
	 */
	private static function storage_dir() {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . self::UPLOAD_FOLDER . '/';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $dir . '.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		foreach ( (array) glob( $dir . '*.csv' ) as $old ) {
			if ( is_file( $old ) && filemtime( $old ) < time() - DAY_IN_SECONDS ) {
				wp_delete_file( $old );
			}
		}
		return $dir;
	}

	/**
	 * Path for a token, or '' when the token is malformed.
	 *
	 * @param string $token 32 hex characters.
	 * @return string
	 */
	private static function path_for( $token ) {
		return preg_match( '/^[a-f0-9]{32}$/', $token ) ? self::storage_dir() . $token . '.csv' : '';
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified with check_admin_referer() below.
	/**
	 * Stream the finished export and delete the temporary file.
	 */
	public function handle_download() {
		$token = isset( $_GET['token'] ) ? sanitize_key( wp_unslash( $_GET['token'] ) ) : '';
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Você não tem permissão para fazer isso.', 'axellcore-atelierclub' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::DOWNLOAD . '-' . $token );

		$path = self::path_for( $token );
		if ( '' === $path || ! is_file( $path ) ) {
			wp_die( esc_html__( 'O arquivo da exportação não está mais disponível.', 'axellcore-atelierclub' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=membros-' . gmdate( 'Y-m-d' ) . '.csv' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		wp_delete_file( $path );
		exit;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
}
