#!/usr/bin/env python3
"""Generates content/noclass-nav-hero.html — a proof-of-concept slice of the
/atelier-noclass experiment (nav + hero, the two hardest sections: fixed
positioning, scroll-driven state, fluid typography) rebuilt with ZERO
`aac-`-prefixed CSS classes. Every visual property is a native core-block
style attribute (color/typography/spacing/border/position) instead.

Two block-supports processing paths matter here (confirmed by reading
wp-includes/block-supports/*.php directly):
  - color/typography/spacing/border have NO render_block hook at all — they
    are purely editor-side (JS save()), meaning whatever inline style="..."
    we hand-author IS exactly what the frontend renders, unmodified. We
    don't need to byte-match WordPress's own internal formatting — just
    write valid CSS achieving the intended look, and keep the JSON attrs
    reasonably faithful so the editor's own recompute doesn't flag it.
  - position DOES hook render_block (wp_render_position_support()) — WP
    generates the actual CSS (a `.wp-container-N{position:sticky;...}`
    rule + auto class) from the JSON attrs at render time, regardless of
    what's in the stored HTML. Requires settings.position.sticky=true,
    enabled via Template_Loader::enable_position_sticky() (a
    wp_theme_json_data_theme filter — no theme file touched).

Known hard limits found building this (no native block-attribute
equivalent exists for any of these — confirmed by reading every relevant
wp-includes/block-supports/*.php file, not guessed):
  - CSS counters (chapter numbering, decimal-leading-zero benefit numbers)
  - ::before/::after generated content (decorative eyebrow line, arrow
    icons, notice-pill glyphs via CSS, mystery-tier diagonal stripe)
  - backdrop-filter (nav's frosted-glass scrolled state)
  - CSS transitions (nav's smooth scroll-state change, reveal-on-scroll's
    fade/slide) — no "motion" block support exists
  - Multi-layer/positioned background gradients (hero overlay stacks two
    gradients; core/group's single `style.color.gradient` only takes one)
  - Fluid typography via clamp() driven by the *block's own fontSize
    control* — the Custom Size field takes one fixed value, not a range.
    (We DO use raw clamp() strings directly in hand-authored inline
    style="" below — valid CSS the browser executes fine — but that's
    bypassing the editor UI entirely, not "the fontSize attribute doing
    it": a real editor user could never produce this through the panel.)
"""

import json
import os

OUT = []


def esc_attrs(d):
    return json.dumps(d, ensure_ascii=False, separators=(",", ":"))


def style_to_css(style):
    """Flatten a WP block `style` attribute object into an inline CSS
    string — the same shape (color/typography/spacing/border/position)
    useBlockProps.save() would serialize, hand-rolled since we're not
    running JS. Only the properties this file actually uses are handled."""
    decls = []
    color = style.get("color", {})
    if "text" in color:
        decls.append(f'color:{color["text"]}')
    if "background" in color:
        decls.append(f'background-color:{color["background"]}')
    if "gradient" in color:
        decls.append(f'background:{color["gradient"]}')

    typo = style.get("typography", {})
    if "fontSize" in typo:
        decls.append(f'font-size:{typo["fontSize"]}')
    if "fontFamily" in typo:
        decls.append(f'font-family:{typo["fontFamily"]}')
    if "fontWeight" in typo:
        decls.append(f'font-weight:{typo["fontWeight"]}')
    if "fontStyle" in typo:
        decls.append(f'font-style:{typo["fontStyle"]}')
    if "lineHeight" in typo:
        decls.append(f'line-height:{typo["lineHeight"]}')
    if "letterSpacing" in typo:
        decls.append(f'letter-spacing:{typo["letterSpacing"]}')
    if "textTransform" in typo:
        decls.append(f'text-transform:{typo["textTransform"]}')

    spacing = style.get("spacing", {})
    if "padding" in spacing:
        p = spacing["padding"]
        if isinstance(p, str):
            decls.append(f'padding:{p}')
        else:
            for side in ("top", "right", "bottom", "left"):
                if side in p:
                    decls.append(f'padding-{side}:{p[side]}')
    if "margin" in spacing:
        m = spacing["margin"]
        if isinstance(m, str):
            decls.append(f'margin:{m}')
        else:
            for side in ("top", "right", "bottom", "left"):
                if side in m:
                    decls.append(f'margin-{side}:{m[side]}')
    if "blockGap" in spacing:
        decls.append(f'gap:{spacing["blockGap"]}')

    border = style.get("border", {})
    if "radius" in border:
        decls.append(f'border-radius:{border["radius"]}')
    if "width" in border and "color" in border:
        decls.append(f'border:{border["width"]} solid {border["color"]}')

    dimensions = style.get("dimensions", {})
    if "minHeight" in dimensions:
        decls.append(f'min-height:{dimensions["minHeight"]}')

    elements = style.get("_extra", "")
    css = ";".join(decls)
    if elements:
        css = (css + ";" + elements) if css else elements
    return css


def layout_to_css(layout):
    """The `layout` block attribute's actual CSS (display:flex + friends)
    is generated by WordPress at render time from theme.json global styles
    (WP_Theme_JSON's layout definitions) — NOT from wp-block-library or the
    per-block block-supports stylesheet. Confirmed by testing: with global
    styles stripped (required to keep the active theme's own presets from
    bleeding in — a constraint from earlier in this project, unrelated to
    this experiment), `layout` JSON attrs were present and WP even added
    the right classes/override rules (justify-content, flex-wrap, gap), but
    the foundational `.is-layout-flex{display:flex}` rule was simply
    missing from every loaded stylesheet, and the container computed
    `display:block`. So the `layout` attribute is kept in the JSON (it's
    still what a real editor user would set via the panel), but the actual
    `display` value is additionally hand-supplied via inline style here —
    a real limit of native block styling in a page this isolated, not a
    bug in our markup."""
    if not layout:
        return ""
    t = layout.get("type")
    if t == "flex":
        decls = ["display:flex"]
        if layout.get("orientation") == "vertical":
            decls.append("flex-direction:column")
        if "justifyContent" in layout:
            decls.append(f'justify-content:{layout["justifyContent"]}')
        if "alignItems" in layout:
            decls.append(f'align-items:{layout["alignItems"]}')
        if layout.get("flexWrap") == "nowrap":
            decls.append("flex-wrap:nowrap")
        return ";".join(decls)
    if t == "grid":
        decls = ["display:grid"]
        if "columnCount" in layout:
            decls.append(f'grid-template-columns:repeat({layout["columnCount"]},1fr)')
        return ";".join(decls)
    return ""


