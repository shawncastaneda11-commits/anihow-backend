"""Generates the level 1 and level 2 data flow diagrams of AniHow (v3.5).

Every flow below traces to a route in routes/api.php, a Filament resource or
action, or a scheduled command in routes/console.php. Run from
docs/manuscript/figures; render.sh then draws the .dot files.
"""
import os, sys
sys.path.insert(0, os.path.dirname(__file__))
from dfd_common import render, draw

def save(name, title, procs, flows):
    open(f'src/{name}.dot', 'w').write(render(title, procs, flows))
    draw(f'src/{name}.dot', f'{name}.png', f'{name}.svg')

# ------------------------------------------------------------------ Level 1
P1 = [('p1', '1.0', 'Manage\\nAccounts and\\nAccess'), ('p2', '2.0', 'Manage\\nCatalog and\\nPricing'),
      ('p3', '3.0', 'Browse\\nMarketplace'), ('p4', '4.0', 'Process\\nOrders'),
      ('p5', '5.0', 'Coordinate\\nOrders and\\nNotify'), ('p6', '6.0', 'Manage Farm\\nContent and\\nHelp'),
      ('p7', '7.0', 'Report\\nAnalytics and\\nExports'), ('p8', '8.0', 'Handle\\nData Rights'),
      ('p9', '9.0', 'Handle\\nContent\\nReports')]
F1 = [
 ('by','p1','registration, credentials,\\nverification code'), ('fs','p1','credentials, password\\nchanges'),
 ('sa','p1','farmer-seller and editor\\naccounts, approvals,\\nsuspensions'),
 ('p1','by','access token,\\nverification status'), ('p1','fs','access token'), ('p1','ce','farm roster'),
 ('p1','em','verification codes,\\npassword reset links'),
 ('p1','D1','accounts, roles, status'), ('D1','p1','stored accounts'), ('p1','D16','hashed codes, tokens'), ('D16','p1','code check'),
 ('sa','p2','crop types, system price\\nguards, takedowns, restores'), ('ce','p2','farm price guards'),
 ('fs','p2','listings, photos,\\ntawad rules'), ('p2','fs','effective floor,\\nrejections'),
 ('p2','D3','crop types'), ('D3','p2','system guards'), ('p2','D2','farm overrides'), ('D2','p2','farm guards'),
 ('p2','D4','listings, tawad rules'), ('p2','D8','guard and takedown\\nnotices'),
 ('by','p3','searches, favorites'), ('p3','by','listings, storefronts,\\nfarm pages, reviews'),
 ('fs','p3','shop profile'), ('p3','fs','farm page,\\nown shop reviews'),
 ('D4','p3','active listings'), ('D2','p3','farm profiles'), ('D9','p3','shop reviews'), ('D12','p3','public announcements'),
 ('p3','D10','favorites'), ('D10','p3','saved favorites'), ('p3','D1','shop profile'),
 ('by','p4','cart items, checkout,\\ncancellations, reviews'), ('fs','p4','status updates,\\nwalk-in sales'),
 ('p4','by','order status, receipts'), ('p4','fs','orders'), ('sch','p4','hourly sweep'),
 ('sa','p4','review removals'), ('p4','sa','order ledger'),
 ('p4','D5','cart items'), ('D5','p4','cart'), ('p4','D6','orders, status history'), ('D6','p4','order records'),
 ('p4','D4','stock held, deducted,\\nreleased'), ('D4','p4','prices, tawad, stock'), ('p4','D9','reviews'),
 ('p4','D8','order notices'), ('p4','em','low-stock alerts'),
 ('by','p5','chat messages,\\nread marks'), ('fs','p5','chat messages,\\nread marks'), ('p5','by','messages,\\nnotifications'), ('p5','fs','messages,\\nnotifications'),
 ('p5','sa','chat threads\\n(read only)'), ('p5','D7','messages'), ('D7','p5','threads'), ('D8','p5','notifications'), ('p5','D8','read status'), ('D6','p5','order parties'),
 ('ce','p6','profile, photos, articles,\\nannouncements, farm answers'), ('sa','p6','system answers,\\nmoderation'),
 ('fs','p6','questions'), ('by','p6','questions'), ('p6','fs','articles, announcements,\\nanswers'), ('p6','by','answers'),
 ('p6','ce','moderation notices'), ('sch','p6','five-minute check'),
 ('p6','D2','profile, photos'), ('p6','D11','articles'), ('D11','p6','published articles'), ('p6','D12','announcements'), ('D12','p6','due announcements'),
 ('p6','D13','answers'), ('D13','p6','matching answers'), ('p6','D8','announcement notices'),
 ('D6','p7','completed orders'), ('p7','sa','system analytics,\\nCSV exports, export log'), ('p7','ce','farm analytics'), ('p7','fs','My Sales'),
 ('sa','p7','export requests'), ('p7','D14','export entries'), ('D14','p7','export history'),
 ('by','p8','corrections, download\\nand deletion requests'), ('fs','p8','corrections, download\\nand deletion requests'),
 ('sa','p8','deletion decisions'), ('p8','sa','pending requests'), ('p8','by','own data export'), ('p8','fs','own data export'),
 ('p8','D15','requests, decisions'), ('D15','p8','request status'), ('p8','D1','corrections,\\nanonymization'), ('D1','p8','profile'),
 ('D6','p8','orders, open orders check'), ('D17','p8','own reports'), ('p8','D8','request and\\nrejection notices'), ('p8','em','deletion notices'),
 ('by','p9','listing and\\nreview reports'), ('fs','p9','reports on reviews\\nof own shop'),
 ('sa','p9','resolve or dismiss,\\ntakedown or removal'), ('p9','sa','open reports'),
 ('p9','by','report outcome'), ('p9','fs','report outcome,\\ntakedown notice'),
 ('D4','p9','published listings'), ('D9','p9','visible reviews'), ('p9','D17','reports, decisions'), ('D17','p9','open reports'),
 ('p9','D4','takedowns'), ('p9','D9','review removals'), ('p9','D8','report notices'),
]
def part(keys):
    ks = set(keys)
    return [p for p in P1 if p[0] in ks], [f for f in F1 if f[0] in ks or f[1] in ks]
