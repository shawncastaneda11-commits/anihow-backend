"""Generates the system flowcharts (overall and per role) and the implementation plan.

Standard flowchart symbols: rounded terminator (Start/End), rectangle for a
process, diamond for a decision, parallelogram for input or output, and a small
circle for an on-page connector. Run from docs/manuscript/figures.
"""
import subprocess

HEAD = '''digraph G {
  graph [rankdir=TB, nodesep=0.5, ranksep=0.6, pad=0.4, fontname="Liberation Sans", newrank=true, splines=polyline, bgcolor="white"];
  node  [fontname="Liberation Sans", fontsize=12, penwidth=1, color="#333333", style=filled, fillcolor="#FFFFFF", margin="0.26,0.16"];
  edge  [penwidth=1, arrowsize=0.7, color="#333333", fontname="Liberation Sans", fontsize=11];
'''
def term(k, t): return f'  {k} [shape=box, style="rounded,filled", fillcolor="#F8F9FA", width=1.3, height=0.5, margin="0.2,0.1", label="{t}"];\n'
def proc(k, t): return f'  {k} [shape=box, label="{t}"];\n'
def dec(k, t):  return f'  {k} [shape=diamond, fillcolor="#FFFFFF", label="{t}", margin="0.20,0.10"];\n'
def io(k, t):   return f'  {k} [shape=parallelogram, fillcolor="#F8F9FA", label="{t}", margin="0.30,0.12"];\n'
def conn(k, t): return f'  {k} [shape=circle, width=0.5, fixedsize=true, fillcolor="#F8F9FA", label="{t}"];\n'
def e(a, b, l=None, **kw):
    attrs = [f'label="{l}"'] if l else []
    attrs += [f'{k}={v}' for k, v in kw.items()]
    return f'  {a} -> {b}' + (f' [{", ".join(attrs)}]' if attrs else '') + ';\n'

def save(name, body, lr=False):
    head = HEAD.replace('rankdir=TB, nodesep=0.35, ranksep=0.32', 'rankdir=LR, nodesep=0.22, ranksep=0.35') if lr else HEAD
    open(f'src/{name}.dot', 'w').write(head + body + '}\n')
    subprocess.run(['dot', '-Tpng', '-Gdpi=200', f'src/{name}.dot', '-o', f'{name}.png'], check=True)
    subprocess.run(['dot', '-Tsvg', f'src/{name}.dot', '-o', f'{name}.svg'], check=True)

def role_chart(name, login_label, home_label, tasks):
    """Ladder layout: a vertical column of task decisions; each Yes branch runs across
    as one row of steps and ends in connector R, which returns to the dashboard.
    tasks: [(question, [steps])]; a step is ('p'|'io', text) or ('d', text, no_branch_text)."""
    b = term('s', 'Start') + io('li', login_label) + dec('ok', 'Credentials valid\\nand account active?') + proc('err', 'Show error message')
    b += conn('ain', 'R') + proc('home', home_label) + proc('lo', 'Log out') + term('end', 'End')
    b += e('s', 'li') + e('li', 'ok') + e('ok', 'err', 'No') + e('err', 'li', constraint='false') + e('ok', 'home', 'Yes') + e('ain', 'home')
    b += '  {rank=same; ok err}\n  {rank=same; home ain}\n'
    prev = 'home'
    for i, (question, steps) in enumerate(tasks):
        d = f'q{i}'
        b += dec(d, question)
        gap = {'minlen': 2} if prev != 'home' and any(st[0] == 'd' for st in tasks[i - 1][1]) else {}
        b += e(prev, d, 'No' if prev != 'home' else None, **gap)
        row = [d]
        last = d; after_decision = True
        for j, st in enumerate(steps):
            k = f's{i}_{j}'
            b += {'p': proc, 'd': dec, 'io': io}[st[0]](k, st[1])
            b += e(last, k, 'Yes' if (last == d or last.startswith('s') and steps[int(last.split('_')[1])][0] == 'd') else None)
            row.append(k)
            if st[0] == 'd':
                alt = f'a{i}_{j}'
                b += proc(alt, st[2]) + conn(f'x{alt}', 'R')
                b += e(k, alt, 'No') + e(alt, f'x{alt}') + f'  {{rank=same; {alt}; x{alt}}}\n'
            last = k
        b += conn(f'r{i}', 'R') + e(last, f'r{i}')
        row.append(f'r{i}')
        b += '  {rank=same; ' + '; '.join(row) + '}\n'
        prev = d
    gap = {'minlen': 2} if any(st[0] == 'd' for st in tasks[-1][1]) else {}
    b += e(prev, 'lo', 'No', **gap) + e('lo', 'end')
    trunk = ['s', 'li', 'ok', 'home', 'lo', 'end'] + [f'q{i}' for i in range(len(tasks))]
    b += ''.join(f'  {k} [group=trunk];\n' for k in trunk)
    return b