def group(inner_html_fn, style=None, layout=None, tag_name=None):
    attrs = {}
    if style:
        attrs["style"] = style
    if layout:
        attrs["layout"] = layout
    if tag_name:
        attrs["tagName"] = tag_name
    tag = tag_name or "div"
    css = style_to_css(style or {})
    layout_css = layout_to_css(layout)
    if layout_css:
        css = (css + ";" + layout_css) if css else layout_css
    style_attr = f' style="{css}"' if css else ""
    OUT.append(f'<!-- wp:group {esc_attrs(attrs) if attrs else "{}"} -->')
    OUT.append(f'<{tag} class="wp-block-group"{style_attr}>')
    inner_html_fn()
    OUT.append(f'</{tag}>')
    OUT.append('<!-- /wp:group -->')


def columns(cols_fn, style=None):
    # core/columns' own base `display:flex` lives in its per-block split
    # stylesheet (wp-includes/blocks/columns/style.css), which — like
    # group's `layout`-generated CSS — isn't loaded on this isolated page;
    # supplied directly for the same reason as layout_to_css() above.
    attrs = {"style": style} if style else {}
    css = style_to_css(style or {})
    css = (css + ";display:flex") if css else "display:flex"
    style_attr = f' style="{css}"'
    OUT.append(f'<!-- wp:columns {esc_attrs(attrs) if attrs else "{}"} -->')
    OUT.append(f'<div class="wp-block-columns"{style_attr}>')
    cols_fn()
    OUT.append('</div>')
    OUT.append('<!-- /wp:columns -->')


def column(inner_fn, style=None):
    attrs = {"style": style} if style else {}
    style_attr = f' style="{style_to_css(style)}"' if style else ""
    OUT.append(f'<!-- wp:column {esc_attrs(attrs) if attrs else "{}"} -->')
    OUT.append(f'<div class="wp-block-column"{style_attr}>')
    inner_fn()
    OUT.append('</div>')
    OUT.append('<!-- /wp:column -->')


def heading(level, text, style=None):
    attrs = {"level": level}
    if style:
        attrs["style"] = style
    style_attr = f' style="{style_to_css(style)}"' if style else ""
    OUT.append(f'<!-- wp:heading {esc_attrs(attrs)} -->')
    OUT.append(f'<h{level} class="wp-block-heading"{style_attr}>{text}</h{level}>')
    OUT.append('<!-- /wp:heading -->')


def paragraph(html, style=None):
    attrs = {"style": style} if style else {}
    style_attr = f' style="{style_to_css(style)}"' if style else ""
    OUT.append(f'<!-- wp:paragraph {esc_attrs(attrs) if attrs else "{}"} -->')
    OUT.append(f'<p class="wp-block-paragraph"{style_attr}>{html}</p>')
    OUT.append('<!-- /wp:paragraph -->')


def buttons_block(inner_fn, layout=None):
    attrs = {"layout": layout} if layout else {}
    OUT.append(f'<!-- wp:buttons {esc_attrs(attrs) if attrs else "{}"} -->')
    OUT.append('<div class="wp-block-buttons">')
    inner_fn()
    OUT.append('</div>')
    OUT.append('<!-- /wp:buttons -->')


def button(text, style=None, url="#", tag_name="a"):
    attrs = {}
    if tag_name != "a":
        attrs["tagName"] = tag_name
    if style:
        attrs["style"] = style
    if url and tag_name == "a":
        attrs["url"] = url
    style_attr = f' style="{style_to_css(style)}"' if style else ""
    OUT.append(f'<!-- wp:button {esc_attrs(attrs) if attrs else "{}"} -->')
    if tag_name == "a":
        OUT.append(f'<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{url}"{style_attr}>{text}</a></div>')
    else:
        OUT.append(f'<div class="wp-block-button"><{tag_name} class="wp-block-button__link wp-element-button"{style_attr}>{text}</{tag_name}></div>')
    OUT.append('<!-- /wp:button -->')


# ============================================================
# TOKENS (from source/index.html :root — hand-carried, since this
# experiment has no tokens.css to reference custom properties from)
# ============================================================
INK = "#0B0E12"
IVORY = "#EFECE4"
BRONZE = "#B4996A"
BRONZE_2 = "#C6AC7E"
BRONZE_3 = "#D9C199"
SERIF = "'Cormorant Garamond', 'Times New Roman', Georgia, serif"
SANS = "'Inter', -apple-system, 'Segoe UI', system-ui, sans-serif"


# ============================================================
# NAV — core/group, native `position:sticky` (WP renders it as `fixed`-
# equivalent CSS since it's the first element on the page: sticky to a
# container spanning the full page height reads identically to fixed).
# HARD LIMIT: no backdrop-filter, no transition — no block attribute for
# either exists. The scrolled/frosted-glass state is simply not
# reproduced in this variant; nav stays in its resting look throughout.
# ============================================================
def nav_section():
    def inner():
        def logo():
            paragraph('ATELIER AXELL', style={
                "typography": {"fontFamily": SERIF, "fontSize": "15px", "letterSpacing": "0.4em", "textTransform": "uppercase"},
                "color": {"text": IVORY},
                "spacing": {"padding": {"top": "8px", "bottom": "6px"}},
                "_extra": f"border-top:1px solid rgba(239,236,228,.35);border-bottom:1px solid rgba(239,236,228,.35);text-align:center;margin:0",
            })
            paragraph('THE AXELL WORLD', style={
                "typography": {"fontFamily": SANS, "fontSize": "8.5px", "letterSpacing": "0.42em", "textTransform": "uppercase"},
                "color": {"text": "rgba(239,236,228,.55)"},
                "spacing": {"margin": {"top": "6px", "bottom": "0"}},
                "_extra": "text-align:center",
            })
        group(logo, layout={"type": "flex", "orientation": "vertical", "flexWrap": "nowrap"})

        def links():
            for href, label in [('#convite', 'Convite'), ('#placa', 'A Placa'), ('#jornada', 'Como Entrar'), ('#niveis', 'Níveis'), ('#beneficios', 'Benefícios')]:
                paragraph(f'<a href="{href}" style="color:inherit;text-decoration:none">{label}</a>', style={
                    "typography": {"fontFamily": SANS, "fontSize": "11px", "letterSpacing": "0.24em", "textTransform": "uppercase"},
                    "color": {"text": "rgba(239,236,228,.68)"},
                    "spacing": {"margin": {"top": "0", "bottom": "0"}},
                })

            def cta():
                button('Solicitar adesão', url='#adesao', style={
                    "typography": {"fontFamily": SANS, "fontSize": "11.5px", "fontWeight": "500", "letterSpacing": "0.26em", "textTransform": "uppercase"},
                    "color": {"text": INK, "background": BRONZE},
                    "spacing": {"padding": {"top": "14px", "bottom": "14px", "left": "22px", "right": "22px"}},
                    "border": {"radius": "999px"},
                    "_extra": "display:inline-block;text-decoration:none",
                })
            buttons_block(cta)
        group(links, style={"spacing": {"blockGap": "36px"}}, layout={"type": "flex", "alignItems": "center", "flexWrap": "nowrap"})
    group(
        inner,
        style={
            "color": {"text": IVORY},
            "spacing": {"padding": {"top": "24px", "bottom": "24px", "left": "clamp(20px, 4vw, 60px)", "right": "clamp(20px, 4vw, 60px)"}},
            "position": {"type": "sticky", "top": "0px"},
            "_extra": "background:#0B0E12",
        },
        layout={"type": "flex", "justifyContent": "space-between", "alignItems": "center"},
    )


