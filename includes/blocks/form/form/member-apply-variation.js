/**
 * "Member Application Form" — a axell/form variation pre-loaded with every
 * field of the real Atelier Axell Club application form (the same content
 * bin/generate-content.py produces for the live /atelier page), so
 * inserting this variation from scratch in the editor doesn't mean
 * rebuilding all 18 fields by hand field-by-field.
 *
 * This is a SECOND, independent expression of that same field list — not
 * generated from bin/generate-content.py, and not what actually renders on
 * /atelier (that's still 100% owned by the Python generator + the seeded
 * post_content, unchanged). If the real form's fields ever change, this
 * file needs updating by hand too. Accepted tradeoff for now: this
 * variation exists purely as an editor convenience/starting point for
 * inserting a fresh copy of the form elsewhere, not as the source of truth
 * for the production page.
 */
( function ( blocks, i18n ) {
	var __ = i18n.__;

	function label( id, text, required ) {
		return [ 'axell/form-label', {
			for: id,
			text: required ? text + '<span class="aa-req"> *</span>' : text,
		} ];
	}

	function control( type, id, extra ) {
		return [ 'axell/form-control', Object.assign( { type: type, id: id }, extra || {} ) ];
	}

	/** One label+control pair, wrapped in the same `.aa-field` group the
	 * real generator uses (reuses sections.css's existing rule verbatim). */
	function field( type, id, text, required, controlExtra ) {
		return [ 'core/group', { className: 'aa-field' }, [
			label( id, text, required ),
			control( type, id, Object.assign( { required: !! required }, controlExtra || {} ) ),
		] ];
	}

	function hint( text ) {
		return [ 'core/paragraph', { content: text, className: 'aa-hint' } ];
	}

	/** A row of 2-3 fields side by side — same `.aa-form-row`/`.aa-cols-*`
	 * classes the real generator uses. */
	function row( colsClass, fields ) {
		return [ 'core/group', { className: 'aa-form-row ' + colsClass }, fields ];
	}

	function fieldset( legendText, rows ) {
		return [ 'axell/form-fieldset', { legend: legendText }, rows ];
	}

	var UF_OPTIONS = [ 'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO' ]
		.map( function ( s ) { return { label: s, value: s }; } );

	var TEMPLATE = [
		fieldset( '01 — Autoria', [
			row( 'aa-cols-2', [
				field( 'text', 'nome', 'Nome completo', true, { placeholder: 'Como devemos chamá-lo(a)?' } ),
				field( 'text', 'escritorio', 'Escritório / Atelê', true, { placeholder: 'Nome do escritório' } ),
			] ),
			row( 'aa-cols-3', [
				field( 'email', 'email', 'E-mail profissional', true, { placeholder: 'voce@escritorio.com.br' } ),
				field( 'tel', 'telefone', 'Telefone', true, { placeholder: '(11) 90000-0000', mask: 'phone' } ),
				field( 'text', 'registro', 'Registro (CAU / CREA / ABD)', false, { placeholder: 'A00000-0' } ),
			] ),
			row( 'aa-cols-2', [
				[ 'core/group', { className: 'aa-field' }, [
					label( 'atuacao', 'Atuação principal', true ),
					control( 'select', 'atuacao', {
						required: true,
						placeholder: 'Selecione uma opção',
						options: [
							{ label: 'Arquitetura residencial de alto padrão', value: 'Arquitetura residencial de alto padrão' },
							{ label: 'Design de interiores', value: 'Design de interiores' },
							{ label: 'Arquitetura corporativa / hospitalidade', value: 'Arquitetura corporativa / hospitalidade' },
							{ label: 'Wellness · Spa · Hotelaria', value: 'Wellness · Spa · Hotelaria' },
							{ label: 'Outros', value: 'Outros' },
						],
					} ),
				] ],
				[ 'core/group', { className: 'aa-field' }, [
					label( 'portfolio', 'Portfólio (URL)', false ),
					control( 'url', 'portfolio', { placeholder: 'https://…' } ),
					hint( 'Site, Instagram, Behance ou drive com projetos.' ),
				] ],
			] ),
		] ),
		fieldset( '02 — Documento', [
			row( 'aa-cols-2', [
				[ 'core/group', { className: 'aa-field' }, [
					label( 'tipoDoc', 'Tipo de cadastro', true ),
					control( 'select', 'tipoDoc', {
						required: true,
						placeholder: 'Selecione',
						options: [
							{ label: 'Pessoa Física · CPF', value: 'cpf' },
							{ label: 'Pessoa Jurídica · CNPJ', value: 'cnpj' },
						],
					} ),
				] ],
				[ 'core/group', { className: 'aa-field' }, [
					label( 'documento', 'CPF ou CNPJ', true ),
					control( 'text', 'documento', { required: true, placeholder: '000.000.000-00 / 12.ABC.345/01DE-35', mask: 'cpf-cnpj', maskSourceName: 'tipoDoc' } ),
					hint( 'Utilizado para emissão de bônus e nota fiscal. CNPJ alfanumérico é aceito.' ),
				] ],
			] ),
		] ),
		fieldset( '03 — Endereço do escritório', [
			row( 'aa-cols-addr', [
				field( 'text', 'rua', 'Logradouro', true, { placeholder: 'Rua, Avenida, Alameda…' } ),
				field( 'text', 'numero', 'Número', true, { placeholder: '000' } ),
				field( 'text', 'complemento', 'Complemento', false, { placeholder: 'Sala, andar, conjunto' } ),
			] ),
			row( 'aa-cols-2', [
				field( 'text', 'bairro', 'Bairro', true, { placeholder: 'Bairro' } ),
				field( 'text', 'referencia', 'Referência', false, { placeholder: 'Próximo a…' } ),
			] ),
			row( 'aa-cols-city', [
				field( 'select', 'uf', 'UF', true, { placeholder: '—', options: UF_OPTIONS } ),
				field( 'select', 'cidade', 'Cidade', true, { placeholder: 'Selecione o estado', citiesSourceName: 'uf' } ),
				field( 'text', 'cep', 'CEP', true, { placeholder: '00000-000', mask: 'cep' } ),
			] ),
		] ),
		fieldset( '04 — Lojas parceiras', [
			[ 'core/group', { className: 'aa-field' }, [
				[ 'core/paragraph', { content: 'Onde você costuma especificar Axell?', className: 'aa-field-label-text' } ),
				hint( 'Liste até <strong style="color:var(--bronze-3);font-weight:500">cinco</strong> revendas ou showrooms parceiros com quem você trabalha. Preencha apenas o que fizer sentido — os campos vazios podem ficar em branco.' ),
				[ 'core/html', { content: '<ol type="i" class="aa-partner-slots">' +
					[ 1, 2, 3, 4, 5 ].map( function ( i ) {
						return '<li class="aa-partner-slot"><input type="text" name="loja' + i + '" placeholder="Nome da loja · cidade"></li>';
					} ).join( '' ) +
					'</ol>' } ],
			] ],
		] ),
		[ 'core/group', { className: 'aa-consent' }, [
			control( 'checkbox', 'regulamento', { required: true } ),
			label( 'regulamento', 'Li e concordo com o <a href="#">regulamento do Atelier Axell Club</a> e com o tratamento dos meus dados conforme a Política de Privacidade e a LGPD.', false ),
		] ],
		[ 'axell/form-submission-notification', { type: 'success' }, [
			[ 'core/paragraph', {}, 'Sua solicitação foi enviada.' ],
			[ 'core/paragraph', {}, 'A curadoria Axell entrará em contato em breve com o próximo passo. Bem-vindo(a) ao Atelier.' ],
		] ],
		[ 'axell/form-submission-notification', { type: 'error' }, [
			[ 'core/paragraph', {}, 'Não foi possível enviar sua solicitação. Tente novamente em instantes.' ],
		] ],
		[ 'core/group', { className: 'aa-submit-row' }, [
			[ 'core/buttons', {}, [
				[ 'core/button', { className: 'aa-btn-primary aa-btn-lg', tagName: 'button', type: 'submit', text: 'Enviar solicitação' } ],
			] ],
			[ 'core/paragraph', { className: 'aa-fine', content: 'Ao enviar, você concorda em receber comunicações do Atelier Axell Club. Cadastro sujeito à aprovação da curadoria Axell.' } ],
		] ],
	];

	blocks.registerBlockVariation( 'axell/form', {
		name: 'member-apply',
		title: __( 'Member Application Form', 'axellcore-atelierclub' ),
		description: __( 'The full Atelier Axell Club application form, pre-loaded with every field — the same content as the live /atelier page. A starting point for inserting a fresh copy elsewhere; the live page itself is still generated by bin/generate-content.py, not this variation.', 'axellcore-atelierclub' ),
		icon: 'groups',
		attributes: { submitsToRest: true, className: 'aa-apply-form' },
		innerBlocks: TEMPLATE,
		scope: [ 'inserter', 'transform' ],
	} );
} )( window.wp.blocks, window.wp.i18n );
