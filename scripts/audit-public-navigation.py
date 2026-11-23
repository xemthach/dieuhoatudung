"""Rebuild the source inventory. Counts are matching source lines, compared with HEAD.

Run after `php artisan route:list --json` has been saved to the audit storage folder.
Generated assets are scanned by rg too; dependencies outside the requested roots are excluded.
"""
import argparse
import collections
import csv
import json
import io
from pathlib import Path
import re
import subprocess
import tarfile

parser = argparse.ArgumentParser()
parser.add_argument('--baseline', default='HEAD')
args = parser.parse_args()
BASELINE = args.baseline

ROOTS = ['app', 'config', 'database', 'resources', 'routes', 'public', 'tests']
OUT = Path('docs/audits/url-navigation-scope')
OUT.mkdir(parents=True, exist_ok=True)
LEGACY = r'điều hòa tủ đứng|dieu hoa tu dung|dieu-hoa-tu-dung|tu-dung|floor-standing'
TERMS = LEGACY + r'|so-sanh|bang-gia|san-pham'
URLS = r'''["']/(?:san-pham|dieu-hoa-tu-dung|so-sanh-san-pham|bang-gia|danh-muc|thuong-hieu|dai-ly|tim-kiem|bao-gia|lien-he|blog|faq|kien-thuc|cong-cu)[^\s"'<>]*["']'''
DOMAINS = r'dieuhoa-tudung\.test|dieuhoatudung\.com|chonmaylanh\.com'
BASELINE_FILES = None


def scan(pattern, baseline=False):
    if baseline:
        # Use the same Unicode-aware expression for both revisions; git's ERE
        # dialect and case folding differ from ripgrep on Windows.
        global BASELINE_FILES
        if BASELINE_FILES is None:
            archive = subprocess.check_output(['git', 'archive', BASELINE, *ROOTS])
            BASELINE_FILES = {}
            with tarfile.open(fileobj=io.BytesIO(archive)) as tar:
                for member in tar:
                    if not member.isfile():
                        continue
                    content = tar.extractfile(member).read()
                    if b'\x00' not in content:
                        BASELINE_FILES[member.name] = content.decode('utf-8', errors='replace')
        expression = re.compile(pattern, re.I)
        rows = []
        for path, content in BASELINE_FILES.items():
            for number, line in enumerate(content.splitlines(), 1):
                if expression.search(line):
                    rows.append({'file': path, 'line': number, 'text': line.strip()})
        return rows
    result = subprocess.run(['rg', '--json', '-i', pattern, *ROOTS], capture_output=True)
    rows = []
    for line in result.stdout.decode('utf-8').splitlines():
        item = json.loads(line)
        if item['type'] != 'match':
            continue
        data = item['data']
        rows.append({'file': data['path']['text'].replace('\\', '/'), 'line': data['line_number'], 'text': data['lines']['text'].strip()})
    return rows