# ============================================================
# HERO — core/group, min-height 100vh. HARD LIMIT: the source's layered
# radial+linear gradient overlay (two stacked gradients + a photo
# background-image) can't be expressed via style.color.gradient (single
# gradient only) — approximated here with one linear gradient standing in
# for the darkening vignette; the photo layer and second gradient are
# dropped, not faked.
# ============================================================
def hero_section():
    def inner():
        def cols():
            def left():
                paragraph('Edição Lumière · 2026', style={
                    "typography": {"fontFamily": SANS, "fontSize": "11px", "letterSpacing": "0.42em", "textTransform": "uppercase"},
                    "color": {"text": BRONZE_3},
                    "spacing": {"margin": {"bottom": "40px", "top": "0"}},
                })
                heading(1, 'O atelier é o lugar onde criar encontra <em style="font-style:italic;color:' + BRONZE_2 + '">morar</em>.', style={
                    "typography": {"fontFamily": SERIF, "fontWeight": "300", "fontSize": "clamp(48px, 8vw, 116px)", "lineHeight": "1.02", "letterSpacing": "-0.015em"},
                    "color": {"text": IVORY},
                    "spacing": {"margin": {"top": "0", "bottom": "0"}},
                })
                paragraph('Um clube <em style="font-style:italic">por convite</em>. Para arquitetos e designers que transformam o banho em obra, o spa em poesia e o projeto em memória.', style={
                    "typography": {"fontFamily": SERIF, "fontWeight": "300", "fontStyle": "italic", "fontSize": "clamp(20px, 1.9vw, 26px)", "lineHeight": "1.45"},
                    "color": {"text": "rgba(239,236,228,.86)"},
                    "spacing": {"margin": {"top": "36px", "bottom": "44px"}},
                })

                def ctas():
                    button('Solicitar adesão', url='#adesao', style={
                        "typography": {"fontFamily": SANS, "fontSize": "12px", "fontWeight": "500", "letterSpacing": "0.26em", "textTransform": "uppercase"},
                        "color": {"text": INK, "background": BRONZE},
                        "spacing": {"padding": {"top": "24px", "bottom": "24px", "left": "38px", "right": "38px"}},
                        "border": {"radius": "999px"},
                        "_extra": "display:inline-block;text-decoration:none",
                    })
                    button('Ler o convite', url='#convite', style={
                        "typography": {"fontFamily": SANS, "fontSize": "12px", "fontWeight": "500", "letterSpacing": "0.26em", "textTransform": "uppercase"},
                        "color": {"text": IVORY},
                        "spacing": {"padding": {"top": "24px", "bottom": "24px", "left": "38px", "right": "38px"}},
                        "border": {"radius": "999px", "width": "1px", "color": "rgba(239,236,228,.3)"},
                        "_extra": "display:inline-block;text-decoration:none",
                    })
                buttons_block(ctas, layout={"type": "flex", "flexWrap": "wrap"})
            column(left)

            def right():
                paragraph('"Você não entra.<br>Você é recebido."', style={
                    "typography": {"fontFamily": SERIF, "fontStyle": "italic", "fontWeight": "300", "fontSize": "20px", "lineHeight": "1.4"},
                    "color": {"text": BRONZE_3},
                    "spacing": {"margin": {"bottom": "32px", "top": "0"}},
                })

                def kv(k, v):
                    paragraph(k, style={
                        "typography": {"fontFamily": SANS, "fontSize": "10px", "letterSpacing": "0.32em", "textTransform": "uppercase"},
                        "color": {"text": BRONZE_2},
                        "spacing": {"margin": {"bottom": "10px", "top": "0"}},
                    })
                    paragraph(v, style={
                        "typography": {"fontFamily": SERIF, "fontWeight": "300", "fontSize": "20px", "lineHeight": "1.3"},
                        "color": {"text": IVORY},
                        "spacing": {"margin": {"top": "0", "bottom": "28px"}},
                    })
                kv('Programa', 'Atelier Axell Club')
                kv('Convite', 'Por seleção — apenas profissionais aprovados.')
                kv('Três níveis', 'Signature · Alliance · Ambassador')
            column(right, style={"spacing": {"padding": {"left": "40px"}}, "_extra": "border-left:1px solid rgba(239,236,228,.18)"})
        columns(cols, style={"spacing": {"blockGap": "60px"}, "_extra": "align-items:end;max-width:1280px;margin:0 auto;width:100%"})
    group(
        inner,
        style={
            "color": {"text": IVORY, "background": INK},
            "spacing": {"padding": {"top": "160px", "bottom": "clamp(80px, 10vw, 140px)", "left": "clamp(20px, 4vw, 60px)", "right": "clamp(20px, 4vw, 60px)"}},
            "dimensions": {"minHeight": "100vh"},
            "_extra": "position:relative;box-sizing:border-box;background-image:linear-gradient(180deg, rgba(11,14,18,.65) 0%, rgba(11,14,18,.3) 40%, rgba(11,14,18,.85) 100%), radial-gradient(ellipse at 70% 20%, rgba(230,178,122,.18), transparent 60%);background-color:#0B0E12;display:flex;align-items:flex-end",
        },
    )


STONE = "#8A8172"
STONE_DK = "#5F584C"
LINE_IV = "#DED6C2"


def list_block(items_html, ordered=False, list_type="", style=None):
    """Native <ol>/<li> numbering (browser default, driven by the `type`
    HTML attribute) — a genuine hard-CSS-free mechanism, unlike the
    decimal-leading-zero zero-padding the production page achieves via
    ::marker{content:counter(...)}; that specific formatting has no
    attribute equivalent, so Benefits below just uses plain 1/2/3…"""
    tag = "ol" if ordered else "ul"
    attrs = {"ordered": True} if ordered else {}
    if list_type:
        attrs["type"] = list_type
    css = style_to_css(style or {})
    style_attr = f' style="{css}"' if css else ""
    type_attr = f' type="{list_type}"' if list_type else ""
    OUT.append(f'<!-- wp:list {esc_attrs(attrs) if attrs else "{}"} -->')
    OUT.append(f'<{tag} class="wp-block-list"{type_attr}{style_attr}>')
    for item in items_html:
        OUT.append('<!-- wp:list-item -->')
        OUT.append(f'<li>{item}</li>')
        OUT.append('<!-- /wp:list-item -->')
    OUT.append(f'</{tag}>')
    OUT.append('<!-- /wp:list -->')


