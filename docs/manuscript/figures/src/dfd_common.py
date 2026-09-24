"""Shared layout for the AniHow DFD figures (Yourdon notation, matching Figure 6).

Each process gets its own row: the external entities it talks to on the left,
its data stores on the right. An entity or store drawn in more than one row is
marked with an asterisk, as DFD convention allows for duplicated symbols.
"""
EXT = {'sa': ('Super Admin', '#E1E6ED'), 'ce': ('Content Editor', '#E1E6ED'),
       'fs': ('Farmer-Seller', '#E8E6E0'), 'by': ('Buyer', '#E8E6E0'),
       'em': ('Email Service', '#F2F2F2'), 'sch': ('Scheduler', '#F2F2F2')}
STORES = {
 'D1': 'Users and Roles', 'D2': 'Farms', 'D3': 'Crop Types', 'D4': 'Listings',
 'D5': 'Cart Items', 'D6': 'Orders', 'D7': 'Order Messages', 'D8': 'Notifications',
 'D9': 'Reviews', 'D10': 'Favorites', 'D11': 'Crop-Care Articles', 'D12': 'Farm Announcements',
 'D13': 'FAQ Entries', 'D14': 'Export Logs', 'D15': 'Deletion Requests', 'D16': 'Codes and Reset Tokens',
}

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
        out.append(f'  {key} [shape=circle, style=filled, fillcolor="#F4F4F4", penwidth=1.4, fixedsize=true, width=1.35, fontsize=10.5, label="{num}\\n\\n{name}"];')
        left, right = [], []
        for n, rows in row_of.items():
            if key not in rows: continue
            star = '*' if n in dup else ''
            if n in EXT:
                nm, fill = EXT[n]
                left.append(nid(n, key))
                out.append(f'  {nid(n,key)} [shape=box, style=filled, fillcolor="{fill}", penwidth=1.2, width=1.35, height=0.42, label="{nm}{star}"];')
            else:
                right.append(nid(n, key))
                out.append(f'  {nid(n,key)} [shape=plaintext, label=<<TABLE BORDER="0" CELLBORDER="1" CELLSPACING="0" CELLPADDING="3">'
                           f'<TR><TD SIDES="TBL" WIDTH="30"><B>{n}</B></TD><TD SIDES="TB" ALIGN="LEFT">{STORES[n]}{star}</TD></TR></TABLE>>];')
    ranks = {'L': [], 'P': list(pk), 'R': []}
    for a, b, label in flows:
        if a in pk and b in pk:
            out.append(f'  {a} -> {b} [label="{label}", constraint=false];')
            continue
        p = a if a in pk else b
        other = b if a in pk else a
        o = nid(other, p)
        (ranks['L'] if other in EXT else ranks['R']).append(o)
        if other in EXT:   # entity on the left: draw left-to-right, flip arrow when flow goes to the entity
            out.append(f'  {o} -> {p} [label="{label}"{", dir=back" if a == p else ""}];')
        else:              # store on the right
            out.append(f'  {p} -> {o} [label="{label}"{", dir=back" if b == p else ""}];')
    for r in ('L', 'R'):
        if ranks[r]: out.append('  {rank=same; ' + '; '.join(dict.fromkeys(ranks[r])) + '}')
    out.append('  {rank=same; ' + '; '.join(pk) + '}')
    # keep rows in process order
    for x, y in zip(pk, pk[1:]):
        out.append(f'  {x} -> {y} [style=invis, weight=10];')
    out.append('}')
    return '\n'.join(out) + '\n'