save('fig6_1a_dfd_level1', 'Data flow diagram, level 1 (processes 1.0 to 4.0).', *part(['p1','p2','p3','p4']))
save('fig6_1b_dfd_level1', 'Data flow diagram, level 1 (processes 5.0 to 9.0).', *part(['p5','p6','p7','p8','p9']))

# ------------------------------------------------------------------ Level 2
L2 = {}
L2['1'] = ('Manage Accounts and Access', [
  ('a','1.1','Register\\nBuyer'), ('b','1.2','Verify\\nEmail'), ('c','1.3','Log In\\nand Out'),
  ('d','1.4','Reset and\\nChange\\nPassword'), ('e','1.5','Administer\\nAccounts')], [
  ('by','a','name, email,\\npassword'), ('a','D1','buyer account'), ('a','by','access token'), ('a','b','new account'),
  ('by','b','entered code,\\nresend request'), ('b','D16','hashed code,\\n10-minute expiry'), ('D16','b','stored code'),
  ('b','em','six-digit code'), ('b','D1','verified status'), ('b','by','verification status'),
  ('by','c','credentials'), ('fs','c','credentials'), ('D1','c','account, role,\\nstatus'), ('c','by','token or refusal'), ('c','fs','token or refusal'),
  ('by','d','reset request,\\nnew password'), ('fs','d','reset request,\\nnew password'), ('d','D16','reset token'), ('D16','d','token check'),
  ('d','em','reset link'), ('d','D1','password hash'),
  ('sa','e','farmer-seller and editor\\naccounts, approvals,\\nsuspensions'), ('e','D1','accounts, roles,\\nfarm, status'), ('D1','e','members'),
  ('e','ce','own farm roster'), ('e','sa','user list')])
L2['2'] = ('Manage Catalog and Pricing', [
  ('a','2.1','Maintain\\nCrop\\nTaxonomy'), ('b','2.2','Set Farm\\nPrice\\nGuards'), ('c','2.3','Manage\\nListings'),
  ('d','2.4','Manage\\nTawad\\nRules'), ('e','2.5','Moderate\\nListings')], [
  ('sa','a','crop types, system floor\\nand maximum discount'), ('a','D3','crop types, guards'), ('a','b','system guards'),
  ('ce','b','own farm floor\\nand ceiling'), ('sa','b','any farm floor\\nand ceiling'), ('D3','b','system guards'), ('b','D2','tighten-only overrides'),
  ('D4','b','affected listings'), ('b','D8','stranded listing\\nand tawad notices'),
  ('fs','c','listing details, photos,\\npause or resume'), ('D3','c','system floor'), ('D2','c','farm floor'), ('c','D4','listings, photos,\\nthumbnails'),
  ('c','fs','effective floor,\\nrejections'),
  ('fs','d','flat or quantity\\ntawad rules'), ('D3','d','maximum discount'), ('D2','d','farm ceiling'), ('d','D4','tawad rules'), ('d','fs','rejections'),
  ('sa','e','takedown, restore'), ('e','D4','listing status'), ('e','D8','takedown and\\nrestore notices')])