# ---------------------------------------------------------------- Figure: overall
# Dedicated, publication-grade layout: orthogonal routing, grayscale fills,
# padded nodes, aligned dashboard and connector baselines.
def build_overall():
    FONT = 'fontname="Liberation Sans"'
    o = ['digraph G {',
         '  graph [rankdir=TB, splines=ortho, nodesep=0.65, ranksep=0.6, pad=0.5, %s, newrank=true, bgcolor="white"];' % FONT,
         '  node  [%s, fontsize=17, penwidth=1.2, color="#333333", style=filled, fillcolor="#FFFFFF"];' % FONT,
         '  edge  [penwidth=1.2, arrowsize=0.8, color="#333333", %s, fontsize=15];' % FONT]
    def term(k, t): o.append(f'  {k} [shape=box, style="rounded,filled", fillcolor="#F8F9FA", width=1.4, height=0.55, label="{t}"];')
    def proc(k, t): o.append(f'  {k} [shape=box, margin="0.30,0.18", label="{t}"];')
    def dec(k, t):  o.append(f'  {k} [shape=diamond, margin="0.24,0.12", label="{t}"];')
    def io(k, t):   o.append(f'  {k} [shape=parallelogram, fillcolor="#F8F9FA", margin="0.34,0.16", label="{t}"];')
    def conn(k, t): o.append(f'  {k} [shape=circle, width=0.52, fixedsize=true, fillcolor="#F8F9FA", label="{t}"];')
    def xe(a, b, l=None, **kw):
        at = [f'xlabel="{l}"'] if l else []
        at += [f'{k}={v}' for k, v in kw.items()]
        o.append(f'  {a} -> {b}' + (f' [{", ".join(at)}]' if at else '') + ';')
    term('s', 'Start')
    proc('open', 'Open AniHow: Android application\\nor content management panel')
    dec('acct', 'Has an\\naccount?')
    io('reg', 'Register as a buyer\\n(name, email, password)')
    io('code', 'Receive six-digit\\ncode by email')
    dec('valid', 'Code correct and\\nwithin ten minutes?')
    proc('resend', 'Request a\\nnew code')
    io('login', 'Enter email\\nand password')
    dec('ok', 'Credentials valid and\\naccount active?')
    proc('err', 'Show error\\nmessage')
    dec('role', 'Role of\\nthe user?')
    proc('sa', 'Super Admin dashboard\\n(content management panel)')
    proc('ce', 'Content Editor dashboard\\n(own farm only)')
    proc('fs', 'Farmer-seller home\\n(Android application)')
    proc('by', 'Buyer marketplace\\n(Android application)')
    conn('A', 'A'); conn('B', 'B'); conn('C', 'C'); conn('D', 'D')
    proc('tasks', 'Perform role tasks\\n(Figures 6 to 9)')
    dec('out', 'Log out?')
    term('end', 'End')
    # main flow
    xe('s', 'open'); xe('open', 'acct')
    xe('acct', 'reg', 'No'); xe('reg', 'code'); xe('code', 'valid')
    xe('valid', 'resend', 'No'); xe('resend', 'code', constraint='false')
    xe('valid', 'login', 'Yes'); xe('acct', 'login', 'Yes')
    xe('login', 'ok'); xe('ok', 'err', 'No'); xe('err', 'login', constraint='false'); xe('ok', 'role', 'Yes')
    xe('role', 'sa', 'Super Admin'); xe('role', 'ce', 'Content Editor')
    xe('role', 'fs', 'Farmer-seller'); xe('role', 'by', 'Buyer')
    xe('sa', 'A'); xe('ce', 'B'); xe('fs', 'C'); xe('by', 'D')
    xe('A', 'tasks'); xe('B', 'tasks'); xe('C', 'tasks'); xe('D', 'tasks')
    xe('tasks', 'out'); xe('out', 'tasks', 'No', constraint='false'); xe('out', 'end', 'Yes')
    # aligned baselines
    o.append('  {rank=same; sa; ce; fs; by}')
    o.append('  {rank=same; A; B; C; D}')
    # keep the spine straight
    for k in ['s', 'open', 'acct', 'login', 'ok', 'role', 'tasks', 'out', 'end']:
        o.append(f'  {k} [group=spine];')
    o.append('}')
    return '\n'.join(o) + '\n'