def section_head(eyebrow, title, body, on_ivory=False):
    text_color = INK if on_ivory else IVORY
    bronze_color = BRONZE if on_ivory else BRONZE_2
    body_color = STONE_DK if on_ivory else "rgba(239,236,228,.72)"

    def cols():
        def left():
            # HARD LIMIT: the eyebrow's decorative leading line is a CSS
            # ::before pseudo-element in the source — no block attribute
            # equivalent, so it's simply not reproduced here.
            paragraph(eyebrow, style={
                "typography": {"fontFamily": SANS, "fontSize": "10.5px", "fontWeight": "500", "letterSpacing": "0.32em", "textTransform": "uppercase"},
                "color": {"text": bronze_color},
                "spacing": {"margin": {"bottom": "28px", "top": "0"}},
            })
            heading(2, title, style={
                "typography": {"fontFamily": SERIF, "fontWeight": "300", "fontSize": "clamp(36px, 5vw, 68px)", "lineHeight": "1.05", "letterSpacing": "-0.015em"},
                "color": {"text": text_color},
            })
        column(left)

        def right():
            paragraph(body, style={
                "typography": {"fontFamily": SANS, "fontSize": "15.5px", "lineHeight": "1.75", "fontWeight": "300"},
                "color": {"text": body_color},
                "_extra": "max-width:62ch",
            })
        column(right, style={"_extra": "align-self:end"})
    columns(cols, style={"spacing": {"blockGap": "80px", "margin": {"bottom": "88px"}}, "_extra": "align-items:end"})


def chapter_tag(text, on_ivory=False):
    paragraph(text, style={
        "typography": {"fontFamily": SANS, "fontSize": "10.5px", "letterSpacing": "0.36em", "textTransform": "uppercase", "fontWeight": "500"},
        "color": {"text": BRONZE if on_ivory else BRONZE_2},
        "spacing": {"margin": {"bottom": "16px", "top": "0"}},
    })


# ============================================================
# CONVITE / MANIFESTO — core/columns, 1fr/1.4fr
# ============================================================
def manifesto_section():
    def cols():
        def side():
            chapter_tag('Capítulo 01 · Convite')
            heading(2, 'Não basta especificar. É preciso <em style="font-style:italic">sentir</em>.', style={
                "typography": {"fontFamily": SERIF, "fontWeight": "300", "fontSize": "clamp(30px, 3.6vw, 44px)", "fontStyle": "italic"},
                "color": {"text": IVORY},
            })
        column(side, style={"_extra": "flex-basis:38%"})

        def copy():
            paragraph('Há gestos no banho que merecem ser desenhados por <em style="font-style:italic">mãos especiais</em>.<br>Há projetos que pedem <em style="font-style:italic">cuidado de autor</em>.<br>E há profissionais que a Axell <em style="font-style:italic">reconhece por assinatura</em>.', style={
                "typography": {"fontFamily": SANS, "fontSize": "15.5px", "lineHeight": "1.9", "fontWeight": "300"},
                "color": {"text": "rgba(239,236,228,.78)"},
                "spacing": {"margin": {"bottom": "24px", "top": "0"}},
            })
            paragraph('É preciso escolher como se escolhesse uma obra de arte para a própria casa — porque cada banheira, cada spa, cada detalhe carrega o gesto de quem escolheu. Arquitetos e designers escolhem por convicção. E reconhecemos isso.', style={
                "typography": {"fontFamily": SANS, "fontSize": "15.5px", "lineHeight": "1.9", "fontWeight": "300"},
                "color": {"text": "rgba(239,236,228,.78)"},
                "spacing": {"margin": {"bottom": "24px", "top": "0"}},
            })
            paragraph('O Atelier Axell Club é um clube por seleção. Discreto. Curado. Feito para os poucos profissionais que desenham projetos extraordinários com peças Axell — e transformam o produto em memória.', style={
                "typography": {"fontFamily": SANS, "fontSize": "15.5px", "lineHeight": "1.9", "fontWeight": "300"},
                "color": {"text": "rgba(239,236,228,.78)"},
                "spacing": {"margin": {"bottom": "24px", "top": "0"}},
            })
            paragraph('— Curadoria &amp; Assinatura Axell', style={
                "typography": {"fontFamily": SERIF, "fontStyle": "italic", "fontSize": "16px"},
                "color": {"text": BRONZE_2},
            })
        column(copy, style={"_extra": "flex-basis:62%"})

    def inner():
        columns(cols, style={"spacing": {"blockGap": "100px"}, "_extra": "align-items:start"})
    section_wrap(inner)


def section_wrap(inner_fn, on_ivory=False, on_ink=False, padding="clamp(90px, 13vw, 180px) clamp(20px, 4vw, 60px)"):
    bg = IVORY if on_ivory else INK
    text = INK if on_ivory else IVORY
    group(inner_fn, style={
        "color": {"text": text, "background": bg},
        "spacing": {"padding": padding if isinstance(padding, dict) else {
            "top": padding.split()[0], "bottom": padding.split()[0],
            "left": padding.split()[1], "right": padding.split()[1],
        }},
        "_extra": "box-sizing:border-box",
    })


def container(inner_fn, extra=""):
    group(inner_fn, style={"_extra": f"max-width:1280px;margin:0 auto;box-sizing:border-box{(';' + extra) if extra else ''}"})


# ============================================================
# 4 PILARES — core/list ordered type="i" (native numbering)
# ============================================================
PILLARS = [
    ('Reconhecimento', 'Selo de autoria para quem desenha ambientes extraordinários. Sua assinatura, valorizada pela marca.'),
    ('Recompensa', 'Bônus financeiro direto por cada venda concretizada. A parceria em números, com transparência.'),
    ('Experiência', 'Viagens, jantares e curadoria à altura do talento. Momentos que alimentam repertório.'),
    ('Visibilidade', 'Os seus projetos ganham a voz autoral da Axell — publicação editorial, imprensa e redes.'),
]


def pillars_section():
    def inner():
        def head():
            chapter_tag('Capítulo 02 · Manifesto')
            heading(2, 'Quatro pilares. Uma <em style="font-style:italic;color:' + BRONZE_2 + '">assinatura</em>.', style={
                "typography": {"fontFamily": SERIF, "fontWeight": "300", "fontSize": "clamp(36px, 5vw, 68px)", "lineHeight": "1.05"},
                "color": {"text": IVORY},
                "spacing": {"margin": {"bottom": "24px", "top": "0"}},
            })
            paragraph('Arquitetos e designers escolhem por convicção — e o clube foi construído sobre quatro pilares que devolvem essa convicção em forma de reconhecimento, recompensa, experiência e visibilidade.', style={
                "typography": {"fontFamily": SANS, "fontSize": "15.5px", "lineHeight": "1.75", "fontWeight": "300"},
                "color": {"text": "rgba(239,236,228,.72)"},
                "_extra": "max-width:62ch",
            })
        container(head, extra="margin-bottom:64px")

        items = [f'<strong style="font-family:{SERIF};font-weight:400;font-size:26px;color:{IVORY};display:block;margin-bottom:14px">{t}</strong><span style="font-family:{SANS};font-size:14px;line-height:1.65;color:rgba(239,236,228,.66);font-weight:300">{b}</span>' for t, b in PILLARS]
        list_block(items, ordered=True, list_type="i", style={
            "_extra": "display:grid;grid-template-columns:repeat(4,1fr);gap:40px;list-style-position:inside;padding:0;margin:0;max-width:1280px;margin-left:auto;margin-right:auto",
        })
    section_wrap(inner)


