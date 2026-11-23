"""Read-only local crawl; does not submit forms or follow external links."""
import argparse
import collections
from html.parser import HTMLParser
import json
from pathlib import Path
import ssl
import urllib.error
import urllib.parse
import urllib.request

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', required=True)
parser.add_argument('--output', required=True)
args = parser.parse_args()
origin = urllib.parse.urlparse(args.base_url).netloc


class Links(HTMLParser):
    def __init__(self):
        super().__init__()
        self.links = []

    def handle_starttag(self, tag, attrs):
        if tag == 'a':
            self.links.extend(value for key, value in attrs if key == 'href' and value)


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, response, code, message, headers, newurl):
        return None


# Laragon uses a local development certificate. This script is only an audit client.
opener = urllib.request.build_opener(NoRedirect, urllib.request.HTTPSHandler(context=ssl._create_unverified_context()))
queue = [(path, 0, '(seed)') for path in ['/', '/san-pham', '/so-sanh-san-pham', '/bang-gia', '/faq', '/thuong-hieu', '/dai-ly', '/blog', '/bao-gia']]
seen, rows = set(), []
while queue and len(seen) < 180:
    path, depth, source = queue.pop(0)
    if path in seen:
        continue
    seen.add(path)
    try:
        try:
            response = opener.open(args.base_url.rstrip('/') + path, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            status = response.code
            content_type = response.headers.get('Content-Type', '')
            html = response.read().decode('utf-8', errors='replace')
            row = {'path': path, 'status': status, 'source': source}
            if 300 <= status < 400:
                row['location'] = response.headers.get('Location')
                target = urllib.parse.urlparse(urllib.parse.urljoin(args.base_url, row['location']))
                if target.netloc == origin:
                    queue.append((target.path, depth, path))
            rows.append(row)
            if status == 200 and depth < 2 and 'text/html' in content_type:
                links = Links()
                links.feed(html)
                for href in links.links:
                    target = urllib.parse.urlparse(urllib.parse.urljoin(args.base_url + path, href))
                    if target.netloc == origin and '/export/' not in target.path and not target.path.startswith(('/admin', '/livewire', '/storage', '/build')):
                        queue.append((target.path, depth + 1, path))
    except Exception as error:
        rows.append({'path': path, 'status': 0, 'source': source, 'error': str(error)})

result = {'counts': dict(collections.Counter(row['status'] for row in rows)), 'urls': rows}
Path(args.output).write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print(json.dumps({'count': len(rows), 'statuses': result['counts']}))
raise SystemExit(1 if any(row['status'] == 0 or row['status'] >= 400 for row in rows) else 0)