open('src/fig5a_flow_overall.dot', 'w').write(build_overall())
subprocess.run(['dot', '-Tpng', '-Gdpi=200', 'src/fig5a_flow_overall.dot', '-o', 'fig5a_flow_overall.png'], check=True)
subprocess.run(['dot', '-Tsvg', 'src/fig5a_flow_overall.dot', '-o', 'fig5a_flow_overall.svg'], check=True)

# ---------------------------------------------------------------- Figure: Super Admin
save('fig5b_flow_super_admin', role_chart('sa', 'Log in to the\\nCMS', 'Super Admin\\ndashboard', [
  ('Manage\\naccounts?', [('p', 'Create, approve, or\\nsuspend an account'), ('p', 'Save the\\naccount')]),
  ('Edit crop\\ntaxonomy?', [('io', 'Enter floor price\\nand discount'), ('d', 'Values\\nvalid?', 'Show\\nerror'), ('p', 'Save; notify\\nsellers')]),
  ('Moderate\\ncontent?', [('p', 'Review listings,\\nreviews, answers'), ('p', 'Take down, remove,\\nor deactivate')]),
  ('Handle a\\nreport?', [('p', 'Open the\\nreport'), ('d', 'Action\\nwarranted?', 'Dismiss;\\nnotify reporter'), ('p', 'Resolve; take down\\nor remove')]),
  ('Deletion\\nrequest?', [('p', 'Open the\\nrequest'), ('d', 'No open orders\\nand approved?', 'Reject;\\nnotify user'), ('p', 'Anonymize;\\nemail notice')]),
  ('View\\nrecords?', [('p', 'View ledger, chats,\\nand analytics'), ('io', 'Export CSV\\n(logged)')]),
]))

# ---------------------------------------------------------------- Figure: Content Editor
save('fig5c_flow_content_editor', role_chart('ce', 'Log in to the\\nCMS', 'Content Editor\\ndashboard (own farm)', [
  ('Edit farm\\nprofile?', [('io', 'Edit profile, pickup\\npoint, and photos'), ('p', 'Save the\\nprofile')]),
  ('Write an\\narticle?', [('io', 'Write and tag\\nthe article'), ('d', 'Publish\\nnow?', 'Keep as\\ndraft'), ('p', 'Visible to all\\nfarmer-sellers')]),
  ('Post an\\nannouncement?', [('io', 'Set audience,\\nschedule, pin'), ('d', 'Starts\\nnow?', 'Scheduler notifies\\nat start time'), ('p', 'Notify own\\nfarm’s sellers')]),
  ('Edit farm\\nanswers?', [('io', 'Add or override\\na farm answer'), ('p', 'Save for\\nmoderation')]),
  ('Set price\\nguards?', [('io', 'Enter farm floor\\nand discount'), ('d', 'Tighter than\\nsystem values?', 'Reject the\\nchange'), ('p', 'Save; notify\\nlistings')]),
  ('View roster or\\nanalytics?', [('p', 'View roster and\\nfarm analytics')]),
]))

# ---------------------------------------------------------------- Figure: Farmer-seller
save('fig5d_flow_farmer_seller', role_chart('fs', 'Log in to the\\napp', 'Farmer-seller\\nhome', [
  ('Manage\\nlistings?', [('io', 'Enter price, stock,\\nand photos'), ('d', 'At or above\\nthe floor?', 'Show the\\nfloor price'), ('p', 'Publish with\\noptional tawad')]),
  ('Process\\norders?', [('d', 'Accept the\\norder?', 'Cancel with reason;\\nstock released'), ('p', 'Confirm, mark\\nReady, hand over'), ('io', 'Complete with\\namount received')]),
  ('Walk-in\\nsale?', [('io', 'Enter listing,\\nquantity, amount'), ('p', 'Saved at\\nCompleted')]),
  ('View My\\nSales?', [('p', 'View 7- or\\n30-day figures')]),
  ('Read farm\\ninformation?', [('p', 'Read articles and\\nannouncements')]),
  ('Reviews\\nor chat?', [('p', 'View reviews;\\nreport a review'), ('p', 'Chat about\\nan order')]),
]))

