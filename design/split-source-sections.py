#!/usr/bin/env python3
"""Splits atelier-axell-club.html (the source) into one page per block, header to footer.

Each block lives in source/<name>/index.html (served on :8544 by Caddy) with the same head, fonts and CSS as
the source, so one block can be reviewed on its own. Run again after editing the
source: the folders are generated. The source stays the only file to edit.
"""
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parent
OUT = ROOT / 'source'
SRC = (ROOT / 'atelier-axell-club.html').read_text(encoding='utf-8')

SECTION_NAMES = [
    'hero', 'convite', 'manifesto', 'placa', 'protagonistas', 'promessas', 'conceito',
    'jornada', 'niveis', 'beneficios', 'editorial', 'cta-strip', 'adesao',
]
LABELS = {
    'header': 'Cabeçalho', 'hero': 'Hero', 'footer': 'Rodapé', 'convite': 'Convite',
    'manifesto': 'Manifesto', 'placa': 'A placa', 'protagonistas': 'Protagonistas',
    'promessas': 'Promessas', 'conceito': 'Conceito', 'jornada': 'Jornada',
    'niveis': 'Níveis', 'beneficios': 'Benefícios', 'editorial': 'Editorial',
    'cta-strip': 'Faixa de chamada', 'adesao': 'Adesão',
}
# Links from one block to another use the folder names.
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
    return SRC[i:SRC.index(end, i)]


head = SRC[:SRC.index('<body>')]
head = re.sub(r'<title>.*?</title>', '<title>{title} — Atelier Axell Club (source)</title>', head, count=1, flags=re.S)
assert '{title}' in head

header = block(r'^<header class="nav".*?^</header>')
footer = block(r'^<footer class="footer".*?^</footer>')
body = between('<body>', '<script>\n  // Nav scroll state')
sections = re.findall(r'^<section\b.*?^</section>', body, re.M | re.S)
assert len(sections) == len(SECTION_NAMES), (len(sections), len(SECTION_NAMES))

# Scripts that belong to a block (from the page's final <script>).
nav_script = between('  // Nav scroll state', '  onScroll();\n') + '  onScroll();\n'
form_script = between('  // Document mask CPF / CNPJ', '  // Reveal on scroll')


def fix_paths(text):
    # source/<name>/ sits next to source/assets (copied by make sync).
    return re.sub(r"(?<![\w./-])assets/", '../assets/', text)


def fix_anchors(text):
    def swap(match):
        target = match.group(1)
        return f'href="../{ANCHORS[target]}/"' if target in ANCHORS else match.group(0)
    return re.sub(r'href="#([\w-]+)"', swap, text)


def page(name, body_html, script=''):
    html = head.replace('{title}', LABELS[name])
    out = f'{html}<body>\n{body_html}\n'
    if script:
        out += f'<script>\n{script}</script>\n'
    out += '</body>\n</html>\n'
    return fix_anchors(fix_paths(out))


def write(name, html):
    folder = OUT / name
    folder.mkdir(parents=True, exist_ok=True)
    (folder / 'index.html').write_text(html, encoding='utf-8')
    print(f'source/{name}/index.html')


write('header', page('header', header, nav_script))
for name, section in zip(SECTION_NAMES, sections):
    script = form_script if name == 'adesao' else ''
    write(name, page(name, section, script))
write('footer', page('footer', footer))
