#!/usr/bin/env python3
"""Generates the Atelier Club's page/header/footer Gutenberg block markup.

Source of truth for content/seed-content.html, content/header-part.html,
and content/footer-part.html — those files are BUILD OUTPUT, not hand-edited
directly. Run this script after changing anything here:

    python3 bin/generate-content.py

Every helper below (group/columns/heading/paragraph/list_block/...) emits a
matched pair of a Gutenberg block comment (`<!-- wp:name {attrs} -->`) and
the literal saved HTML WordPress's own save() would recompute from those
attrs — the two must always match exactly, or the block editor flags the
content as invalid the moment someone opens the page.
"""

import json
import os

OUT = []

def esc_attrs(d):
    return json.dumps(d, ensure_ascii=False, separators=(",", ":"))

def group(className, inner_html_fn, extra_attrs=None, tag_name=None, layout=None):
    attrs = {"className": className} if className else {}
    if layout:
        attrs["layout"] = layout
    if tag_name:
        attrs["tagName"] = tag_name
    if extra_attrs:
        attrs.update(extra_attrs)
    tag = tag_name or "div"
    cls = "wp-block-group" + (f" {className}" if className else "")
    OUT.append(f'<!-- wp:group {esc_attrs(attrs) if attrs else "{}"} -->')
    OUT.append(f'<{tag} class="{cls}">')
    inner_html_fn()
    OUT.append(f'</{tag}>')
    OUT.append('<!-- /wp:group -->')

def columns(className, cols_fn, extra_attrs=None):
    attrs = {"className": className}
    if extra_attrs:
        attrs.update(extra_attrs)
    OUT.append(f'<!-- wp:columns {esc_attrs(attrs)} -->')
    OUT.append(f'<div class="wp-block-columns {className}">')
    cols_fn()
    OUT.append('</div>')
    OUT.append('<!-- /wp:columns -->')

def column(width_pct, inner_fn, className=""):
    attrs = {}
    if width_pct is not None:
        attrs["width"] = f"{width_pct}%"
    if className:
        attrs["className"] = className
    cls = "wp-block-column" + (f" {className}" if className else "")
    style = f' style="flex-basis:{width_pct}%"' if width_pct is not None else ""
    OUT.append(f'<!-- wp:column {esc_attrs(attrs) if attrs else "{}"} -->')
    OUT.append(f'<div class="{cls}"{style}>')
    inner_fn()
    OUT.append('</div>')
    OUT.append('<!-- /wp:column -->')

def heading(level, text, className=""):
    attrs = {"level": level}
    if className:
        attrs["className"] = className
    cls = "wp-block-heading" + (f" {className}" if className else "")
    OUT.append(f'<!-- wp:heading {esc_attrs(attrs)} -->')
    OUT.append(f'<h{level} class="{cls}">{text}</h{level}>')
    OUT.append('<!-- /wp:heading -->')

def paragraph(html, className=""):
    attrs = {}
    if className:
        attrs["className"] = className
    cls = "wp-block-paragraph" + (f" {className}" if className else "")
    OUT.append(f'<!-- wp:paragraph {esc_attrs(attrs) if attrs else "{}"} -->')
    OUT.append(f'<p class="{cls}">{html}</p>')
    OUT.append('<!-- /wp:paragraph -->')

def html_block(raw):
    OUT.append('<!-- wp:html -->')
    OUT.append(raw)
    OUT.append('<!-- /wp:html -->')

def list_block(items_html, className="", ordered=False, list_type=""):
    """list_type maps to core/list's own `type` attribute — the real HTML
    `type="i"` attribute on <ol>, which the browser numbers natively
    (lower-roman, etc.) with zero extra CSS. Prefer this over hand-writing
    numeral text in each item wherever the content genuinely is a sequential
    list."""
    tag = "ol" if ordered else "ul"
    attrs = {"ordered": True} if ordered else {}
    if list_type:
        attrs["type"] = list_type
    if className:
        attrs["className"] = className
    cls = "wp-block-list" + (f" {className}" if className else "")
    type_attr = f' type="{list_type}"' if list_type else ""
    OUT.append(f'<!-- wp:list {esc_attrs(attrs) if attrs else "{}"} -->')
    OUT.append(f'<{tag} class="{cls}"{type_attr}>')
    for item in items_html:
        OUT.append(f'<!-- wp:list-item -->')
        OUT.append(f'<li>{item}</li>')
        OUT.append(f'<!-- /wp:list-item -->')
    OUT.append(f'</{tag}>')
    OUT.append('<!-- /wp:list -->')

def buttons_block(className, inner_fn, layout=None):
    attrs = {"className": className} if className else {}
    if layout:
        attrs["layout"] = layout
    cls = "wp-block-buttons" + (f" {className}" if className else "")
    OUT.append(f'<!-- wp:buttons {esc_attrs(attrs) if attrs else "{}"} -->')
    OUT.append(f'<div class="{cls}">')
    inner_fn()
    OUT.append('</div>')
    OUT.append('<!-- /wp:buttons -->')

def button(text, className, url=None, tag_name=None, btn_type=None):
    """A single core/button. tagName defaults to 'a' (link); pass
    tag_name='button' for a real <button> (needed for the form submit
    control — an <a> click never fires a native 'submit' event, which our
    frontend.js relies on)."""
    attrs = {"className": className} if className else {}
    if tag_name:
        attrs["tagName"] = tag_name
    if btn_type:
        attrs["type"] = btn_type
    if url:
        attrs["url"] = url
    cls = "wp-block-button" + (f" {className}" if className else "")
    OUT.append(f'<!-- wp:button {esc_attrs(attrs)} -->')
    if tag_name == "button":
        OUT.append(f'<div class="{cls}"><button type="{btn_type or "button"}" class="wp-block-button__link wp-element-button">{text}</button></div>')
    else:
        OUT.append(f'<div class="{cls}"><a class="wp-block-button__link wp-element-button" href="{url or "#"}">{text}</a></div>')
    OUT.append('<!-- /wp:button -->')