# ============================================================
# A PLACA
# ============================================================
def placa_section():
    def inner():
        def cols():
            def copy():
                chapter_tag('Capítulo 03 · A Placa', on_ivory=False)
                heading(2, 'A placa do <em style="font-style:italic;color:' + BRONZE_2 + '">membro</em>.', style={
                    "typography": {"fontFamily": SERIF, "fontWeight": "300", "fontSize": "clamp(36px, 5vw, 68px)"},
                    "color": {"text": IVORY},
                    "spacing": {"margin": {"bottom": "24px", "top": "0"}},
                })
                paragraph('CONCEDIDA POR CATEGORIZAÇÃO', style={
                    "typography": {"fontFamily": SANS, "fontSize": "11px", "letterSpacing": "0.2em", "textTransform": "uppercase", "fontWeight": "500"},
                    "color": {"text": BRONZE_2, "background": "rgba(230,178,122,.08)"},
                    "spacing": {"padding": {"top": "12px", "bottom": "12px", "left": "20px", "right": "20px"}},
                    "border": {"radius": "999px", "width": "1px", "color": "rgba(230,178,122,.35)"},
                    "_extra": "display:inline-block;margin-bottom:20px",
                })
                paragraph('A entrega da placa depende do estágio de categorização alcançado pelo profissional dentro do clube. Ela é conquistada, não distribuída.', style={
                    "typography": {"fontFamily": SERIF, "fontStyle": "italic", "fontSize": "15px"},
                    "color": {"text": "rgba(239,236,228,.6)"},
                    "spacing": {"margin": {"bottom": "20px", "top": "0"}},
                })
                paragraph('Uma peça <em style="font-style:italic">única</em>, uma obra de arte em pedra. Gravada com o nome do profissional e o selo ATELIER AXELL em letras finas e delgadas.', style={
                    "typography": {"fontFamily": SERIF, "fontWeight": "300", "fontSize": "clamp(20px,2vw,26px)", "fontStyle": "italic", "lineHeight": "1.4"},
                    "color": {"text": "rgba(239,236,228,.86)"},
                    "spacing": {"margin": {"bottom": "32px", "top": "0"}},
                })
                items = [
                    f'<strong style="color:{BRONZE_2};font-family:{SANS};font-size:11px;letter-spacing:.1em;text-transform:uppercase;display:block;margin-bottom:4px">Abstratismo</strong><span style="color:rgba(239,236,228,.7);font-family:{SANS};font-size:14px">Cada peça traz um desenho abstrato único, criado pela curadoria Axell.</span>',
                    f'<strong style="color:{BRONZE_2};font-family:{SANS};font-size:11px;letter-spacing:.1em;text-transform:uppercase;display:block;margin-bottom:4px">Exclusividade</strong><span style="color:rgba(239,236,228,.7);font-family:{SANS};font-size:14px">Nenhum membro recebe o mesmo desenho.</span>',
                    f'<strong style="color:{BRONZE_2};font-family:{SANS};font-size:11px;letter-spacing:.1em;text-transform:uppercase;display:block;margin-bottom:4px">Entrega</strong><span style="color:rgba(239,236,228,.7);font-family:{SANS};font-size:14px">Após aprovação do cadastro, em até 30 dias.</span>',
                ]
                list_block(items, style={"_extra": "list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:20px"})
            column(copy)

            def visual():
                def plate():
                    paragraph('Atelier · Axell', style={
                        "typography": {"fontFamily": SANS, "fontSize": "10px", "letterSpacing": "0.3em", "textTransform": "uppercase"},
                        "color": {"text": BRONZE_2}, "_extra": "text-align:center",
                        "spacing": {"margin": {"bottom": "20px", "top": "0"}},
                    })
                    paragraph('[Seu nome]', style={
                        "typography": {"fontFamily": SERIF, "fontWeight": "300", "fontSize": "28px"},
                        "color": {"text": IVORY}, "_extra": "text-align:center",
                        "spacing": {"margin": {"top": "0", "bottom": "40px"}},
                    })
                group(plate, style={
                    "spacing": {"padding": {"top": "60px", "bottom": "60px", "left": "40px", "right": "40px"}},
                    "border": {"width": "1px", "color": "rgba(180,153,106,.3)"},
                    "_extra": "background:linear-gradient(145deg, #1a1510, #0f0c08)",
                })
            column(visual)
        columns(cols, style={"spacing": {"blockGap": "80px"}})
    section_wrap(inner, padding="clamp(90px,13vw,180px) clamp(20px,4vw,60px)")


# ============================================================
# PROTAGONISTAS — ivory background
# ============================================================
PROTAGONISTS = [
    ('Vocês assinam ambientes', 'Detalham o projeto, escolhem o produto, validam o ritual.'),
    ('Vocês educam o cliente', 'Recomendam com curadoria, esclarecem diferenças técnicas.'),
    ('Vocês constroem a marca', 'Cada projeto é um capítulo de conteúdo, uma voz técnica e autoral.'),
]


def protagonists_section():
    def inner():
        def head():
            chapter_tag('Capítulo 04 · Protagonistas', on_ivory=True)
            heading(2, 'O arquiteto e o <em style="font-style:italic;color:' + BRONZE + '">designer</em>.', style={
                "typography": {"fontFamily": SERIF, "fontWeight": "300", "fontSize": "clamp(36px, 5vw, 68px)"},
                "color": {"text": INK},
                "spacing": {"margin": {"bottom": "24px", "top": "0"}},
            })
            paragraph('A Axell reconhece os profissionais que transformam banheiras e spas em momentos extraordinários.', style={
                "typography": {"fontFamily": SANS, "fontSize": "15.5px", "lineHeight": "1.75", "fontWeight": "300"},
                "color": {"text": STONE_DK}, "_extra": "max-width:62ch",
            })
        container(head, extra="margin-bottom:64px")

        items = [f'<strong style="font-family:{SERIF};font-weight:400;font-size:30px;color:{INK};display:block;margin-bottom:16px">{t}</strong><span style="font-family:{SANS};font-size:14.5px;line-height:1.7;color:{STONE_DK};font-weight:300">{b}</span>' for t, b in PROTAGONISTS]
        list_block(items, ordered=True, list_type="i", style={
            "_extra": f"display:grid;grid-template-columns:repeat(3,1fr);gap:40px;list-style-position:inside;padding:0;margin:0;max-width:1280px;margin-left:auto;margin-right:auto;border-top:1px solid {LINE_IV}",
        })
    section_wrap(inner, on_ivory=True)


