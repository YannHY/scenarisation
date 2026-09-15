"""Build PER entries from the CIIP table transcription (including merged cells).

French text is preserved, with whitespace flattened only. P/A suffixes reference
CIIP cell anchors; component IDs retain the IDs already stored in scenarios.
Run with --check to verify the committed catalogue against its source snapshot.
"""
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SOURCE = ROOT / 'data/references/per-numerique.json'
CATALOG = ROOT / 'js/competency-catalog.js'


def grid(rows):
    result = []
    for ri, row in enumerate(rows):
        while len(result) <= ri:
            result.append({})
        col = 0
        for cell in row:
            while col in result[ri]:
                col += 1
            for rr in range(ri, ri + cell['row']):
                while len(result) <= rr:
                    result.append({})
                for cc in range(col, col + cell['col']):
                    assert cc not in result[rr], 'Overlapping CIIP cells'
                    result[rr][cc] = cell
            col += cell['col']
    return result


def build():
    source = json.loads(SOURCE.read_text())
    lines = ['\t'.join(['# framework', 'per-romand', 'PER romand · Éducation numérique', 'PER romand · Digital education', source['source']])]
    counts = {'components': 0, 'progressions': 0, 'expectations': 0}
    def add(*fields):
        assert all('\t' not in f and '\n' not in f and '`' not in f and '${' not in f for f in fields)
        lines.append('\t'.join(fields))
    for page in source['pages']:
        code = page['title'].split(' - ')[0]
        cycle = code[3]
        url = f"https://portail.ciip.ch/per/learning-objectives/{page['id']}"
        add('## group', code.lower().replace(' ', ''), page['title'], page['title'], url)
        add('### subgroup', 'composantes', page['title'] + ' · Composantes', page['title'] + ' · Components')
        for component in page['components']:
            match = re.fullmatch(r'(\d+)(.*)', component)
            n, text = match.groups()
            add(code + '.' + n, text, f'Cycle {cycle} · Composante {n} · {page["title"]}', text, f'Cycle {cycle} · Component {n} · Official French text', url)
            counts['components'] += 1
        ncols = page['rows'][0][0]['col']
        expanded = grid(page['rows'])
        years = [expanded[1][c]['text'] for c in range(ncols)]
        section = None
        seen = set()
        for ri, row in enumerate(page['rows'][2:], 2):
            if row[0]['heading']:
                section = row[0]
                add('### subgroup', section['id'], code + ' · ' + section['text'], code + ' · ' + section['text'])
                continue
            if section is None:
                continue
            for cell in row:
                if not cell['text'] or cell['col'] > ncols or cell['id'] in seen:
                    continue
                columns = [c for c, v in expanded[ri].items() if v is cell]
                start = min(columns)
                if start > ncols:
                    continue  # Teaching guidance belongs in descriptions, not selectable entries.
                seen.add(cell['id'])
                progression = start < ncols
                kind = 'Progression des apprentissages' if progression else 'Attente fondamentale'
                period = ' / '.join(years[c] for c in columns) if progression else "Au cours, mais au plus tard à la fin du cycle, l'élève…"
                desc = f'Cycle {cycle} · {kind} · {period}'
                if progression:
                    expectation = expanded[ri].get(ncols, {}).get('text', '')
                    if expectation:
                        desc += ' — Attente fondamentale : ' + expectation
                guidance = expanded[ri].get(ncols + 1, {}).get('text', '')
                if guidance:
                    desc += ' — Indications pédagogiques : ' + guidance
                suffix = ('P' if progression else 'A') + cell['id']
                add(code + '.' + suffix, cell['text'], desc, cell['text'], desc, url + '#' + cell['id'])
                counts['progressions' if progression else 'expectations'] += 1
    return '\n'.join(lines) + '\n', counts


if __name__ == '__main__':
    block, counts = build()
    current = CATALOG.read_text()
    start = current.index('# framework\tper-romand\t')
    end = current.index('# framework\tcrcn\t', start)
    expected = current[:start] + block + current[end:]
    if '--check' in sys.argv:
        if expected != current:
            raise SystemExit('PER catalogue differs from its official source transcription')
    else:
        CATALOG.write_text(expected)
    print(counts)