def classify(row, kind):
    path, text = row['file'], row['text']
    if path.startswith('tests/'):
        return 'TEST_FIXTURE', 'Fixture hoặc assertion regression; không phải nội dung mặc định của website.'
    if path.startswith('database/migrations/'):
        return 'MIGRATION_HISTORY', 'Lịch sử dữ liệu đã triển khai; giữ nguyên migration cũ.'
    if path.startswith('routes/'):
        return 'ROUTE', 'Định nghĩa route canonical, endpoint POST, redirect 301 hoặc landing SEO riêng; không phải link tự dựng trong view.'
    if '/landing/' in path or path.endswith('LandingController.php'):
        return 'SEO_CONTENT', 'Landing SEO chuyên về danh mục tủ đứng, có URL riêng; không áp dụng làm phạm vi chung của site.'
    if path.endswith(('DemoProductSeeder.php', 'CatalogueGree2026Seeder.php', 'DemoDataSeeder.php', 'ProductFactory.php')):
        return 'VALID_PRODUCT_CATEGORY_REFERENCE', 'Tên, slug hoặc nội dung sản phẩm/danh mục tủ đứng thật trong dữ liệu mẫu/catalogue.'
    if any(token in path for token in ['TechnicalSchema', 'ImportGreeCatalogue', 'ProductTaxonomyAudit', 'ProductMarketingCapacityEligibility', 'hvac_equipment_types', 'resources/catalog/']):
        return 'VALID_PRODUCT_CATEGORY_REFERENCE', 'Taxonomy/schema kỹ thuật hoặc nguồn catalogue của loại máy tủ đứng; không giới hạn site vào loại máy này.'
    if path.endswith(('AISeoContentGenerator.php', 'HVACSeoContentEngine.php')) and kind == 'legacy':
        return 'VALID_PRODUCT_CATEGORY_REFERENCE', 'Một loại thiết bị trong danh sách kiến thức HVAC nhiều danh mục.'
    if path.endswith('MailTemplateRenderer.php'):
        return 'OTHER', 'Ví dụ xem trước template email hoặc biến URL; dữ liệu sản phẩm thật dùng named route.'
    if '/Filament/' in path:
        return 'OTHER', 'Placeholder/hướng dẫn nhập liệu Admin, không phải đích điều hướng public.'
    if path.endswith('app.js') and '/api/compare/add' in text:
        return 'OTHER', 'Ví dụ chú thích, không được thực thi.'
    if kind == 'legacy':
        return 'UNEXPLAINED', 'Cần rà soát thủ công.'
    if kind == 'domain':
        return 'OTHER', 'Cần rà soát domain trong cấu hình/ví dụ.'
    if path.startswith('public/'):
        return 'OTHER', 'Asset được build hoặc thư viện public; xem nội dung dòng trong CSV.'
    if 'href=' in text or 'fetch(' in text or 'url(' in text or 'location' in text:
        return 'URL', 'Named route, bộ lọc giữ URL hiện tại hoặc URL động từ resolver/model; xem dòng nguồn.'
    return 'OTHER', 'Khóa/spec/route name/nội dung liên quan đã được kiểm kê; xem dòng nguồn.'


def write_csv(name, rows, kind):
    with (OUT / name).open('w', newline='', encoding='utf-8-sig') as stream:
        writer = csv.DictWriter(stream, fieldnames=['file', 'line', 'classification', 'why_valid', 'text'])
        writer.writeheader()
        for row in sorted(rows, key=lambda r: (r['file'], r['line'])):
            classification, why = classify(row, kind)
            if name.endswith('-before.csv') and classification == 'UNEXPLAINED':
                classification, why = 'LEGACY_SITE_HARDCODE', 'Giả định phạm vi tủ đứng ở cấp website trong HEAD trước sửa.'
            writer.writerow({**row, 'classification': classification, 'why_valid': why})


stats = {'baseline': subprocess.check_output(['git', 'rev-parse', BASELINE], text=True).strip(), 'unit': 'matching_source_lines'}
for kind, pattern in [('legacy', LEGACY), ('hardcoded_urls', URLS), ('domain', DOMAINS)]:
    before, after = scan(pattern, True), scan(pattern)
    write_csv(kind + '-before.csv', before, kind)
    write_csv(kind + '-after.csv', after, kind)
    before_counts = collections.Counter((r['file'], r['text']) for r in before)
    after_counts = collections.Counter((r['file'], r['text']) for r in after)
    stats[kind] = {
        'before': len(before), 'remaining': len(after),
        'removed_or_rewritten': sum((before_counts - after_counts).values()),
        'added_or_rewritten': sum((after_counts - before_counts).values()),
        'unexplained': sum(classify(r, kind)[0] == 'UNEXPLAINED' for r in after),
    }
    if kind == 'legacy':
        lines = ['# Tham chiếu legacy còn giữ', '', 'Mỗi dòng có giải thích; xem CSV để đối chiếu nội dung đầy đủ.', '', '| File | Dòng | Phân loại | Lý do hợp lệ |', '|---|---:|---|---|']
        for row in sorted(after, key=lambda r: (r['file'], r['line'])):
            classification, why = classify(row, kind)
            lines.append(f"| {row['file']} | {row['line']} | {classification} | {why} |")
        (OUT / 'legacy-remaining.md').write_text('\n'.join(lines) + '\n', encoding='utf-8')