# ============================================================
# 4 PROMESSAS
# ============================================================
PROMISES = [
    ('Recompensa real', 'Bônus financeiro direto por cada venda concretizada, sem intermediários.'),
    ('Curadoria constante', 'Eventos, viagens e encontros desenhados ao detalhe.'),
    ('Visibilidade editorial', 'Os seus projetos ganham a voz autoral da Axell.'),
    ('Pertencimento', 'Lugar entre os poucos profissionais que a Axell reconhece por assinatura.'),
]


def promises_section():
    def inner():
        def head():
            chapter_tag('Capítulo 05 · Proposta')
            heading(2, 'Quatro promessas. Uma <em style="font-style:italic;color:' + BRONZE_2 + '">assinatura</em>: Axell.', style={
                "typography": {"fontFamily": SERIF, "fontWeight": "300", "fontSize": "clamp(36px, 5vw, 68px)"},
                "color": {"text": IVORY},
                "spacing": {"margin": {"bottom": "24px", "top": "0"}},
            })
            paragraph('Ser membro do Atelier Axell é ocupar um lugar seleto entre os profissionais que desenham o bem-estar brasileiro.', style={
                "typography": {"fontFamily": SANS, "fontSize": "15.5px", "lineHeight": "1.75", "fontWeight": "300"},
                "color": {"text": "rgba(239,236,228,.72)"}, "_extra": "max-width:62ch",
            })
        container(head, extra="margin-bottom:64px")

        items = [f'<strong style="font-family:{SERIF};font-weight:400;font-size:26px;color:{IVORY};display:block;margin-bottom:12px">{t}</strong><span style="font-family:{SANS};font-size:14.5px;line-height:1.65;color:rgba(239,236,228,.68);font-weight:300">{b}</span>' for t, b in PROMISES]
        list_block(items, ordered=True, list_type="i", style={
            "_extra": "display:grid;grid-template-columns:1fr 1fr;gap:40px;list-style-position:inside;padding:0;margin:0;max-width:1280px;margin-left:auto;margin-right:auto",
        })
    section_wrap(inner)


# ============================================================
# O NOME — ivory, columns with a definition table
# ============================================================
CONCEPT_ROWS = [
    ('Espaço', 'Espaço de criação — não sala de exposição.'),
    ('Postura', 'Você não é cliente. É autor.'),
    ('Método', 'Curadoria e ofício. Prática, não só compra.'),
    ('Escala', 'Oficina íntima. Pertence a poucos, com profundidade.'),
    ('Origem', 'Herança francesa. Estética editorial, museu, maison.'),
    ('Marca', 'Axell como origem — não como patrocinador.'),
]


def concept_section():
    def inner():
        def cols():
            def left():
                chapter_tag('Capítulo 06 · O Nome', on_ivory=True)
                heading(2, 'Um espaço <em style="font-style:italic;color:' + BRONZE + '">exclusivo</em>.', style={
                    "typography": {"fontFamily": SERIF, "fontWeight": "300", "fontSize": "clamp(36px, 5vw, 68px)"},
                    "color": {"text": INK},
                    "spacing": {"margin": {"bottom": "24px", "top": "0"}},
                })
                paragraph('"O atelier é o lugar onde criar encontra morar. É oficina de começar, é o altar de arquitetar."', style={
                    "typography": {"fontFamily": SERIF, "fontStyle": "italic", "fontWeight": "300", "fontSize": "22px", "lineHeight": "1.45"},
                    "color": {"text": STONE_DK},
                })
            column(left)

            def right():
                paragraph('Por que Atelier', style={
                    "typography": {"fontFamily": SANS, "fontSize": "10.5px", "letterSpacing": "0.36em", "textTransform": "uppercase", "fontWeight": "500"},
                    "color": {"text": BRONZE},
                    "spacing": {"margin": {"bottom": "20px", "top": "0"}},
                })

                def rows():
                    for k, v in CONCEPT_ROWS:
                        def row(k=k, v=v):
                            paragraph(k, style={
                                "typography": {"fontFamily": SANS, "fontSize": "10px", "letterSpacing": "0.1em", "textTransform": "uppercase"},
                                "color": {"text": BRONZE}, "spacing": {"margin": {"top": "0", "bottom": "4px"}},
                            })
                            paragraph(v, style={
                                "typography": {"fontFamily": SERIF, "fontSize": "18px"},
                                "color": {"text": INK}, "spacing": {"margin": {"top": "0"}},
                            })
                        group(row, style={"spacing": {"padding": {"top": "16px", "bottom": "16px"}}, "_extra": f"border-bottom:1px solid {LINE_IV}"})
                group(rows)
            column(right)
        columns(cols, style={"spacing": {"blockGap": "80px"}})
    section_wrap(inner, on_ivory=True)


# ============================================================
# JORNADA — decorative numeral badge has no block-attribute equivalent
# (colored circle shape); using plain ordered-list numbering instead.
# ============================================================
JOURNEY = [
    ('Solicitação', 'Você entra na área Atelier Axell e inicia sua solicitação de adesão.'),
    ('Cadastro', 'Nome, escritório, CAU/CREA, portfólio, endereço, CPF/CNPJ.'),
    ('Curadoria', 'A equipe Axell lê, entende o seu perfil e libera o acesso.'),
    ('Onboarding', 'Recebe e-mail de boas-vindas e passa a acessar o Atelier.'),
]


def journey_section():
    def inner():
        def head():
            chapter_tag('Capítulo 07 · Como Entrar')
            heading(2, 'Quatro passos <em style="font-style:italic;color:' + BRONZE_2 + '">até o clube</em>.', style={
                "typography": {"fontFamily": SERIF, "fontWeight": "300", "fontSize": "clamp(36px, 5vw, 68px)"},
                "color": {"text": IVORY},
            })
        container(head, extra="margin-bottom:64px")

        items = [f'<strong style="font-family:{SERIF};font-weight:400;font-size:24px;color:{IVORY};display:block;margin-bottom:12px">{t}</strong><span style="font-family:{SANS};font-size:14px;line-height:1.65;color:rgba(239,236,228,.66);font-weight:300">{b}</span>' for t, b in JOURNEY]
        list_block(items, ordered=True, style={
            "_extra": "display:grid;grid-template-columns:repeat(4,1fr);gap:32px;list-style-position:inside;padding:0;margin:0;max-width:1280px;margin-left:auto;margin-right:auto",
        })
        paragraph('"Você não entra. <em style="font-style:italic">Você é recebido</em>."', style={
            "typography": {"fontFamily": SERIF, "fontStyle": "italic", "fontSize": "22px"},
            "color": {"text": "rgba(239,236,228,.6)"}, "_extra": "text-align:center;max-width:1280px;margin:56px auto 0",
        })
    section_wrap(inner)