def icon_block(icon_name):
    """core/icon — dynamic block (self-closing), backed by an icon
    registered via wp_register_icon() (see includes/class-icons.php)."""
    OUT.append(f'<!-- wp:icon {esc_attrs({"icon": icon_name})} /-->')

def notice_pill(icon_name, text, extra_cls=""):
    cls = f'aac-notice-pill aac-icon-{icon_name}' + (f' {extra_cls}' if extra_cls else '')
    def inner():
        icon_block(f'axellcore/{icon_name}')
        paragraph(text)
    group(cls, inner, layout={"type": "flex", "alignItems": "center"})

def chapters_block(chapters_fn):
    """axellcore/chapters — wraps the page's numbered chapters and
    establishes the CSS counter scope each axellcore/chapter increments."""
    OUT.append('<!-- wp:axellcore/chapters -->')
    OUT.append('<div class="wp-block-axellcore-chapters aac-chapters">')
    chapters_fn()
    OUT.append('</div>')
    OUT.append('<!-- /wp:axellcore/chapters -->')

def chapter_block(label, content_fn):
    """axellcore/chapter — one numbered chapter. `label` is the part after
    "Capítulo NN · " (e.g. "Manifesto"); the number itself is never stored
    here — it's CSS counter-generated (see .aac-chapter-tag::before in
    sections.css), so reordering chapters in the editor renumbers them
    automatically."""
    attrs = {"label": label}
    OUT.append(f'<!-- wp:axellcore/chapter {esc_attrs(attrs)} -->')
    OUT.append('<div class="wp-block-axellcore-chapter aac-chapter">')
    OUT.append(f'<p class="aac-chapter-tag"><span class="aac-chapter-label">{label}</span></p>')
    OUT.append('<div class="aac-chapter-body">')
    content_fn()
    OUT.append('</div>')
    OUT.append('</div>')
    OUT.append('<!-- /wp:axellcore/chapter -->')

def cta_link(text, href, className=""):
    """A plain inline link with the CSS ::after trailing-arrow treatment
    (assets/css/blocks-bridge.css's .aac-tier-cta::after) — for CTAs that
    are NOT pill-styled buttons (e.g. the tier card's text link)."""
    cls = f' class="{className}"' if className else ''
    return f'<a href="{href}"{cls}>{text}</a>'

# ============================================================
# NAV
# ============================================================
def nav_section():
    def inner():
        def logo():
            paragraph('Atelier Axell', 'aac-n1')
            paragraph('The Axell World', 'aac-n2')
        group('aac-nav-logo', logo, layout={"type": "flex", "orientation": "vertical", "flexWrap": "nowrap"})

        def links():
            for href, label in [
                ('#convite', 'Convite'), ('#placa', 'A Placa'), ('#jornada', 'Como Entrar'),
                ('#niveis', 'Níveis'), ('#beneficios', 'Benefícios'),
            ]:
                paragraph(f'<a href="{href}">{label}</a>')
            def cta():
                button('Solicitar adesão', 'aac-nav-cta aac-btn-primary', url='#adesao')
            buttons_block('', cta)
        group('aac-nav-links', links, layout={"type": "flex", "alignItems": "center"})
    group('aac-nav', inner, layout={"type": "flex", "justifyContent": "space-between", "alignItems": "center"})

# ============================================================
# HERO
# ============================================================
def hero_section():
    def inner():
        # Purely decorative, zero content (CSS background-image/gradient
        # layers) — an empty core/group shows a confusing "pick a layout"
        # placeholder in the editor for no benefit, so these three stay
        # minimal core/html (see also aac-placa-stone below).
        html_block('<div class="aac-hero-photo" role="img" aria-label="Suíte spa com banheira freestanding em pedra e vista noturna"></div>')
        html_block('<div class="aac-hero-overlay"></div>')

        def hero_inner_cols():
            def left():
                paragraph('Edição Lumière · 2026', 'aac-hero-cap')
                heading(1, 'O atelier é o lugar onde criar<br/>encontra <em>morar</em>.', 'aac-display')
                paragraph('Um clube <em>por convite</em>. Para arquitetos e designers que transformam o banho em obra, o spa em poesia e o projeto em memória.', 'aac-hero-quote')
                def ctas():
                    button('Solicitar adesão', 'aac-btn-primary aac-btn-lg', url='#adesao')
                    button('Ler o convite', 'aac-btn-ghost aac-btn-lg', url='#convite')
                buttons_block('aac-hero-ctas', ctas, layout={"type": "flex", "flexWrap": "wrap"})
            def right():
                def meta():
                    paragraph('"Você não entra.<br/>Você é recebido."', 'aac-stamp')
                    def kv(k, v):
                        def row():
                            paragraph(k, 'aac-k')
                            paragraph(v, 'aac-v')
                        group('aac-kv', row)
                    kv('Programa', 'Atelier Axell Club')
                    kv('Convite', 'Por seleção — apenas profissionais aprovados.')
                    kv('Três níveis', 'Signature · Alliance · Ambassador')
                group('aac-hero-meta', meta, tag_name='aside')
            column(None, left)
            column(None, right)
        columns('aac-hero-inner aac-container', hero_inner_cols)

        def footer_row():
            paragraph('The Axell World')
            paragraph('Um clube por curadoria')
        group('aac-hero-footer', footer_row, layout={"type": "flex", "justifyContent": "space-between"})
    group('aac-hero aac-reveal', inner)

# ============================================================
# CONVITE / MANIFESTO
# ============================================================
def manifesto_section():
    def cols():
        def side():
            heading(2, 'Não basta especificar. É preciso <em>sentir</em>.')
        def copy():
            paragraph('Há gestos no banho que merecem ser desenhados por <em>mãos especiais</em>.<br>Há projetos que pedem <em>cuidado de autor</em>.<br>E há profissionais que a Axell <em>reconhece por assinatura</em>.')
            paragraph('É preciso escolher como se escolhesse uma obra de arte para a própria casa — porque cada banheira, cada spa, cada detalhe carrega o gesto de quem escolheu. Arquitetos e designers escolhem por convicção. E reconhecemos isso.')
            paragraph('O Atelier Axell Club é um clube por seleção. Discreto. Curado. Feito para os poucos profissionais que desenham projetos extraordinários com peças Axell — e transformam o produto em memória.')
            paragraph('— Curadoria &amp; Assinatura Axell', 'aac-sig')
        column(None, side, className='aac-manifesto-side')
        column(None, copy, className='aac-manifesto-copy')
    columns('aac-manifesto-block', cols)