L2['3'] = ('Browse Marketplace', [
  ('a','3.1','Search\\nListings'), ('b','3.2','View\\nStorefronts'), ('c','3.3','View Farm\\nPages'), ('d','3.4','Manage\\nFavorites')], [
  ('by','a','keywords, crop type,\\nsort'), ('D4','a','active listings'), ('D3','a','crop types'), ('D2','a','farm names'), ('a','by','matching listings'),
  ('by','b','shop selection'), ('D1','b','shop profiles'), ('D4','b','shop listings'), ('D9','b','shop reviews'), ('b','by','storefront, reviews'),
  ('fs','b','shop profile\\nupdates'), ('b','D1','shop profile'), ('b','fs','own shop reviews,\\nrating'),
  ('by','c','farm selection'), ('fs','c','farm selection'), ('D2','c','profile, photos,\\ncontact number'), ('D12','c','public\\nannouncements'),
  ('c','by','farm page without\\ncontact number'), ('c','fs','farm page, contact\\nnumber if own farm'),
  ('by','d','listing and shop\\nfavorites'), ('d','D10','favorites'), ('D10','d','saved favorites'), ('d','by','favorites list')])
L2['4'] = ('Process Orders', [
  ('a','4.1','Manage\\nCart'), ('b','4.2','Check Out'), ('c','4.3','Update\\nOrder\\nStatus'), ('d','4.4','Record\\nWalk-in\\nSale'),
  ('e','4.5','Sweep\\nStale\\nOrders'), ('f','4.6','Review\\nOrders'), ('g','4.7','Provide\\nOrder\\nRecords')], [
  ('by','a','cart items\\n(verified buyers)'), ('D4','a','price, stock'), ('a','D5','cart items'),
  ('by','b','checkout, fulfillment\\npreference'), ('D5','b','cart'), ('D4','b','prices, tawad, stock'), ('D2','b','farm guards'),
  ('b','D6','one Placed order\\nper seller'), ('b','D4','held stock'), ('b','D8','new order notice'), ('b','by','order summary'),
  ('fs','c','confirm, ready, complete\\nwith amount, cancel'), ('by','c','cancel while\\nPlaced'), ('D6','c','order'),
  ('c','D6','status, history,\\nfixed prices'), ('c','D4','deduct, release,\\nrestore stock'), ('c','D8','status and\\nlow-stock notices'), ('c','em','low-stock alert'),
  ('fs','d','walk-in sale,\\namount received'), ('D4','d','price, tawad, stock'), ('d','D6','Completed order,\\nno buyer'), ('d','D4','deducted stock'), ('d','D8','low-stock notice'), ('d','em','low-stock alert'),
  ('sch','e','hourly trigger'), ('D6','e','Placed orders'), ('e','D8','12-hour reminder,\\ncancellation notice'),
  ('e','D6','48-hour system\\ncancellation'), ('e','D4','released stock'),
  ('by','f','rating, comment'), ('D6','f','completed app order'), ('f','D9','review'), ('sa','f','review removal'),
  ('D6','g','orders'), ('g','by','history, receipts'), ('g','fs','order list'), ('g','sa','order ledger')])
L2['5'] = ('Coordinate Orders and Notify', [
  ('a','5.1','Exchange\\nOrder\\nChat'), ('b','5.2','Deliver\\nNotifications')], [
  ('by','a','message'), ('fs','a','message'), ('D6','a','order parties,\\nstatus'), ('a','D7','message'), ('D7','a','thread'),
  ('a','by','live messages'), ('a','fs','live messages'), ('a','sa','thread (read only)'), ('a','b','new message'),
  ('D8','b','notifications'), ('by','b','read marks'), ('fs','b','read marks'), ('b','D8','read status'),
  ('b','by','notifications,\\nunread count'), ('b','fs','notifications,\\nunread count')])