# ============================================================
# NÍVEIS — 3 tier cards. Signature card fully done; Alliance/Ambassador
# ("mystery" cards) contain nested list + icon in production — simplified
# to a locked-caption paragraph here (core/icon needs a registered icon,
# out of scope for a pure style-attribute page).
# ============================================================
def tiers_section():
    def inner():
        def head():
            chapter_tag('Capítulo 08 · Níveis', on_ivory=True)
            heading(2, 'Três níveis. Uma <em style="font-style:italic;color:' + BRONZE + '">assinatura</em>.', style={
                "typography": {"fontFamily": SERIF, "fontWeight": "300", "fontSize": "clamp(36px, 5vw, 68px)"},
                "color": {"text": INK},
            })
        container(head, extra="margin-bottom:64px")

        def grid():
            def card(name, cap, tagline, body, locked=False):
                def inner_card():
                    paragraph(cap, style={
                        "typography": {"fontFamily": SANS, "fontSize": "10.5px", "letterSpacing": "0.32em", "textTransform": "uppercase"},
                        "color": {"text": STONE}, "spacing": {"margin": {"bottom": "10px", "top": "0"}},
                    })
                    heading(3, name, style={
                        "typography": {"fontFamily": SERIF, "fontWeight": "400", "fontSize": "38px"},
                        "color": {"text": INK}, "spacing": {"margin": {"bottom": "8px", "top": "0"}},
                    })
                    paragraph(tagline, style={
                        "typography": {"fontFamily": SERIF, "fontStyle": "italic", "fontSize": "16px"},
                        "color": {"text": BRONZE}, "spacing": {"margin": {"bottom": "28px", "top": "0"}},
                    })
                    paragraph(body, style={
                        "typography": {"fontFamily": SANS, "fontSize": "14px", "lineHeight": "1.6", "fontWeight": "300"},
                        "color": {"text": "rgba(90,88,76,.5)" if locked else STONE_DK},
                    })
                group(inner_card, style={
                    "spacing": {"padding": {"top": "48px", "bottom": "44px", "left": "40px", "right": "40px"}},
                    "border": {"radius": "4px", "width": "1px", "color": LINE_IV},
                    "_extra": "background:#FBF8F0" if not locked else "background:#F4F0E5",
                })
            card('Signature', 'Entrada no clube', 'o primeiro convite', 'Área exclusiva, cadastro de projetos, biblioteca técnica, newsletter editorial e bônus financeiro por especificação revertida em venda.')
            card('Alliance', 'Segundo estágio', 'a ser conquistado', 'Revelado após conquista — benefícios desvelados quando o membro alcança o estágio.', locked=True)
            card('Ambassador', 'Terceiro estágio', 'o círculo mais estreito', 'Revelado após conquista — a curadoria Axell guarda o silêncio até lá.', locked=True)
        group(grid, style={"_extra": "display:grid;grid-template-columns:repeat(3,1fr);gap:20px;max-width:1280px;margin:0 auto"})
    section_wrap(inner, on_ivory=True)


# ============================================================
# BENEFÍCIOS
# ============================================================
BENEFITS = [
    ('Área exclusiva', 'Histórico de reconhecimento, projetos cadastrados e campanhas ativas.'),
    ('Materiais premium', 'Catálogos, blocos CAD, manuais técnicos e biblioteca completa.'),
    ('Apoio prioritário', 'Atendimento com consultor Axell dedicado ao seu escritório.'),
    ('Consultoria de produto', 'Suporte técnico especializado para banheiras, spas e linhas completas.'),
    ('Lançamentos antecipados', 'Conhecimento de novidades antes do mercado, com pré-venda.'),
    ('Treinamentos exclusivos', 'Convites para workshops, aulas técnicas e imersões de fábrica.'),
    ('Selo digital de membro', 'Selo oficial Atelier Axell Club para site e redes sociais.'),
    ('Diretório de parceiros', 'Destaque em diretório público Axell com link para seu portfólio.'),
    ('Placa de membro', 'Peça única, uma obra de arte em pedra, gravada com seu nome.'),
]


def benefits_section():
    def inner():
        def head():
            chapter_tag('Capítulo 09 · Benefícios')
            heading(2, 'Recompensas à altura do <em style="font-style:italic;color:' + BRONZE_2 + '">talento</em>.', style={
                "typography": {"fontFamily": SERIF, "fontWeight": "300", "fontSize": "clamp(36px, 5vw, 68px)"},
                "color": {"text": IVORY},
            })
        container(head, extra="margin-bottom:64px")

        items = [f'<strong style="font-family:{SERIF};font-weight:400;font-size:22px;color:{IVORY};display:block;margin-bottom:12px">{t}</strong><span style="font-family:{SANS};font-size:14px;line-height:1.65;color:rgba(239,236,228,.66);font-weight:300">{b}</span>' for t, b in BENEFITS]
        list_block(items, ordered=True, style={
            "_extra": "display:grid;grid-template-columns:repeat(3,1fr);gap:32px 40px;list-style-position:inside;padding:0;margin:0;max-width:1280px;margin-left:auto;margin-right:auto",
        })
    section_wrap(inner)


# ============================================================
# EDITORIAL
# ============================================================
EDIT_PATH = ['Cadastro do projeto na sua conta', 'Curadoria editorial Axell', 'Fotografia ambientada e entrevista', 'Publicação multicanal Axell', 'Destaque em campanhas institucionais']
CHANNELS = [('Instagram Axell', 'Feed principal'), ('Instagram Atelier', 'Canal do clube'), ('Reels & vídeo', 'Motion editorial'), ('Pinterest', 'Curadoria visual'), ('Site editorial', 'Portfolio membros'), ('Newsletter', 'Mensal'), ('Catálogo impresso', 'Anual'), ('Imprensa parceira', 'Curadoria PR')]