# ============================================================
# 4 PILARES
# ============================================================
PILLARS = [
    ('Reconhecimento', 'Selo de autoria para quem desenha ambientes extraordinários. Sua assinatura, valorizada pela marca.'),
    ('Recompensa', 'Bônus financeiro direto por cada venda concretizada. A parceria em números, com transparência.'),
    ('Experiência', 'Viagens, jantares e curadoria à altura do talento. Momentos que alimentam repertório.'),
    ('Visibilidade', 'Os seus projetos ganham a voz autoral da Axell — publicação editorial, imprensa e redes.'),
]

def section_head(title, body):
    def inner():
        def left():
            heading(2, title, 'aac-section-title')
        def right():
            paragraph(body, 'aac-body')
        column(None, left, className='aac-head-left')
        column(None, right, className='aac-head-right')
    columns('aac-section-head aac-reveal', inner)

def pillars_section():
    def head_wrap():
        section_head('Quatro pilares. Uma <em>assinatura</em>.',
                      'Arquitetos e designers escolhem por convicção — e o clube foi construído sobre quatro pilares que devolvem essa convicção em forma de <em>reconhecimento</em>, <em>recompensa</em>, <em>experiência</em> e <em>visibilidade</em>.')
    def outer():
        group('aac-container', head_wrap)
        # A genuine <ol type="i"> — the browser numbers each <li> natively
        # (no hand-typed numeral, no CSS counter needed); the heading/body
        # pair becomes inline <strong>/<span> content inside the single
        # rich-text <li>, since core/list-item only allows a nested core/list
        # as a block child, not arbitrary blocks like core/heading.
        items = [f'<strong>{title}</strong><br><span>{body}</span>' for title, body in PILLARS]
        list_block(items, className='aac-pillars', ordered=True, list_type='i')
    group('aac-on-ink', outer)

# ============================================================
# A PLACA
# ============================================================
def placa_section():
    def cols():
        def copy():
            heading(2, 'A placa do <em>membro</em>.', 'aac-section-title')
            notice_pill('lock', 'Concedida por categorização')
            paragraph('A entrega da placa depende do estágio de categorização alcançado pelo profissional dentro do clube. Ela é conquistada, não distribuída — nasce quando a curadoria Axell reconhece a maturidade da parceria.', 'aac-notice-caption')
            paragraph('Uma peça <em>única</em>, uma obra de arte em pedra. Gravada com o nome do profissional e o selo ATELIER AXELL em letras finas e delgadas — à altura do detalhe.', 'aac-lede')
            list_block([
                '<span class="aac-k">Abstratismo</span><span class="aac-v">Cada peça traz um desenho abstrato único, criado pela curadoria Axell. Nenhum motivo se repete.</span>',
                '<span class="aac-k">Exclusividade</span><span class="aac-v">Nenhum membro recebe o mesmo desenho. Cada placa nasce com o nome do arquiteto — não é fabricada, é dedicada.</span>',
                '<span class="aac-k">Entrega</span><span class="aac-v">Após aprovação do cadastro, em até 30 dias, em embalagem premium do clube.</span>',
            ], className='aac-placa-specs')
        def visual():
            html_block('<div class="aac-placa-stone"></div>')
            def plate():
                paragraph('Atelier · Axell', 'aac-pp-top')
                paragraph('[Seu nome]<span>Membro Atelê</span>', 'aac-pp-name')
                def bottom():
                    paragraph('Nº única')
                    paragraph('MMXXVI')
                group('aac-pp-bottom', bottom, layout={"type": "flex", "justifyContent": "space-between"})
            group('aac-placa-plate', plate)
        column(None, copy, className='aac-placa-copy aac-reveal')
        column(None, visual, className='aac-placa-visual aac-reveal')
    columns('aac-placa aac-section', cols)

# ============================================================
# PROTAGONISTAS
# ============================================================
PROTAGONISTS = [
    ('Vocês assinam ambientes', 'Detalham o projeto, escolhem o produto, validam o ritual. A mão de vocês está em cada momento — do primeiro croqui ao último acabamento.'),
    ('Vocês educam o cliente', 'Recomendam com curadoria, esclarecem diferenças técnicas, fecham a especificação antes da compra. Vocês são a voz de confiança.'),
    ('Vocês constroem a marca', 'Cada projeto é um capítulo de conteúdo. Vocês são a voz técnica e autoral da Axell no campo — a marca fala através das obras.'),
]

def protagonists_section():
    def outer():
        def head_wrap():
            section_head('O arquiteto e o <em>designer</em>.',
                          'A Axell reconhece os profissionais que transformam banheiras e spas em <em>momentos extraordinários</em>. Antes da venda, existe o olhar que escolhe. Antes do gesto, existe a mão que desenha.')
        group('aac-container', head_wrap)
        items = [f'<strong>{title}</strong><br><span>{body}</span>' for title, body in PROTAGONISTS]
        list_block(items, className='aac-prota-grid', ordered=True, list_type='i')
    group('aac-protagonists aac-on-ivory aac-section', outer)

# ============================================================
# 4 PROMESSAS
# ============================================================
PROMISES = [
    ('Recompensa real', 'Bônus financeiro direto por cada venda concretizada, sem intermediários. Transparência de valor.'),
    ('Curadoria constante', 'Eventos, viagens e encontros desenhados ao detalhe — do jantar íntimo à imersão internacional.'),
    ('Visibilidade editorial', 'Os seus projetos ganham a voz autoral da Axell — publicação em canais oficiais e imprensa parceira.'),
    ('Pertencimento', 'Lugar entre os poucos profissionais que a Axell reconhece por assinatura. Discreto, curado, permanente.'),
]