L2['6'] = ('Manage Farm Content and Help', [
  ('a','6.1','Maintain\\nFarm\\nProfile'), ('b','6.2','Publish\\nCrop-Care\\nArticles'), ('c','6.3','Post Farm\\nAnnounce-\\nments'),
  ('d','6.4','Manage\\nFAQ\\nAnswers'), ('e','6.5','Answer\\nQuestions')], [
  ('ce','a','profile, pickup point,\\ncover and photos'), ('sa','a','any farm profile'), ('a','D2','profile, photos'),
  ('ce','b','articles, crop tags'), ('sa','b','unpublish'), ('b','D11','articles, status'), ('D11','b','published articles'),
  ('b','fs','articles from\\nall farms'),
  ('ce','c','announcement, audience,\\nschedule, pin'), ('c','D12','announcements'), ('sch','c','five-minute check'), ('D12','c','due announcements'),
  ('c','D8','notices to own\\nfarm sellers'), ('c','fs','announcements'),
  ('sa','d','system answers,\\ndeactivate, remove'), ('ce','d','farm answers\\nand overrides'), ('d','D13','answers'), ('d','ce','moderation notice'),
  ('by','e','question'), ('fs','e','question'), ('D13','e','system answers,\\nfarm overrides'), ('e','by','answer or\\nsuggestions'), ('e','fs','answer or\\nsuggestions')])
L2['7'] = ('Report Analytics and Exports', [
  ('a','7.1','Summarize\\nSales'), ('b','7.2','Export\\nCSV Files')], [
  ('D6','a','completed orders,\\nwalk-ins included'), ('a','sa','system-wide\\ndashboard'), ('a','ce','own farm\\ndashboard'),
  ('a','fs','My Sales,\\n7 or 30 days'), ('a','b','summaries'),
  ('sa','b','ledger or analytics\\nexport, filters'), ('D6','b','order ledger'), ('b','sa','CSV file\\n(no contact data)'),
  ('b','D14','export entry'), ('D14','b','export history')])
L2['8'] = ('Handle Data Rights', [
  ('a','8.1','Correct\\nProfile'), ('b','8.2','Download\\nOwn Data'), ('c','8.3','Request\\nDeletion'), ('d','8.4','Decide\\nDeletion')], [
  ('by','a','name, phone,\\naddress'), ('fs','a','name, phone,\\naddress'), ('a','D1','corrected profile'),
  ('by','b','download request'), ('fs','b','download request'), ('D1','b','profile'), ('D6','b','orders'), ('D9','b','reviews'), ('D17','b','own reports'),
  ('D10','b','favorites'), ('D5','b','cart'), ('b','by','JSON file'), ('b','fs','JSON file'),
  ('by','c','request, cancel'), ('fs','c','request, cancel'), ('D6','c','open orders'), ('c','D15','pending request'),
  ('c','D8','notice to\\nSuper Admins'), ('c','by','request status'), ('c','fs','request status'),
  ('D15','d','pending requests'), ('sa','d','approve or reject'), ('d','sa','request details'), ('D6','d','open orders\\nrecheck'),
  ('d','D1','anonymized account'), ('d','D15','decision'), ('d','em','deletion notice'), ('d','D8','rejection notice')])
L2['9'] = ('Handle Content Reports', [
  ('a','9.1','Submit\\nReport'), ('b','9.2','Decide\\nReport')], [
  ('by','a','listing or review,\\nreason, details'), ('fs','a','review on own shop,\\nreason, details'),
  ('D4','a','published listing'), ('D9','a','visible review'), ('D17','a','open report check'),
  ('a','D17','open report'), ('a','D8','notice to\\nSuper Admins'), ('a','by','confirmation\\nor refusal'), ('a','fs','confirmation\\nor refusal'),
  ('D17','b','open reports'), ('sa','b','resolve with note or dismiss;\\noptional takedown or removal'), ('b','sa','report and target'),
  ('b','D17','status, resolver'), ('b','D4','takedown'), ('b','D9','review removal'),
  ('b','D8','takedown notice,\\nreporter notice')])
for k, (name, procs, flows) in L2.items():
    procs = [(f'q{k}{p}', n, lab) for p, n, lab in procs]
    keys = {p[0][len(k)+1:] for p in procs}
    fl = [((f'q{k}{a}' if a in keys else a), (f'q{k}{b}' if b in keys else b), l) for a, b, l in flows]
    save(f'fig6_2_{k}_dfd_level2', f'Data flow diagram, level 2, process {k}.0 {name}.', procs, fl)
print('dfd written')
