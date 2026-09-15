"""Build the detailed CRCN catalogue from the official Eduscol PDF transcription."""
import json
import re
import sys
import unicodedata
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
SOURCE = ROOT / 'data/references/crcn-eduscol.json'
CATALOG = ROOT / 'js/competency-catalog.js'


def clean(text):
    return re.sub(r'\s+', ' ', unicodedata.normalize('NFC', text)).strip()


def parse_source():
    source = json.loads(SOURCE.read_text())
    text = '\n'.join(source['lines'])
    text = text[re.search(r'Compétence 1\.1 [^\n]+\nDe quoi', text).start():]
    # Page boundaries sometimes join the previous bullet to the next heading.
    chunks = re.split(r'Compétence (\d\.\d) ', text)[1:]
    competencies = []
    for index in range(0, len(chunks), 2):
        code, body = chunks[index:index+2]
        title, body = body.split('\n', 1)
        _, body = body.split('De quoi s’agit-il ? (Décret n°2019-919 du 30 août 2019)', 1)
        description, body = re.split(r'Références? au socle commun de connaissances, de compétences et de culture', body, maxsplit=1)
        socle, body = body.split('Thématiques et mots-clés associés (Pix)', 1)
        themes, body = body.split('Niveaux de maîtrise des compétences numériques et repères pour enseigner', 1)
        levels = re.split(r'Niveau ([1-5])\s*\n', body)[1:]
        indicators = []
        for j in range(0, len(levels), 2):
            level, content = levels[j:j+2]
            bullets = [clean(b) for b in content.split('') if clean(b)]
            for n, bullet in enumerate(bullets, 1):
                indicators.append({'level': int(level), 'order': n, 'text': bullet})
        competencies.append({'code': code, 'title': clean(title), 'description': clean(description),
            'socle': [clean(b) for b in socle.split('') if clean(b)], 'themes': clean(themes),
            'page': 3 + index // 2, 'indicators': indicators})
    assert len(competencies) == 16
    return source, competencies


def build():
    source, competencies = parse_source()
    domains = {
        '1': ('information', '1 · Informations et données', '1 · Information and data'),
        '2': ('communication', '2 · Communication et collaboration', '2 · Communication and collaboration'),
        '3': ('creation', '3 · Création de contenus', '3 · Content creation'),
        '4': ('protection', '4 · Protection et sécurité', '4 · Protection and security'),
        '5': ('environnement', '5 · Environnement numérique', '5 · Digital environment'),
    }
    lines = []
    def add(*fields):
        assert all('\t' not in f and '\n' not in f and '`' not in f and '${' not in f for f in fields)
        lines.append('\t'.join(fields))
    add('# framework', 'crcn', 'CRCN', 'French digital competency framework (CRCN)', source['source'])
    domain = None
    for item in competencies:
        code, title = item['code'], item['title']
        if domain != code[0]:
            domain = code[0]
            add('## group', *domains[domain])
        url = source['source'] + '#page=' + str(item['page'])
        add('### subgroup', code, code + ' · ' + title, code + ' · ' + title)
        description = item['description']
        # Keep the 16 existing IDs so saved scenarios continue to resolve.
        add(code, title, description, title, description, url)
        for indicator in item['indicators']:
            n = indicator['level']
            mastery = 'Novice' if n <= 2 else 'Indépendant' if n <= 4 else 'Avancé'
            description = f'Niveau {n} · {mastery} · {code} — {title}'
            add(f'{code}.N{n}.{indicator["order"]}', indicator['text'], description,
                indicator['text'], description, url)
    return '\n'.join(lines) + '\n', competencies


if __name__ == '__main__':
    block, items = build()
    current = CATALOG.read_text()
    start = current.index('# framework\tcrcn\t')
    end = current.index('# framework\tpix\t', start)
    expected = current[:start] + block + current[end:]
    if '--check' in sys.argv:
        if current != expected:
            raise SystemExit('CRCN catalogue differs from the Eduscol source transcription')
    else:
        CATALOG.write_text(expected)
    print(f'{len(items)} compétences, {sum(len(x["indicators"]) for x in items)} repères')