def promises_section():
    def outer():
        def head_wrap():
            section_head('Quatro promessas. Uma <em>assinatura</em>: Axell.',
                          'Ser membro do Atelier Axell é ocupar um lugar seleto entre os profissionais que desenham o bem-estar brasileiro — uma comunidade discreta, curada pela Axell.')
        group('aac-container', head_wrap)
        items = [f'<strong>{title}</strong><br><span>{body}</span>' for title, body in PROMISES]
        list_block(items, className='aac-promises-grid', ordered=True, list_type='i')
    group('aac-promises aac-section-sm', outer)

# ============================================================
# O NOME / CONCEITO
# ============================================================
CONCEPT_ROWS = [
    ('Espaço', 'Espaço de <em>criação</em> — não sala de exposição.'),
    ('Postura', 'Você não é <em>cliente</em>. É autor.'),
    ('Método', 'Curadoria e <em>ofício</em>. Prática, não só compra.'),
    ('Escala', 'Oficina íntima. Pertence a <em>poucos</em>, com profundidade.'),
    ('Origem', 'Herança francesa. Estética editorial, museu, <em>maison</em>.'),
    ('Marca', 'Axell como <em>origem</em> — não como patrocinador.'),
]

def concept_section():
    def cols():
        def left():
            heading(2, 'Um espaço <em>exclusivo</em>.', 'aac-section-title')
            paragraph('"O atelier é o lugar onde <em>criar</em> encontra <em>morar</em>. É oficina de começar, é o altar de arquitetar. Não é prédio nem morada — é gesto, é sonho, é criar."', 'aac-verse')
        def right():
            paragraph('Por que Atelier', 'aac-chapter-tag')
            def rows():
                for k, v in CONCEPT_ROWS:
                    def row(k=k, v=v):
                        paragraph(k, 'aac-k')
                        paragraph(v, 'aac-v')
                    group('aac-row', row)
            group('aac-concept-table', rows)
        column(None, left, className='aac-reveal')
        column(None, right, className='aac-reveal')
    columns('aac-concept aac-section aac-on-ivory', cols)

# ============================================================
# JORNADA
# ============================================================
JOURNEY = [
    ('Solicitação', 'Acesse a página do clube', 'Você entra na área Atelier Axell dentro do site oficial e inicia sua solicitação de adesão.'),
    ('Cadastro', 'Conte sobre sua autoria', 'Nome, escritório, CAU/CREA, portfólio, endereço, CPF/CNPJ, lojas parceiras e o regulamento assinado.'),
    ('Curadoria', 'Aprovação Axell', 'A equipe Axell lê, entende o seu perfil e libera o acesso — com o cuidado que a autoria merece.'),
    ('Onboarding', 'Recepção ao clube', 'Recebe e-mail de boas-vindas. Cria usuário e senha e passa a acessar todo o conteúdo do Atelier.'),
]

def journey_section():
    def inner():
        def head_wrap():
            section_head('Quatro passos <em>até o clube</em>.',
                          'Uma jornada simples, elegante e respeitosa com seu tempo. Sem burocracia — apenas curadoria.')
        head_wrap()
        def steps():
            for cap, title, body in JOURNEY:
                def item(cap=cap, title=title, body=body):
                    paragraph(cap, 'aac-cap')
                    heading(4, title)
                    paragraph(body)
                group('aac-jstep aac-reveal', item)
        group('aac-journey-steps', steps)
        paragraph('"Você não entra. <em>Você é recebido</em>."', 'aac-journey-quote')
    group('aac-journey aac-section aac-container', inner)

# ============================================================
# NIVEIS (TIERS)
# ============================================================
def tiers_section():
    def outer():
        def head_wrap():
            section_head('Três níveis. Uma <em>assinatura</em>.',
                          'Todo membro começa no nível <em>Signature</em>. Os dois estágios seguintes — <em>Alliance</em> e <em>Ambassador</em> — são conquistas, não escolhas. Seus benefícios são revelados quando a curadoria Axell reconhece a maturidade da parceria. Cada capítulo, a seu tempo.')
        head_wrap()

        def grid():
            def signature():
                paragraph('Entrada no clube', 'aac-tier-cap')
                heading(3, 'Signature', 'aac-tier-name')
                paragraph('o primeiro convite', 'aac-tier-italic')
                list_block([
                    'Área exclusiva do clube',
                    'Cadastro de projetos',
                    'Materiais e biblioteca técnica',
                    'Convites para treinamentos',
                    'Newsletter editorial',
                    'Bônus financeiro por cada especificação de produto Axell revertida em venda',
                ])
                paragraph(cta_link('Começar por aqui', '#adesao', 'aac-tier-cta'))
            group('aac-tier aac-reveal', signature)

            def mystery(cap, name, italic, line, hint):
                def content():
                    paragraph(cap, 'aac-tier-cap')
                    heading(3, name, 'aac-tier-name')
                    paragraph(italic, 'aac-tier-italic')
                    def lock_mark():
                        icon_block('axellcore/lock')
                        paragraph('Revelado após conquista')
                    group('aac-lock-mark', lock_mark, layout={"type": "flex", "alignItems": "center"})
                    def body():
                        paragraph(line, 'aac-mystery-line')
                    group('aac-mystery-body', body)
                    paragraph(hint, 'aac-mystery-hint')
                group('aac-tier aac-mystery aac-reveal', content)

            mystery('Segundo estágio', 'Alliance', 'a ser conquistado',
                    'Há um <em>segundo capítulo</em> reservado a quem transforma o hábito em consistência. Seus benefícios são desvelados quando o membro alcança o estágio.',
                    'Conquistado pela curadoria Axell')
            mystery('Terceiro estágio', 'Ambassador', 'o círculo mais estreito',
                    'O <em>topo entre prescritores</em>. Um convite reservado, com benefícios que só se descobrem ao chegar. A curadoria Axell guarda o silêncio até lá.',
                    'Um capítulo por vez')
        group('aac-tiers-grid', grid)
    group('aac-tiers aac-section aac-on-ivory aac-container', outer)

