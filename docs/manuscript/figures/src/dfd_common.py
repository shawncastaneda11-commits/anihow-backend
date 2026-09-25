"""Shared layout and rendering for the AniHow DFD figures, in Gane-Sarson notation.

Processes are rounded rectangles with the process number in a top compartment;
external entities are rectangles; data stores are open-ended rectangles with the
store ID in a left compartment. Each process gets its own row: the external
entities it talks to on the left, its data stores on the right. A symbol drawn
more than once in a diagram carries the Gane-Sarson duplicate mark: a diagonal
stroke across the lower-right corner of an entity, and a second vertical line on
the left edge of a data store.
"""
import json, re, subprocess

EXT = {'sa': ('Super Admin', '#E1E6ED'), 'ce': ('Content Editor', '#E1E6ED'),
       'fs': ('Farmer-Seller', '#E8E6E0'), 'by': ('Buyer', '#E8E6E0'),
       'em': ('Email Service', '#F2F2F2'), 'sch': ('Scheduler', '#F2F2F2')}
STORES = {
 'D1': 'Users and Roles', 'D2': 'Farms', 'D3': 'Crop Types', 'D4': 'Listings',
 'D5': 'Cart Items', 'D6': 'Orders', 'D7': 'Order Messages', 'D8': 'Notifications',
 'D9': 'Reviews', 'D10': 'Favorites', 'D11': 'Crop-Care Articles', 'D12': 'Farm Announcements',
 'D13': 'FAQ Entries', 'D14': 'Export Logs', 'D15': 'Deletion Requests', 'D16': 'Codes and Reset Tokens', 'D17': 'Reports',
}

def process_label(num, name, fontsize=10.5):
    lines = '<BR/>'.join(name.split('\\n'))
    return (f'<<TABLE STYLE="ROUNDED" BORDER="1" CELLBORDER="0" CELLSPACING="0" CELLPADDING="3" BGCOLOR="#F4F4F4">'
            f'<TR><TD><FONT POINT-SIZE="{fontsize}">{num}</FONT></TD></TR><HR/>'
            f'<TR><TD CELLPADDING="5"><FONT POINT-SIZE="{fontsize}">{lines}</FONT></TD></TR></TABLE>>')

def store_label(d, dup=False):
    extra = '<TD SIDES="L" WIDTH="3" CELLPADDING="0"></TD>' if dup else ''
    return (f'<<TABLE BORDER="0" CELLBORDER="1" CELLSPACING="0" CELLPADDING="3"><TR>{extra}'
            f'<TD SIDES="TBL" WIDTH="30"><B>{d}</B></TD><TD SIDES="TB" ALIGN="LEFT">{STORES[d]}</TD></TR></TABLE>>')

def render(title, procs, flows, rowsep=0.25):
    """procs: [(key, number, name)], flows: [(src, dst, label)]; src/dst are proc keys, EXT keys or store ids."""
    pk = [p[0] for p in procs]
    row_of = {}
    for a, b, _ in flows:
        if a in pk and b not in pk: row_of.setdefault(b, set()).add(a)
        if b in pk and a not in pk: row_of.setdefault(a, set()).add(b)
    dup = {n for n, rows in row_of.items() if len(rows) > 1}
    out = [f'// {title}', 'digraph G {',
           '  graph [rankdir=LR, nodesep=%s, ranksep=0.9, pad=0.25, fontname="Liberation Sans", newrank=true];' % rowsep,
           '  node  [fontname="Liberation Sans", fontsize=11];',
           '  edge  [penwidth=0.9, arrowsize=0.6, color="#333333", fontname="Liberation Sans", fontsize=8.5];']
    def nid(n, p): return f'{n}_{p}'
    for key, num, name in procs:
        out.append(f'  {key} [shape=plaintext, margin=0, width=0, height=0, label={process_label(num, name)}];')
        for n, rows in row_of.items():
            if key not in rows: continue
            if n in EXT:
                nm, fill = EXT[n]
                mark = ', comment="gsdup"' if n in dup else ''
                out.append(f'  {nid(n,key)} [shape=box, style=filled, fillcolor="{fill}", penwidth=1.2, width=1.35, height=0.46, label="{nm}"{mark}];')
            else:
                out.append(f'  {nid(n,key)} [shape=plaintext, margin=0, width=0, height=0, label={store_label(n, n in dup)}];')
    ranks = {'L': [], 'R': []}
    for a, b, label in flows:
        if a in pk and b in pk:
            out.append(f'  {a} -> {b} [label="{label}", constraint=false];')
            continue
        p = a if a in pk else b
        other = b if a in pk else a
        o = nid(other, p)
        (ranks['L'] if other in EXT else ranks['R']).append(o)
        if other in EXT:
            out.append(f'  {o} -> {p} [label="{label}"{", dir=back" if a == p else ""}];')
        else:
            out.append(f'  {p} -> {o} [label="{label}"{", dir=back" if b == p else ""}];')
    for r in ('L', 'R'):
        if ranks[r]: out.append('  {rank=same; ' + '; '.join(dict.fromkeys(ranks[r])) + '}')
    out.append('  {rank=same; ' + '; '.join(pk) + '}')
    for x, y in zip(pk, pk[1:]):
        out.append(f'  {x} -> {y} [style=invis, weight=10];')
    out.append('}')
    return '\n'.join(out) + '\n'

def draw(dot_path, png_path, svg_path, dpi=200):
    """Render a DFD .dot to PNG and SVG and add the Gane-Sarson duplicate stroke to marked entities."""
    from PIL import Image, ImageDraw
    subprocess.run(['dot', '-Tpng', f'-Gdpi={dpi}', dot_path, '-o', png_path], check=True)
    subprocess.run(['dot', '-Tsvg', dot_path, '-o', svg_path], check=True)
    g = json.loads(subprocess.run(['dot', '-Tjson', dot_path], check=True, capture_output=True, text=True).stdout)
    bb = [float(v) for v in g['bb'].split(',')]
    marks = []
    for o in g.get('objects', []):
        if o.get('comment') == 'gsdup':
            x, y = (float(v) for v in o['pos'].split(','))
            w, h = float(o['width']) * 72, float(o['height']) * 72
            marks.append((x + w / 2, y - h / 2))      # lower-right corner, points, origin bottom-left
    c = 11.0                                           # stroke length along each edge, points
    im = Image.open(png_path).convert('RGB')
    s = dpi / 72.0
    pad_x = (im.size[0] / s - (bb[2] - bb[0])) / 2
    pad_y = (im.size[1] / s - (bb[3] - bb[1])) / 2
    d = ImageDraw.Draw(im)
    for rx, by in marks:
        x0, y0 = (rx - c + pad_x) * s, (bb[3] - (by) + pad_y) * s
        x1, y1 = (rx + pad_x) * s, (bb[3] - (by + c) + pad_y) * s
        d.line((x0, y0, x1, y1), fill=(0, 0, 0), width=max(2, int(1.2 * s)))
    im.save(png_path)
    svg = open(svg_path).read()
    lines = ''.join(f'<line x1="{rx-c:.2f}" y1="{-by:.2f}" x2="{rx:.2f}" y2="{-(by+c):.2f}" stroke="black" stroke-width="1.2"/>' for rx, by in marks)
    svg = re.sub(r'(<g id="graph0"[^>]*>)', lambda m: m.group(1) + lines, svg, count=1)
    open(svg_path, 'w').write(svg)
