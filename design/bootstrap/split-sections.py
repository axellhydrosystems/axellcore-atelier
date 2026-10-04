#!/usr/bin/env python3
"""Splits index.html into one page per block (header, hero, sections, footer).

Each page lives in its own folder (bootstrap/<name>/index.html) with the same
head, fonts and stylesheet as index.html, so a single block can be reviewed on
its own. Run it again after editing index.html: the folders are generated.
"""
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parent
SRC = (ROOT / 'index.html').read_text(encoding='utf-8')

# Sections inside <main>, in page order (ids/classes checked below).
MAIN_NAMES = [
    'convite', 'manifesto', 'placa', 'protagonistas', 'promessas', 'conceito',
    'jornada', 'niveis', 'beneficios', 'editorial', 'cta-strip', 'adesao',
]

LABELS = {
    'header': 'Cabeçalho', 'hero': 'Hero', 'footer': 'Rodapé',
    'convite': 'Convite', 'manifesto': 'Manifesto', 'placa': 'A placa',
    'protagonistas': 'Protagonistas', 'promessas': 'Promessas',
    'conceito': 'Conceito', 'jornada': 'Jornada', 'niveis': 'Níveis',
    'beneficios': 'Benefícios', 'editorial': 'Editorial',
    'cta-strip': 'Faixa de chamada', 'adesao': 'Adesão',
}

# Anchor targets: links from one page to another use the folder names.
ANCHORS = {
    'top': 'hero', 'convite': 'convite', 'placa': 'placa', 'promessas': 'promessas',
    'jornada': 'jornada', 'niveis': 'niveis', 'beneficios': 'beneficios',
    'editorial': 'editorial', 'adesao': 'adesao',
}


def block(pattern):
    match = re.search(pattern, SRC, re.M | re.S)
    assert match, pattern
    return match.group(0)


def between(start, end):
    i = SRC.index(start)
    j = SRC.index(end, i)
    return SRC[i:j]


header = block(r'^<header class="nav".*?^</header>')
hero = block(r'^<section class="hero".*?^</section>')
footer = block(r'^<footer class="footer".*?^</footer>')

main_inner = between('<main>', '</main>')
sections = re.findall(r'^<section\b.*?^</section>', main_inner, re.M | re.S)
assert len(sections) == len(MAIN_NAMES), (len(sections), len(MAIN_NAMES))

head = SRC[:SRC.index('<body>')]
head = head.replace('<title>Atelier Axell Club — Um clube por convite para arquitetos e designers</title>',
                    '<title>{title} — Atelier Axell Club (protótipo)</title>')
assert '{title}' in head

# Scripts that belong to specific blocks.
nav_script = between('  // Nav scroll state', '  onScroll();\n') + '  onScroll();\n'
form_script = between('  // Document mask CPF / CNPJ', '  // Reveal on scroll')


def fix_paths(text):
    text = re.sub(r'(?<![\w./-])assets/', '../assets/', text)
    text = text.replace('href="style.min.css"', 'href="../style.min.css"')
    return text


def fix_anchors(text):
    def swap(match):
        target = match.group(1)
        return f'href="../{ANCHORS[target]}/"' if target in ANCHORS else match.group(0)
    return re.sub(r'href="#([\w-]+)"', swap, text)


# Bootstrap CSS from the CDN, used by the test pages in bs/ (markup first).
CDN_CSS = ('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" '
           'rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" '
           'crossorigin="anonymous">')
BS_ADESAO = (ROOT / 'bs' / 'adesao.html').read_text(encoding='utf-8')


def page(name, body, script='', css=None):
    html = head.replace('{title}', LABELS[name])
    if css:
        html = html.replace('<link rel="stylesheet" href="style.min.css">', css)
        assert css in html
    out = f'{html}<body>\n{body}\n'
    if script:
        out += f'<script>\n{script}</script>\n'
    out += '</body>\n</html>\n'
    return fix_anchors(fix_paths(out))


def write(name, html):
    folder = ROOT / name
    folder.mkdir(exist_ok=True)
    (folder / 'index.html').write_text(html, encoding='utf-8')
    print(f'{name}/index.html')


write('header', page('header', header, nav_script))
write('hero', page('hero', hero))
for name, section in zip(MAIN_NAMES, sections):
    if name == 'adesao':
        write(name, page(name, f'<main>\n{BS_ADESAO}</main>', form_script, css=CDN_CSS))
        continue
    write(name, page(name, f'<main>\n{section}\n</main>'))
write('footer', page('footer', footer))
