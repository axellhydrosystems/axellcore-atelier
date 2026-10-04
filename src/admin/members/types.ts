export interface MemberSummary {
	id: number;
	nome: string;
	escritorio: string;
	email: string;
	telefone: string;
	atuacao: string;
	uf: string;
	cidade: string;
	documento_mascarado: string;
	data: string;
	status: string;
}

export interface MemberDetail {
	id: number;
	nome: string;
	email: string;
	portfolio: string;
	uf: string;
	cidade: number | null;
	cidade_nome: string;
	data: string;
	status: string;
	escritorio: string;
	telefone: string;
	registro: string;
	atuacao: string;
	tipoDoc: string;
	documento: string;
	rua: string;
	numero: string;
	complemento: string;
	bairro: string;
	referencia: string;
	cep: string;
	loja1: string;
	loja2: string;
	loja3: string;
}

export interface SelectOption {
	value: string;
	label: string;
}

export interface MembersConfig {
	listUrl: string;
	editUrl: string;
	states: SelectOption[];
	atuacao: string[];
}

declare global {
	interface Window {
		aacMembers?: MembersConfig;
	}
}