# ---------------------------------------------------------------- Figure: Buyer
save('fig5e_flow_buyer', role_chart('by', 'Log in to the\\napp', 'Buyer\\nmarketplace', [
  ('Browse the\\nmarketplace?', [('p', 'Search listings, shops,\\nand farm pages'), ('p', 'Save favorites or\\nreport content')]),
  ('Place an\\norder?', [('p', 'Add listings\\nto the cart'), ('d', 'Email\\nverified?', 'Verify email\\nwith the code'), ('io', 'Check out: pickup\\nor delivery')]),
  ('Track an\\norder?', [('d', 'Confirmed within\\n48 hours?', 'System cancels;\\nstock released'), ('p', 'Hand over, pay\\ncash; Completed'), ('io', 'Leave a\\nreview')]),
  ('Need\\nhelp?', [('p', 'Ask the\\nFAQ helper')]),
  ('Manage\\nmy data?', [('p', 'Correct, download,\\nor request deletion')]),
]))

# ---------------------------------------------------------------- Figure: implementation plan
phases = [
 ('1', 'Server preparation', 'Provision the virtual private server (2 vCPU, 4 GB RAM, 40 GB SSD); install MySQL 8.4;\\nconfigure HTTPS and secure WebSocket; supervise the queue worker and Reverb;\\nregister the scheduler; set Philippine time; configure Gmail SMTP'),
 ('2', 'Deployment and initial data', 'Deploy the Laravel application; run migrations; seed roles, permissions, and FAQ answers;\\nenter the crop taxonomy and system price guards; set up daily database backups'),
 ('3', 'Accounts and release build', 'Create the Super Admin, Content Editor, and farmer-seller accounts;\\nbuild the Android application with the production addresses and install it on participants’ phones'),
 ('4', 'Orientation', 'Orient the Content Editor on the content management panel, the farmer-sellers on listings and\\norders, and the buyers on ordering, in English and Filipino'),
 ('5', 'Parallel operation and evaluation', 'Run AniHow for two weeks alongside the chapter’s existing selling and records;\\ncross-check recorded sales; hold the five-day evaluation window and collect questionnaires'),
 ('6', 'Turnover', 'Hand administration to the LPU-Cavite ICT Department (Super Admin);\\nthe chapter’s Content Editor maintains farm content and price guards'),
]
b = '''digraph G {
  graph [rankdir=TB, nodesep=0.3, ranksep=0.28, pad=0.25, fontname="Liberation Sans"];
  node  [fontname="Liberation Sans", fontsize=10.5, shape=plaintext];
  edge  [penwidth=1.0, arrowsize=0.7, color="#333333"];
'''
BRL = '<BR ALIGN="LEFT"/>'
for n, t, d in phases:
    d = d.replace(chr(92) + 'n', BRL)
    b += (f'  p{n} [label=<<TABLE BORDER="1" CELLBORDER="0" CELLSPACING="0" CELLPADDING="6" BGCOLOR="#FFFFFF">'
          f'<TR><TD BGCOLOR="#E1E6ED" ALIGN="LEFT" WIDTH="520"><B>Phase {n}: {t}</B></TD></TR>'
          f'<TR><TD ALIGN="LEFT" WIDTH="520"><FONT POINT-SIZE="9.5">{d}<BR ALIGN="LEFT"/></FONT></TD></TR></TABLE>>];\n')
for (a, *_), (c, *_) in zip(phases, phases[1:]):
    b += f'  p{a} -> p{c};\n'
b += '}\n'
open('src/fig24_implementation_plan.dot', 'w').write(b)
subprocess.run(['dot', '-Tpng', '-Gdpi=200', 'src/fig24_implementation_plan.dot', '-o', 'fig24_implementation_plan.png'], check=True)
subprocess.run(['dot', '-Tsvg', 'src/fig24_implementation_plan.dot', '-o', 'fig24_implementation_plan.svg'], check=True)
print('flowcharts written')