# ============================================================
# BENEFICIOS
# ============================================================
BENEFITS = [
    ('Área exclusiva', 'Histórico de reconhecimento, projetos cadastrados e campanhas ativas em painel privado.'),
    ('Materiais premium', 'Catálogos, blocos CAD, manuais técnicos e biblioteca completa de especificações.'),
    ('Apoio prioritário', 'Atendimento com consultor Axell dedicado ao seu escritório, canal direto.'),
    ('Consultoria de produto', 'Suporte técnico especializado para banheiras, spas e linhas completas.'),
    ('Lançamentos antecipados', 'Conhecimento de novidades antes do mercado, com pré-venda e amostras.'),
    ('Treinamentos exclusivos', 'Convites para workshops, aulas técnicas e imersões de produto na fábrica.'),
    ('Selo digital de membro', 'Selo oficial Atelier Axell Club para site, redes sociais e materiais do escritório.'),
    ('Diretório de parceiros', 'Destaque em diretório público Axell com link direto para o seu portfólio.'),
    ('Placa de membro', 'Peça única, uma obra de arte em pedra, gravada com o nome do arquiteto.'),
]
PRIZES = ['Vouchers & gift cards', 'Produtos Axell', 'Experiências gastronômicas', 'Hospedagens premium', 'Viagens & upgrades', 'Feiras & eventos']

def benefits_section():
    def outer():
        def head_wrap():
            section_head('Recompensas à altura do <em>talento</em>.',
                          'Do bônus financeiro à placa em pedra, dos lançamentos antecipados às viagens internacionais — cada benefício foi desenhado para respeitar o ofício, o tempo e a influência do autor.')
        group('aac-container', head_wrap)

        # Zero-padded (01, 02…) isn't a native <ol type> value, but the
        # marker is still browser-generated, not hand-typed: ::marker{content:
        # counter(list-item, decimal-leading-zero)} in sections.css overrides
        # the displayed digits while the numbering itself stays native.
        items = [f'<strong>{title}</strong><br><span>{body}</span>' for title, body in BENEFITS]
        list_block(items, className='aac-benefits-grid', ordered=True)

        def prizes_wrap():
            def prizes_inner():
                paragraph('Premiações do clube', 'aac-eyebrow')
                def chips():
                    for p in PRIZES:
                        paragraph(p, 'aac-prize-chip')
                group('aac-prizes-list', chips, layout={"type": "flex", "flexWrap": "wrap"})
                notice_pill('clock', 'Concedidas por categorização')
                paragraph('As recompensas do clube são liberadas de acordo com o estágio de categorização alcançado pelo profissional. Cada nível abre um novo repertório de benefícios — e mantém os anteriores como base permanente.', 'aac-notice-caption')
            group('aac-container', prizes_inner)
        group('aac-benefits-prizes', prizes_wrap)
    group('aac-benefits', outer)

# ============================================================
# EDITORIAL
# ============================================================
EDIT_PATH = [
    'Cadastro do projeto na sua conta',
    'Curadoria editorial Axell',
    'Fotografia ambientada e entrevista',
    'Publicação multicanal Axell',
    'Destaque em campanhas institucionais',
]
CHANNELS = [
    ('Instagram Axell', 'Feed principal'), ('Instagram Atelier', 'Canal do clube'),
    ('Reels & vídeo', 'Motion editorial'), ('Pinterest', 'Curadoria visual'),
    ('Site editorial', 'Portfolio membros'), ('Newsletter', 'Mensal'),
    ('Catálogo impresso', 'Anual'), ('Imprensa parceira', 'Curadoria PR'),
]

def editorial_section():
    def cols():
        def left():
            heading(2, 'Seu projeto, com a voz <em>Axell</em>.', 'aac-section-title')
            paragraph('A Axell transforma o seu case em conteúdo editorial premium. Ganho duplo: a marca ganha prova social, e o parceiro ganha autoridade técnica e visibilidade qualificada.', 'aac-body')
            paragraph('Percurso editorial', 'aac-chapter-tag')
            list_block(EDIT_PATH, className='aac-edit-path', ordered=True, list_type='i')
        def right():
            paragraph('Onde os cases aparecem', 'aac-chapter-tag')
            def grid():
                for name, kind in CHANNELS:
                    def card(name=name, kind=kind):
                        paragraph(name, 'aac-name')
                        paragraph(kind, 'aac-kind')
                    group('aac-channel', card, layout={"type": "flex", "justifyContent": "space-between"})
            group('aac-channels-grid', grid)
        column(None, left, className='aac-reveal')
        column(None, right, className='aac-channels aac-reveal')
    columns('aac-editorial aac-section', cols)
    def quote():
        paragraph('"Cada projeto é um <em>capítulo de conteúdo</em>. Cada assinatura, um capítulo da marca."', 'aac-editorial-quote')
    group('', quote)

# ============================================================
# CTA STRIP
# ============================================================
def cta_strip_section():
    def inner():
        heading(2, 'Há profissionais que transformam o banho em <em>obra</em>, o spa em <em>poesia</em>, e o projeto em <em>memória</em>. Solicite sua adesão.')
        def cta():
            button('Quero fazer parte', 'aac-btn-primary aac-btn-lg', url='#adesao')
        buttons_block('', cta)
    group('aac-cta-strip-inner aac-container', inner)

# ============================================================
# FOOTER
# ============================================================
FOOTER_COLUMNS = [
    ('O Clube', [('#convite', 'Convite'), ('#placa', 'A Placa'), ('#niveis', 'Níveis'), ('#beneficios', 'Benefícios')]),
    ('Contato', [(None, 'atelier@axell.com.br'), (None, '0800 000 0000'), (None, 'Seg. a Sex. 09h — 18h')]),
    ('Institucional', [('#', 'Sobre a Axell'), ('#', 'Showrooms'), ('#', 'Política de privacidade'), ('#', 'Regulamento do clube')]),
]