def editorial_section():
    def inner():
        def cols():
            def left():
                chapter_tag('Capítulo 11 · Editorial', on_ivory=True)
                heading(2, 'Seu projeto, com a voz <em style="font-style:italic;color:' + BRONZE + '">Axell</em>.', style={
                    "typography": {"fontFamily": SERIF, "fontWeight": "300", "fontSize": "clamp(36px, 5vw, 68px)"},
                    "color": {"text": INK}, "spacing": {"margin": {"bottom": "20px", "top": "0"}},
                })
                paragraph('A Axell transforma o seu case em conteúdo editorial premium.', style={
                    "typography": {"fontFamily": SANS, "fontSize": "15.5px", "lineHeight": "1.75", "fontWeight": "300"},
                    "color": {"text": STONE_DK}, "spacing": {"margin": {"bottom": "32px", "top": "0"}},
                })
                paragraph('Percurso editorial', style={
                    "typography": {"fontFamily": SANS, "fontSize": "10.5px", "letterSpacing": "0.36em", "textTransform": "uppercase", "fontWeight": "500"},
                    "color": {"text": BRONZE}, "spacing": {"margin": {"bottom": "16px", "top": "0"}},
                })
                items = [f'<span style="font-family:{SERIF};font-weight:400;font-size:20px;color:{INK}">{t}</span>' for t in EDIT_PATH]
                list_block(items, ordered=True, list_type="i", style={"_extra": f"border-top:1px solid {LINE_IV};padding-left:32px;margin-top:12px"})
            column(left)

            def right():
                paragraph('Onde os cases aparecem', style={
                    "typography": {"fontFamily": SANS, "fontSize": "10.5px", "letterSpacing": "0.36em", "textTransform": "uppercase", "fontWeight": "500"},
                    "color": {"text": BRONZE}, "spacing": {"margin": {"bottom": "20px", "top": "0"}},
                })

                def grid():
                    for name, kind in CHANNELS:
                        def card(name=name, kind=kind):
                            paragraph(name, style={
                                "typography": {"fontFamily": SERIF, "fontSize": "16px"}, "color": {"text": INK},
                                "spacing": {"margin": {"top": "0", "bottom": "0"}},
                            })
                            paragraph(kind, style={
                                "typography": {"fontFamily": SANS, "fontSize": "11px", "textTransform": "uppercase", "letterSpacing": "0.05em"},
                                "color": {"text": STONE}, "spacing": {"margin": {"top": "0", "bottom": "0"}},
                            })
                        group(card, style={"spacing": {"padding": {"top": "14px", "bottom": "14px"}}, "_extra": f"display:flex;justify-content:space-between;border-bottom:1px solid {LINE_IV}"})
                group(grid, style={"_extra": "display:grid;grid-template-columns:repeat(2,1fr);gap:0 20px"})
            column(right)
        columns(cols, style={"spacing": {"blockGap": "80px"}})
    section_wrap(inner, on_ivory=True)


# ============================================================
# CTA STRIP
# ============================================================
def cta_strip_section():
    def inner():
        heading(2, 'Há profissionais que transformam o banho em <em style="font-style:italic;color:' + BRONZE_2 + '">obra</em>, o spa em <em style="font-style:italic;color:' + BRONZE_2 + '">poesia</em>. Solicite sua adesão.', style={
            "typography": {"fontFamily": SERIF, "fontWeight": "300", "fontSize": "clamp(32px, 4.4vw, 60px)", "lineHeight": "1.1"},
            "color": {"text": IVORY}, "_extra": "max-width:20ch;text-align:center;margin:0 auto 40px",
        })

        def cta():
            button('Quero fazer parte', style={
                "typography": {"fontFamily": SANS, "fontSize": "12px", "fontWeight": "500", "letterSpacing": "0.26em", "textTransform": "uppercase"},
                "color": {"text": INK, "background": BRONZE},
                "spacing": {"padding": {"top": "24px", "bottom": "24px", "left": "38px", "right": "38px"}},
                "border": {"radius": "999px"},
                "_extra": "display:inline-block;text-decoration:none",
            })
        buttons_block(cta, layout={"type": "flex", "justifyContent": "center"})
    group(inner, style={
        "color": {"background": INK},
        "spacing": {"padding": {"top": "120px", "bottom": "120px", "left": "clamp(20px,4vw,60px)", "right": "clamp(20px,4vw,60px)"}},
        "_extra": "box-sizing:border-box;text-align:center",
    })


# ============================================================
# FOOTER
# ============================================================
FOOTER_COLUMNS = [
    ('O Clube', ['Convite', 'A Placa', 'Níveis', 'Benefícios']),
    ('Contato', ['atelier@axell.com.br', '0800 000 0000', 'Seg. a Sex. 09h — 18h']),
    ('Institucional', ['Sobre a Axell', 'Showrooms', 'Política de privacidade', 'Regulamento do clube']),
]


def footer_section():
    def inner():
        def grid():
            def brand():
                paragraph('ATELIER AXELL', style={
                    "typography": {"fontFamily": SERIF, "fontSize": "15px", "letterSpacing": "0.3em", "textTransform": "uppercase"},
                    "color": {"text": IVORY}, "spacing": {"margin": {"bottom": "16px", "top": "0"}},
                })
                paragraph('O atelier é o lugar onde criar encontra morar. Um clube por convite para arquitetos e designers.', style={
                    "typography": {"fontFamily": SANS, "fontSize": "13.5px", "lineHeight": "1.7", "fontWeight": "300"},
                    "color": {"text": "rgba(239,236,228,.55)"},
                })
            group(brand)

            for title, links in FOOTER_COLUMNS:
                def col(title=title, links=links):
                    heading(5, title, style={
                        "typography": {"fontFamily": SANS, "fontSize": "11px", "letterSpacing": "0.2em", "textTransform": "uppercase"},
                        "color": {"text": BRONZE_2}, "spacing": {"margin": {"bottom": "18px", "top": "0"}},
                    })
                    items = [f'<span style="font-family:{SANS};font-size:13.5px;color:rgba(239,236,228,.6)">{l}</span>' for l in links]
                    list_block(items, style={"_extra": "list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:12px"})
                group(col)
        group(grid, style={"_extra": "display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;max-width:1280px;margin:0 auto"})

        def bottom():
            paragraph('© 2026 Axell. Todos os direitos reservados.', style={
                "typography": {"fontFamily": SANS, "fontSize": "12px"}, "color": {"text": "rgba(239,236,228,.4)"},
                "spacing": {"margin": {"top": "0", "bottom": "0"}},
            })
            paragraph('Atelier Axell Club · Edição Lumière · 2026', style={
                "typography": {"fontFamily": SANS, "fontSize": "12px"}, "color": {"text": "rgba(239,236,228,.4)"},
                "spacing": {"margin": {"top": "0", "bottom": "0"}},
            })
        group(bottom, style={"spacing": {"padding": {"top": "32px"}}, "_extra": f"display:flex;justify-content:space-between;border-top:1px solid rgba(239,236,228,.1);max-width:1280px;margin:32px auto 0"})
    group(inner, style={
        "color": {"background": INK},
        "spacing": {"padding": {"top": "80px", "bottom": "40px", "left": "clamp(20px,4vw,60px)", "right": "clamp(20px,4vw,60px)"}},
        "_extra": "box-sizing:border-box",
    })


# ============================================================
# ASSEMBLE — full page (nav/hero + all chaptered sections + cta/footer).
# The application form section is deliberately NOT included: axellcore/form
# and axellcore/form-input are custom blocks whose own save() markup is
# built around aac-field/aac-consent classNames baked into their JS — they
# aren't restyleable via this experiment's approach without rewriting the
# blocks themselves, which is a different exercise than "style the design
# via native attributes."
# ============================================================
PLUGIN_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

nav_section()
hero_section()
manifesto_section()
pillars_section()
placa_section()
protagonists_section()
promises_section()
concept_section()
journey_section()
tiers_section()
benefits_section()
editorial_section()
cta_strip_section()
footer_section()

with open(f'{PLUGIN_DIR}/content/noclass-full.html', 'w', encoding='utf-8') as f:
    f.write('\n'.join(OUT) + '\n')

print(f"wrote noclass-full.html: {len(OUT)} lines")
