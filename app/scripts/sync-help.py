#!/usr/bin/env python3
"""Actualise les documents publics embarqués (contenu français et styles, sans scripts du site).

Usage : python3 scripts/sync-help.py [--cache-dir /tmp]
Les instantanés sont autonomes : polices et schémas sont intégrés en data URI.
"""
import argparse
import base64
import re
import tempfile
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
WEB = ROOT / "Sources/Scenarisation/Web"
ORIGIN = "https://scenarisation.eu/"


def sync(cache):
    def fetch(name, url):
        path = cache / ("scenarisation-" + name)
        if not path.exists():
            with urllib.request.urlopen(url, timeout=30) as response:
                path.write_bytes(response.read())
        return path.read_bytes()

    def site(path):
        return fetch(path.replace("/", "-"), ORIGIN + path).decode()

    def clean(body):
        # Conserver le texte et les attributs sémantiques ; retirer les traductions et la navigation réseau implicite.
        body = re.sub(r'\sdata-[\w-]+="[^"]*"', "", body)
        body = re.sub(r'\s(?:target|rel)="[^"]*"', "", body)
        body = re.sub(r'href="(?!#|https?://|mailto:)([^"]+)"', lambda m: 'href="' + ORIGIN + m[1] + '"', body)

        def image(match):
            path = match[1]
            data = fetch(path.replace("/", "-"), ORIGIN + path)
            assert path.endswith(".svg"), path
            return 'src="data:image/svg+xml;base64,' + base64.b64encode(data).decode() + '"'
        body = re.sub(r'src="(assets/[^"]+)"', image, body)
        assert not re.search(r'<(?:script|iframe)\b|src="https?://', body)
        return body

    source = site("help.php")
    main = re.search(r'<main\b[^>]*>(.*?)</main>', source, re.S)[1]
    chapters = main[main.index('<article class="help-section"'):main.rindex('</article>') + len('</article>')]
    note = '<p class="note">Guide complet de la version web, consultable hors ligne. Les comptes, la publication, le CLI et certains contrôles décrits concernent le site. Pour les commandes de l’application, consultez <a href="https://scenarisation.eu/mac-help">Utiliser sur Mac</a> dans la barre latérale.</p>'
    WEB.joinpath("help-site.html").write_text('<div class="site-guide">\n' + note + '\n' + clean(chapters) + '\n</div>\n')

    page_styles = []
    all_content = chapters
    for name, cls in [("learning-design", "ld-document"), ("bloom", "bloom-document"), ("cadre-conversationnel", "cf-document")]:
        source = site(name + ".php")
        main = re.search(r'<main\b[^>]*>(.*?)</main>', source, re.S)[1]
        all_content += main
        WEB.joinpath(name + ".html").write_text('<div class="' + cls + '">\n' + clean(main) + '\n</div>\n')
        page_styles += re.findall(r'<style>(.*?)</style>', source, re.S)

    interface = site("css/interface.css")
    help_css = interface[interface.index('.help-section {'):interface.index('[data-theme="dark"] html:has(body.help-page)')]
    type_css = interface[interface.index('.help-type-read {'):interface.index('/* About page */')]
    WEB.joinpath("help-site.css").write_text('/* Styles des documents de scenarisation.eu, instantané du 15 novembre 2026 (CC BY-SA 4.0). */\n' + help_css + type_css + '\n'.join(page_styles) + site("css/cadre-conversationnel.css") + site("css/help-terminal.css"))

    # Font Awesome 6 : seuls les noms d’icônes utilisés, avec les trois fontes officielles intégrées.
    fa_origin = "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/"
    fa = fetch("fa.css", fa_origin + "css/all.min.css").decode()
    icons = set(re.findall(r'\bfa-(?!solid\b|regular\b|brands\b)([\w-]+)', all_content)) | {"check", "chevron-down"}
    css = '/* Font Awesome Free 6.5.2 — © Fonticons, Inc. Voir font-awesome-LICENSE.txt. */\n'
    for kind, weight, family in [("solid", 900, "Free"), ("regular", 400, "Free"), ("brands", 400, "Brands")]:
        filename = f"fa-{kind}-{weight}.woff2"
        font = base64.b64encode(fetch(filename, fa_origin + "webfonts/" + filename)).decode()
        css += f'@font-face{{font-family:"Font Awesome 6 {family}";font-style:normal;font-weight:{weight};font-display:block;src:url(data:font/woff2;base64,{font}) format("woff2")}}\n'
        css += f'.fa-{kind}{{font-family:"Font Awesome 6 {family}";font-weight:{weight};font-style:normal;display:inline-block;font-variant:normal;line-height:1;text-rendering:auto;-webkit-font-smoothing:antialiased}}\n'
    for icon in sorted(icons):
        match = re.search(r'\.fa-' + re.escape(icon) + r':before(?:,[^{]+)?\{content:"([^"]+)"\}', fa)
        assert match, "Icône absente : " + icon
        css += f'.fa-{icon}:before{{content:"{match[1]}"}}\n'
    WEB.joinpath("help-icons.css").write_text(css)
    WEB.joinpath("font-awesome-LICENSE.txt").write_bytes(fetch("fa-LICENSE.txt", "https://raw.githubusercontent.com/FortAwesome/Font-Awesome/6.5.2/LICENSE.txt"))
    print("Aide :", len(re.findall(r'<article class="help-section"', chapters)), "chapitres, 3 pages pédagogiques et", len(icons), "icônes embarquées.")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--cache-dir", type=Path)
    args = parser.parse_args()
    if args.cache_dir:
        args.cache_dir.mkdir(parents=True, exist_ok=True)
        sync(args.cache_dir)
    else:
        with tempfile.TemporaryDirectory() as directory:
            sync(Path(directory))