def footer_section():
    def container():
        def grid():
            def brand():
                def logo():
                    paragraph('Atelier Axell', 'aac-n1')
                    paragraph('The Axell World', 'aac-n2')
                group('aac-nav-logo', logo, layout={"type": "flex", "orientation": "vertical", "flexWrap": "nowrap"})
                paragraph('O atelier é o lugar onde criar encontra morar. Um clube por convite para arquitetos e designers que assinam o bem-estar brasileiro.')
            group('aac-footer-brand', brand)

            for title, links in FOOTER_COLUMNS:
                def col(title=title, links=links):
                    heading(5, title)
                    items = [f'<a href="{href}">{label}</a>' if href else label for href, label in links]
                    list_block(items)
                group('', col)
        group('aac-footer-grid', grid, layout={"type": "grid", "columnCount": 4})

        def bottom():
            paragraph('© 2026 Axell. Todos os direitos reservados.')
            paragraph('Atelier Axell Club · Edição Lumière · 2026', 'aac-edition')
        group('aac-footer-bottom', bottom, layout={"type": "flex", "justifyContent": "space-between"})
    def inner():
        group('aac-container', container)
    group('aac-footer', inner)

# ============================================================
# APPLY FORM (axell/form, axell/form-fieldset, axell/form-label,
# axell/form-control, axell/form-submission-notification)
# ============================================================
# axellcore/form-input (the old fused block) is deprecated — kept
# registered for backward compatibility (includes/blocks/form/form-input/
# block.json's "inserter":false) but no longer used to generate content.
# See this plugin's CLAUDE.md for the full split rationale and the real
# technical constraint found while researching an automatic migration path
# (Gutenberg's deprecated/migrate API can't turn one block into sibling
# blocks of a different name — confirmed against core/gallery's own
# deprecated.js). Every HTML shape below was captured byte-for-byte from
# the real blocks via `wp.blocks.getSaveContent()` in the browser (same
# verification discipline as the rest of this generator), not hand-derived.

def _control_html(type_, id_, name, required, placeholder, mask, mask_source, options, checked, value, cities_source=""):
    """Python port of form-control/index.js's ControlElement(attrs, isSave=true).
    `common` mirrors the JS's own `common` object exactly — id/name/required/
    aria-required/placeholder/mask/mask-source/cities-source+disabled are all
    merged in unconditionally of type (mirroring the JS building one shared
    `common` object before branching on type), then each branch only adds
    the tag itself and, for input-like types, a trailing `type="…"`."""
    if type_ == 'hidden':
        return f'<input type="hidden" name="{name}" value="{value}"/>'

    common = f' class="wp-block-axell-form-control" id="{id_}" name="{name}"'
    if required:
        common += ' required aria-required="true"'
    if placeholder:
        common += f' placeholder="{placeholder}"'
    if mask:
        common += f' data-aac-mask="{mask}"'
    if mask_source:
        common += f' data-aac-mask-source="{mask_source}"'
    if cities_source:
        # Starts empty (frontend.js populates it once the source field has
        # a value) — disabled until then.
        common += f' data-aac-cities-source="{cities_source}" disabled'

    if type_ == 'textarea':
        return f'<textarea{common}></textarea>'

    if type_ == 'select':
        opts = f'<option value="">{placeholder or "Select an option"}</option>'
        for o in (options or []):
            opts += f'<option value="{o["value"]}">{o["label"]}</option>'
        return f'<select{common}>{opts}</select>'

    if type_ == 'checkbox':
        chk = ' checked' if checked else ''
        return f'<input{common}{chk} type="checkbox"/>'

    return f'<input{common} type="{type_}"/>'

def form_control(type_, id_, name="", required=False, placeholder="", mask="", mask_source="", options=None, checked=False, value="", cities_source=""):
    """axell/form-control — the actual input/select/textarea. `name` falls
    back to `id_` (already unique across the whole form)."""
    name = name or id_
    attrs = {"type": type_, "id": id_}
    if name != id_:
        attrs["name"] = name
    if required:
        attrs["required"] = True
    if placeholder:
        attrs["placeholder"] = placeholder
    if value:
        attrs["value"] = value
    if checked:
        attrs["checked"] = True
    if options:
        attrs["options"] = options
    if mask:
        attrs["mask"] = mask
    if mask_source:
        attrs["maskSourceName"] = mask_source
    if cities_source:
        attrs["citiesSourceName"] = cities_source

    html = _control_html(type_, id_, name, required, placeholder, mask, mask_source, options, checked, value, cities_source)
    OUT.append(f'<!-- wp:axell/form-control {esc_attrs(attrs)} -->')
    OUT.append(html)
    OUT.append('<!-- /wp:axell/form-control -->')

def form_label(for_, text, required=False, visually_hidden=False):
    """axell/form-label — `label` not included in the comment JSON:
    block.json declares it `source:"rich-text"` (see the historical note
    that used to live on form_input() — same reasoning, same escaped-quote
    parser bug avoided by keeping rich-text-sourced attributes out of the
    JSON blob)."""
    attrs = {"for": for_}
    if required:
        attrs["required"] = True
    if visually_hidden:
        attrs["visuallyHidden"] = True

    cls = 'wp-block-axell-form-label' + ( ' is-visually-hidden' if visually_hidden else '' )
    req_span = '<span class="aac-req"> *</span>' if required else ''
    html = f'<label class="{cls}" for="{for_}"><span class="aac-field-label-text">{text}</span>{req_span}</label>'

    OUT.append(f'<!-- wp:axell/form-label {esc_attrs(attrs)} -->')
    OUT.append(html)
    OUT.append('<!-- /wp:axell/form-label -->')

def field(type_, name, label, required=False, placeholder="", hint="", options=None, mask="", mask_source="", value="", checked=False, cities_source="", visually_hidden=False):
    """The atomic 'field' unit: a core/group.aac-field wrapping a
    axell/form-label + axell/form-control pair (`id`/`name` both = `name`,
    already unique across the whole form — verified). Reuses
    assets/css/sections.css's existing `.aac-field`/`.aac-req`/`.aac-hint`
    rules completely unchanged — they're descendant selectors (`.aac-field
    label`, `.aac-field input`), indifferent to which block renders the
    actual `<label>`/`<input>`, as long as the DOM shape stays the same."""
    def inner():
        form_label(name, label, required, visually_hidden)
        form_control(type_, name, required=required, placeholder=placeholder, mask=mask, mask_source=mask_source, options=options, checked=checked, value=value, cities_source=cities_source)
        if hint:
            paragraph(hint, 'aac-hint')
    group('aac-field', inner)