write_csv('full-text-url-inventory.csv', scan(TERMS), 'inventory')
(OUT / 'counts.json').write_text(json.dumps(stats, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')

route_file = Path('storage/framework/testing/url-scope-audit/routes.json')
raw = route_file.read_bytes()
routes = json.loads(raw.decode('utf-16' if raw.startswith(b'\xff\xfe') else 'utf-8-sig'))
lines = ['# Inventory route thực tế', '', '| Route name | Method | Current path | Controller | Purpose | Hardcoded product scope | Used by |', '|---|---|---|---|---|---|---|']
source_paths = subprocess.check_output(['rg', '--files', *ROOTS], text=True).splitlines()
source_text = {}
for path in source_paths:
    try:
        source_text[path.replace('\\', '/')] = Path(path).read_text(encoding='utf-8').splitlines()
    except (UnicodeError, OSError):
        pass
purposes = {
    'home': 'Trang chủ nhiều danh mục', 'products.index': 'Danh sách/lọc/sort sản phẩm',
    'product.show': 'Chi tiết sản phẩm và thông số canonical', 'category.show': 'Danh mục canonical từ model',
    'brands.index': 'Danh sách thương hiệu', 'brands.show': 'Sản phẩm theo thương hiệu',
    'compare.index': 'Bảng so sánh nhiều danh mục; tối đa 4 sản phẩm',
    'compare.add': 'POST thêm sản phẩm vào session', 'compare.remove': 'POST xóa sản phẩm, JSON hoặc redirect HTML',
    'compare.clear': 'POST xóa tất cả, JSON hoặc redirect HTML', 'price-list': 'Bảng giá tất cả sản phẩm có giá',
    'faq.dieu-hoa': 'FAQ chung', 'quote.index': 'Form báo giá chung', 'contact': 'Liên hệ',
    'dealers.index': 'Danh bạ đại lý; bộ lọc địa điểm', 'dealers.show': 'Chi tiết/review/bản đồ đại lý',
    'dealers.nearby': 'POST tọa độ khi người dùng chọn gần tôi', 'search.index': 'Tìm sản phẩm chung',
    'blog.index': 'Danh sách bài viết', 'blog.show': 'Chi tiết bài viết',
    'btu-calculator.index': 'Công cụ BTU nhiều loại thiết bị', 'landing': 'SEO riêng cho danh mục tủ đứng',
}
for route in routes:
    uri = route['uri']
    if uri.startswith(('admin', 'livewire', 'filament', 'storage', '_')):
        continue
    name = route['name'] or '(không đặt tên)'
    scope = 'Landing chuyên tủ đứng' if name.startswith('landing') else ('URL cũ: redirect GET / tương thích form POST' if 'tu-dung' in uri else 'Không')
    purpose = 'Đọc trang/tài nguyên public' if 'GET' in route['method'] else 'Mutation/form/API có middleware web'
    purpose = purposes.get(name, purpose)
    if not route['name'] and 'so-sanh-san-pham' in uri:
        purpose = 'GET fallback chỉ redirect, không đổi state'
    if not route['name'] and 'tu-dung' in uri:
        purpose = '301 về route chung; POST cũ giữ tương thích' if 'GET' in route['method'] else 'Tương thích form BTU cũ'
    references = []
    if route['name']:
        needle = re.compile(r'''['"]''' + re.escape(name) + r'''['"]''')
        for path, content in source_text.items():
            if path == 'routes/web.php' or path.startswith(('tests/', 'public/')):
                continue
            for number, text in enumerate(content, 1):
                if needle.search(text):
                    references.append(f'{path}:{number}')
    used = '; '.join(references[:8]) or ('Route trực tiếp / client theo URL của controller' if route['name'] else 'Link/bookmark/form lịch sử')
    lines.append(f"| {name} | {route['method'].replace('|', '/')} | /{uri if uri != '/' else ''} | {route['action']} | {purpose} | {scope} | {used} |")
(OUT / 'route-inventory.md').write_text('\n'.join(lines) + '\n', encoding='utf-8')
print(json.dumps(stats, ensure_ascii=False))