def consent_field(name, label, required=False):
    """The consent checkbox — control-then-label composition (checkbox
    first, then the label with its embedded link), replacing the old
    `variant="consent"` special case. Reuses `.aac-consent` unchanged."""
    def inner():
        form_control('checkbox', name, required=required)
        form_label(name, label, required=False)
    group('aac-consent', inner)

def form_row(className, fields_fn):
    """A core/group wrapping a row of `field()` groups — unchanged from
    before (still `.aac-form-row`/`.aac-cols-*`, still a real block-tree
    child of axell/form-fieldset's InnerBlocks)."""
    group('aac-form-row ' + className, fields_fn)

def fieldset(legend_text, fields_fn):
    """axell/form-fieldset — a real `<fieldset>`/`<legend>` pair, replacing
    the old `core/group.aac-form-row` + separate `.aac-form-legend`-styled
    paragraph. `.aac-apply-form fieldset`/`.aac-apply-form legend` in
    sections.css already target plain element selectors (ported from the
    original mockup, unused until now) — no new CSS needed.

    `legend` is intentionally NOT included in the comment JSON — same
    reasoning as form_label()'s `text`/the old form_input()'s `label`:
    block.json declares it `source:"rich-text"`, so WordPress derives it
    from the stored HTML itself; duplicating it into the JSON blob is
    redundant and, for any future legend containing a quote/embedded HTML,
    a repeat of the escaped-quote block-comment parser bug already found
    and fixed once this session."""
    legend_html = f'<legend class="aac-form-legend-text">{legend_text}</legend>' if legend_text else ''
    OUT.append('<!-- wp:axell/form-fieldset {} -->')
    OUT.append('<fieldset class="wp-block-axell-form-fieldset">' + legend_html)
    fields_fn()
    OUT.append('</fieldset>')
    OUT.append('<!-- /wp:axell/form-fieldset -->')

def form_notification(type_, paragraphs_html):
    """axell/form-submission-notification — starts hidden
    (assets/css/sections.css's .aac-notice), toggled visible by
    assets/js/frontend.js's showFormNotice() once the REST fetch() resolves.
    Replaces the old window.alert()-based feedback; see
    includes/blocks/form/form-submission-notification/index.js's header
    comment for the heritage note (ported from Gutenberg's removed
    core/form-submission-notification block, adapted for this form's
    no-page-reload submission)."""
    attrs = {"type": type_}
    cls = f'wp-block-axell-form-submission-notification aac-notice aac-notice-{type_}'
    OUT.append(f'<!-- wp:axell/form-submission-notification {esc_attrs(attrs)} -->')
    OUT.append(f'<div class="{cls}" data-aac-notice-type="{type_}">')
    for html in paragraphs_html:
        paragraph(html)
    OUT.append('</div>')
    OUT.append('<!-- /wp:axell/form-submission-notification -->')

def apply_section():
    def cols():
        def side():
            paragraph('Solicitar adesão', 'aac-eyebrow')
            heading(2, 'Sua autoria tem <em>nome e forma</em>.', 'aac-section-title')
            paragraph('O convite está aberto. Preencha seu cadastro — a curadoria Axell responde com atenção a cada profissional. Após a aprovação, seu acesso ao clube é liberado e a placa começa a ser gravada.', 'aac-lede')
            list_block([
                'Cadastro sujeito à aprovação da curadoria Axell.',
                'Área exclusiva liberada após validação do perfil profissional.',
                'Benefícios, bônus e campanhas seguem as regras vigentes do programa.',
                'A placa é entregue em até 30 dias após aprovação.',
                'Seus dados são tratados conforme a LGPD.',
            ], className='aac-apply-terms', ordered=True, list_type='i')
        column(None, side, className='aac-apply-side aac-reveal')

        def form_col():
            # axell/form's save() renders `<form>{innerBlocks}</form>` from
            # blockProps — every child here MUST be a real block (field() /
            # fieldset() / group / html_block), never raw fieldset/div text,
            # or the editor's save()-recomputation would mismatch the
            # hand-seeded HTML and flag the block as invalid the moment
            # someone opens this page.
            OUT.append(f'<!-- wp:axell/form {esc_attrs({"submitsToRest": True, "className": "aac-apply-form"})} -->')
            OUT.append('<form class="wp-block-axell-form aac-apply-form" data-aac-club-form novalidate="">')

            def section1():
                def row1a():
                    field('text', 'nome', 'Nome completo', True, 'Como devemos chamá-lo(a)?')
                    field('text', 'escritorio', 'Escritório / Atelê', True, 'Nome do escritório')
                form_row('aac-cols-2', row1a)
                def row1b():
                    field('email', 'email', 'E-mail profissional', True, 'voce@escritorio.com.br')
                    field('tel', 'telefone', 'Telefone', True, '(11) 90000-0000', mask='phone')
                    field('text', 'registro', 'Registro (CAU / CREA / ABD)', False, 'A00000-0')
                form_row('aac-cols-3', row1b)
                def row1c():
                    field('select', 'atuacao', 'Atuação principal', True, placeholder='Selecione uma opção', options=[
                        {"label": "Arquitetura residencial de alto padrão", "value": "Arquitetura residencial de alto padrão"},
                        {"label": "Design de interiores", "value": "Design de interiores"},
                        {"label": "Arquitetura corporativa / hospitalidade", "value": "Arquitetura corporativa / hospitalidade"},
                        {"label": "Wellness · Spa · Hotelaria", "value": "Wellness · Spa · Hotelaria"},
                        {"label": "Outros", "value": "Outros"},
                    ])
                    field('url', 'portfolio', 'Portfólio (URL)', False, 'https://…', hint='Site, Instagram, Behance ou drive com projetos.')
                form_row('aac-cols-2', row1c)
            fieldset('01 — Autoria', section1)

            def section2():
                def row2():
                    field('select', 'tipoDoc', 'Tipo de cadastro', True, placeholder='Selecione', options=[
                        {"label": "Pessoa Física · CPF", "value": "cpf"},
                        {"label": "Pessoa Jurídica · CNPJ", "value": "cnpj"},
                    ])
                    field('text', 'documento', 'CPF ou CNPJ', True, '000.000.000-00 / 12.ABC.345/01DE-35',
                                hint='Utilizado para emissão de bônus e nota fiscal. CNPJ alfanumérico é aceito.', mask='cpf-cnpj', mask_source='tipoDoc')
                form_row('aac-cols-2', row2)
            fieldset('02 — Documento', section2)

            def section3():
                def row3a():
                    field('text', 'rua', 'Logradouro', True, 'Rua, Avenida, Alameda…')
                    field('text', 'numero', 'Número', True, '000')
                    field('text', 'complemento', 'Complemento', False, 'Sala, andar, conjunto')
                form_row('aac-cols-addr', row3a)
                def row3b():
                    field('text', 'bairro', 'Bairro', True, 'Bairro')
                    field('text', 'referencia', 'Referência', False, 'Próximo a…')
                form_row('aac-cols-2', row3b)
                def row3c():
                    # UF drives the Cidade select: choosing a state fetches
                    # and populates that state's cities (assets/js/frontend.js,
                    # GET /axellcore-atelierclub/v1/cities?uf=XX — data adapted
                    # from fervidum/f9brcities, see includes/data/br-*.php).
                    # UF must come first so it's usable before Cidade exists.
                    uf_options = [{"label": s, "value": s} for s in
                        ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO']]
                    field('select', 'uf', 'UF', True, placeholder='—', options=uf_options)
                    field('select', 'cidade', 'Cidade', True, placeholder='Selecione o estado', cities_source='uf')
                    field('text', 'cep', 'CEP', True, '00000-000', mask='cep')
                form_row('aac-cols-city', row3c)
            fieldset('03 — Endereço do escritório', section3)

            def section4():
                def partners():
                    paragraph('Onde você costuma especificar Axell?', 'aac-field-label-text')
                    paragraph('Liste até <strong style="color:var(--bronze-3);font-weight:500">cinco</strong> revendas ou showrooms parceiros com quem você trabalha. Preencha apenas o que fizer sentido — os campos vazios podem ficar em branco.', 'aac-hint')
                    # The 5 partner-store inputs stay as core/html: they're
                    # plain optional <input name="lojaN"> fields with no
                    # visible <label> — a shape axell/form-control/
                    # axell/form-label don't produce together as one unit,
                    # and not worth a dedicated field for 5 rarely-edited
                    # placeholder strings. They ARE a genuine sequential
                    # list though, so it's a real <ol type="i"> here — the
                    # browser numbers each <li> natively (i., ii., iii.…),
                    # not a hand-typed numeral span.
                    slots = ''.join(
                        f'<li class="aac-partner-slot"><input type="text" name="loja{i}" placeholder="Nome da loja · cidade"></li>'
                        for i in range(1, 6)
                    )
                    html_block(f'<ol type="i" class="aac-partner-slots">{slots}</ol>')
                group('aac-field', partners)
            fieldset('04 — Lojas parceiras', section4)

            consent_field('regulamento', 'Li e concordo com o <a href="#">regulamento do Atelier Axell Club</a> e com o tratamento dos meus dados conforme a Política de Privacidade e a LGPD.', required=True)

            form_notification('success', [
                'Sua solicitação foi enviada.',
                'A curadoria Axell entrará em contato em breve com o próximo passo. Bem-vindo(a) ao Atelier.',
            ])
            form_notification('error', [
                'Não foi possível enviar sua solicitação. Tente novamente em instantes.',
            ])

            def submit_row():
                # A real <button type="submit"> — core/button's default
                # tagName="a" would never fire a native 'submit' event, which
                # assets/js/frontend.js relies on to intercept the form.
                def submit_btn():
                    button('Enviar solicitação', 'aac-btn-primary aac-btn-lg', tag_name='button', btn_type='submit')
                buttons_block('', submit_btn)
                paragraph('Ao enviar, você concorda em receber comunicações do Atelier Axell Club. Cadastro sujeito à aprovação da curadoria Axell.', 'aac-fine')
            group('aac-submit-row', submit_row)

            OUT.append('</form>')
            OUT.append('<!-- /wp:axell/form -->')
        column(None, form_col)
    columns('aac-apply aac-section aac-container', cols)

# ============================================================
# ASSEMBLE — nav/footer go to their own FSE Template Parts (editable via
# the Site Editor's Patterns > Template Parts UI, per the user's explicit
# request); the page's own post_content is just the in-between sections —
# the template references the parts via core/template-part around
# <!-- wp:post-content /-->.
# ============================================================
# This script lives at <plugin>/bin/generate-content.py — plugin root is its
# grandparent directory. Kept relative (not hardcoded to any one machine)
# specifically so this can live in the repo instead of a throwaway scratch
# directory.
PLUGIN_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

nav_section()
header_part = list(OUT)
OUT.clear()

CHAPTERS = [
    ('Convite', manifesto_section),
    ('Manifesto', pillars_section),
    ('A Placa', placa_section),
    ('Protagonistas', protagonists_section),
    ('Proposta', promises_section),
    ('O Nome', concept_section),
    ('Como Entrar', journey_section),
    ('Níveis', tiers_section),
    ('Benefícios', benefits_section),
    ('Editorial', editorial_section),
]

hero_section()
def all_chapters():
    for label, section_fn in CHAPTERS:
        chapter_block(label, section_fn)
chapters_block(all_chapters)
cta_strip_section()
apply_section()
page_content = list(OUT)
OUT.clear()

footer_section()
footer_part = list(OUT)
OUT.clear()

with open(f'{PLUGIN_DIR}/content/seed-content.html', 'w', encoding='utf-8') as f:
    f.write('\n'.join(page_content) + '\n')

print(f"wrote seed-content.html: {len(page_content)} lines")
