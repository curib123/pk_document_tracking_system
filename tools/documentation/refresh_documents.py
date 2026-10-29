"""Refresh the eight canonical documents; verify with --verify. Requires pymupdf/python-pptx.

Existing diagram layouts and guide illustrations are retained. Added pages are native
Visio shapes / PowerPoint text, with matching vector PDF pages. No Office automation.
"""
from pathlib import Path
import io
import re
import sys
import zipfile
import xml.etree.ElementTree as ET
from copy import deepcopy

import pymupdf as pdf
from pptx import Presentation
from pptx.util import Pt

ROOT = Path(__file__).resolve().parents[2]
DOCS = ROOT / 'docs/system-documentation'
NS = 'http://schemas.microsoft.com/office/visio/2012/main'
REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'
PKG = 'http://schemas.openxmlformats.org/package/2006/relationships'
ET.register_namespace('', NS)
ET.register_namespace('r', REL)
MARKER = 'CURRENT SYSTEM COVERAGE / 29 OCTOBER 2026'

# Explicit route inventory: a new route must be reviewed and mapped, never silently accepted.
GROUPS = [
 ('Account and dashboard', 'Guide 1-3; Flowchart 1', ['login', 'logout', 'change-password', 'dashboard']),
 ('Notifications', 'Guide 14 and supplement; Flowchart supplement', ['notifications/tasks', 'notifications/inbox', 'notifications/tasks/claim', 'notifications/inbox/read']),
 ('Documents and files', 'Guide 4, 7, 15; Flowchart 5', ['documents/(:any)', 'documents/softcopy/direct', 'documents/(:any)/save', 'documents/(:any)/dispose', 'files/upload', 'files/download/(:num)', 'files/print/(:num)', 'files/print-content/(:num)', 'files/review/(:num)', 'files/review-content/(:num)/(:any)/(:any)', 'files/office-setup']),
 ('Places and categories', 'Guide 17; Process Flow 5', ['places', 'places/(:any)', 'places/(:any)/save', 'places/(:any)/deactivate']),
 ('Request lifecycle', 'Guide 5-10, 12; Flowchart 2-3', ['my-requests/(:any)', 'my-requests/(:any)/save', 'my-requests/(:any)/submit', 'my-requests/(:any)/cancel', 'my-requests/(:any)/delete']),
 ('Decisions and handoffs', 'Guide 11, 13; Flowchart 3-4', ['my-tasks/(:any)', 'my-tasks/(:any)/decide', 'my-tasks/hardcopy-transfer/dispatch', 'my-tasks/hardcopy-transfer/accept']),
 ('Direct assignments', 'Guide 16, 18; Process Flow 4', ['admin/document-assignments', 'admin/document-assignments/save', 'admin/document-assignments/location', 'admin/document-assignments/category', 'admin/users/(:num)/assigned-documents']),
 ('Audit and recovery', 'Guide 21-22; Flowchart 6', ['admin/audit', 'admin/backups', 'admin/backups/create', 'admin/backups/cleanup', 'admin/backups/import', 'admin/backups/restore', 'admin/backups/download/(:any)']),
 ('Users and roles', 'Guide 18-19; Flowchart 6', ['admin/users', 'admin/users/save', 'admin/users/deactivate', 'admin/roles', 'admin/roles/save', 'admin/roles/deactivate', 'admin/roles/remove']),
 ('Workflow versions', 'Guide 20; Flowchart 6', ['admin/workflows', 'admin/workflows/step/save', 'admin/workflows/step/remove', 'admin/workflows/step/move', 'admin/workflows/publish', 'admin/workflows/clone']),
]
REQUESTS = {
 'softcopy_create': 'Create controlled softcopy and its approved file.',
 'softcopy_revise': 'Publish replacement revision; retain history; retire superseded file bytes after commit.',
 'softcopy_cancel': 'Mark controlled document Cancelled and retire its files; separate from cancelling a draft.',
 'hardcopy_create': 'Register physical document, holder, place hierarchy and optional retention dates.',
 'hardcopy_update': 'Apply approved metadata and retention changes to the active hardcopy.',
 'access': 'Grant personal access to the requester until expiry; role permissions still govern file actions.',
 'assignment': 'Assign to requester: add/reactivate softcopy assignment or replace hardcopy holder.',
 'transfer': 'Approve handoff, then requester Dispatch and recipient Accept; acceptance updates custody/location.',
 'disposal': 'Dispose hardcopy, preserve history, clear Location and revoke personal grants.',
}
NOTES = [
 ('23. Display, installation and connection', [
  'Use the theme button to select light or dark mode. The preference is saved in this browser; without one, the system follows the device theme.',
  'Use Toggle navigation on a narrow screen. Table and Folder views, filters, breadcrumbs and paging operate within your document permissions.',
  'Use Install app when the browser offers it. Confirm the browser prompt. After installation the install control is hidden; eligibility depends on browser support and a secure context.',
  'Open the installed app to use the same server and account. Installation does not provide offline document editing or approval. Reconnect before performing server actions.',
  'Opening the notification panel does not mark everything read. Selecting an entry acknowledges that entry; dismissing a reminder does not decide a task.',
 ]),
 ('24. Current behavior and recovery prerequisites', [
  'These documents describe the checked-out application source on 29 October 2026. Screenshots are illustrative examples from the retained guide; names, counts and visual arrangement can differ.',
  'Direct hardcopy disposal preserves its Location and grant rows. Approval-based hardcopy disposal clears Location and revokes grants. Both record disposal history; disposed status still restricts actions.',
  'Hardcopy assignment immediately changes Current Holder without Dispatch/Accept. Single and bulk direct assignments block records with active transfers. Bulk assignment is limited to 2,000 active records.',
  'Only the current approved controlled file is available for normal delivery. Revision metadata remains, but retired/rejected file bytes may be cleaned up. Editing a review copy does not replace the stored proposal.',
  'Automatic backups require an administrator to install tools/install_backup_schedule.ps1 on a UTC+08:00 host. It schedules 5:00 PM local time under SYSTEM; this document does not verify an installed task or successful run.',
  'Configure backup encryption/signing and database tools for the service account. Check readiness and a resulting archive. Restore requires your password and RESTORE, creates recovery backup, restores database/private data and signs out; code recovery is offline.',
 ]),
]

# Row tuples: source -> numbered process -> destination, with named data on both arrows.
DFD = [
 ('Level 1 / identity, catalog and controlled files', [
 ('User', 'Credentials / password', '1. Authenticate / change password', 'Session / account outcome', 'User'),
 ('D1 users, roles, role_permissions, permissions; login_attempts', 'Account / permission data', '1. Authenticate / authorize', 'Session data', 'Private session storage'),
 ('D2 document registers, assignments, access_grants, places, categories', 'Scoped records', '2. Browse / inspect documents', 'Catalog / details', 'Authorized user'),
 ('Requester / direct operator', 'Attachment bytes', '3. Validate / store file', 'File metadata and bytes', 'D3 files, revisions, artifacts; private documents'),
 ('D3 files and private documents; D1/D2 scope', 'Current file / access data', '3. Review / download / print', 'Authorized proposal or current file', 'Authorized reviewer / user'),
 ('3. Apply revision / cancellation / disposal', 'Retired file identity', '3. Clean retired bytes after commit', 'Metadata retained; bytes removed', 'D3 revision history / private documents'),
 ]),
 ('Level 1 / requests, approvals and custody', [
 ('Requester', 'Draft proposal / submit', '4. Validate / save / route request', 'Payload / captured route', 'D4 requests, workflow_steps; D3 pending files'),
 ('D5 workflows, workflow_versions; D1 users/roles', 'Published route / active approvers', '4. Resolve approval route', 'Assigned step / task', 'D4 workflow_steps; notifications'),
 ('Active assigned approver', 'Approve / Return / Reject', '5. Decide and preserve history', 'Decision / active stage', 'D4 requests, workflow_history, workflow_steps'),
 ('5. Final approval', 'Approved payload', '6. Apply document operation', 'Record / grant / disposal changes', 'D2 registers, assignments, access_grants, disposals, status_history; D3'),
 ('5. Approved transfer', 'Approved destination / parties', '7. Prepare physical handoff', 'For Transfer record', 'D6 transfers; D4 approved request'),
 ('Requester Dispatch / recipient Accept', 'Release / receipt confirmation', '7. Validate handoff and custody', 'Transfer state; accepted custody', 'D6 transfers; D2 holder/location/grants; D4 history'),
 ]),
 ('Level 1 / administration, notifications and recovery', [
 ('Authorized maintainer', 'Accounts / roles / hierarchy / route draft', '8. Validate configuration / publish', 'Saved definitions / version', 'D1 identity; D2 places/categories; D5 workflows'),
 ('Authorized controller', 'Document / location / category and user', '6. Apply direct assignment', 'Holder / assignment changes', 'D2 hardcopy_documents / assignments'),
 ('D4 active steps; notifications / receipts', 'Assigned tasks / unread entries', '9. Present / acknowledge alerts', 'Task links / unread count', 'User; task_notification_receipts'),
 ('Application operations', 'Actor / action / outcome', '10. Record audit event', 'Audit entry / daily log', 'Private audit and log files'),
 ('Backup operator / installed scheduled task', 'Create / import / download / cleanup', '11. Verify and manage archives', 'Signed encrypted archive / result', 'Private backup archives; operator'),
 ('Verified archive + authorized restore user', 'Archive / password / RESTORE', '11. Snapshot / restore / recover', 'Database/private data; sign-out', 'Database; private data; recovery archive'),
 ]),
]

def current(text):
    for before, after in [
        ('PROPOSED', 'CURRENT'), ('Proposed authentication', 'Current authentication'),
        ('Proposed request', 'Current request'), ('Proposed permission', 'Current permission'),
        ('Proposed approved', 'Current approved'), ('PROPOSAL  ·  REVISION 02', 'CURRENT  ·  REVISION 03'),
        ('26 OCTOBER 2026', '29 OCTOBER 2026'),
        ('Planned user procedures', 'Current user procedures'), ('expected outcomes', 'operation outcomes'),
        ('The proposed', 'The current'), ('The planned', 'The current'),
        ('The planned daily schedule', 'The configured schedule target'),
        ('planned daily schedule', 'schedule target after installation'),
        ('proposed sign-in screen', 'current sign-in screen'),
        ('proposed navigation', 'current navigation'), ('planned screens', 'current screens'),
    ]:
        text = text.replace(before, after)
    return text

def check_source():
    routes = set(re.findall(r"\$route\['([^']+)'\]", (ROOT/'application/config/routes.php').read_text()))
    routes -= {'default_controller', '404_override', 'translate_uri_dashes'}
    mapped = {r for _, _, values in GROUPS for r in values}
    assert routes == mapped, f'Route coverage drift: missing={routes-mapped}, stale={mapped-routes}'
    policy = (ROOT/'application/services/request/request_policy.php').read_text()
    types = set(re.findall(r"'([^']+)'\s*=>", policy))
    assert types == set(REQUESTS), f'Request coverage drift: {types ^ set(REQUESTS)}'
    print(f'Source coverage: {len(routes)} route patterns; {len(types)} request types')

def box(page, rect, text, size=13, fill=None, align=0):
    rect = pdf.Rect(rect)
    if fill:
        page.draw_rect(rect, color=(.35,.45,.55), fill=fill, width=.7)
    inner = rect + (9, 7, -9, -7) if fill else rect
    result = -1
    while result < 0 and size >= 9:
        result = page.insert_textbox(inner, text, fontsize=size, fontname='helv', color=(.12,.18,.25), align=align)
        size -= .5
    assert result >= 0, f'Text overflow: {text[:90]}'

def page_header(doc, title, subtitle):
    page = doc.new_page(width=1190, height=842)
    box(page, (40,25,1150,55), 'PK DOCUMENT TRACKING SYSTEM', 19)
    box(page, (40,67,1150,108), title, 22)
    box(page, (40,112,1150,140), subtitle, 11)
    box(page, (40,805,1150,830), MARKER, 10)
    return page

def text_page(doc, title, lines):
    page = page_header(doc, title, 'Current source behavior; permissions and record scope apply to each operation.')
    y=160
    for line in lines:
        height = 72 if len(lines) <= 7 else 53
        box(page, (45,y,1140,y+height), line, 15)
        y += height+12
    return page

def flow_page(doc, title, rows):
    page = page_header(doc, title, 'Arrows name data passed between external actors, numbered processes and data stores (D1-D6).')
    for i,(left,a,mid,b,right) in enumerate(rows):
        y = 160+i*103
        box(page,(40,y,270,y+77),left,12,(.92,.96,.99),1)
        box(page,(450,y,730,y+77),mid,13,(.91,.96,.92),1)
        box(page,(910,y,1150,y+77),right,12,(.96,.94,.89),1)
        for start,end,label in [(270,450,a),(730,910,b)]:
            cy=y+49
            page.draw_line((start,cy),(end,cy),color=(.25,.35,.45))
            page.draw_polyline([(end-8,cy-4),(end,cy),(end-8,cy+4)],color=(.25,.35,.45))
            box(page,(start+5,y+2,end-5,y+43),label,10,align=1)
    return page

def pagination(text, total):
    text = re.sub(r'Page (\d+) of \d+', lambda m: f'Page {m[1]} of {total}', text)
    return re.sub(r'\b(\d+) / \d+\b', lambda m: f'{m[1]} / {total}', text)

def clean_pdf(path, total):
    doc=pdf.open(path)
    # Remove earlier generated supplements so a refresh is repeatable.
    while len(doc) and MARKER in doc[-1].get_text():
        doc.delete_page(len(doc)-1)
    for page in doc:
        replacements=[]
        for block in page.get_text('dict')['blocks']:
            for line in block.get('lines',[]):
                for span in line['spans']:
                    new=pagination(current(span['text']), total)
                    if new != span['text']:
                        replacements.append((span,new))
                        page.add_redact_annot(pdf.Rect(span['bbox']), fill=False)
        if replacements:
            page.apply_redactions(images=0,graphics=0)
            for span,new in replacements:
                font='hebo' if 'bold' in span['font'].lower() else 'helv'
                size=span['size']
                limit=page.rect.width-span['origin'][0]-15
                while pdf.get_text_length(new,fontname=font,fontsize=size)>limit: size-=.25
                color=tuple(((span['color']>>shift)&255)/255 for shift in (16,8,0))
                page.insert_text(span['origin'],new,fontname=font,fontsize=size,color=color)
    return doc

def save_pdf(doc,path):
    data=doc.tobytes(garbage=4,deflate=True)
    doc.close()
    path.write_bytes(data)

def native_page(page):
    """Convert the supplement's vector boxes/text/arrows to editable Visio objects."""
    def tag(name): return '{'+NS+'}'+name
    root=ET.Element(tag('PageContents'))
    shapes=ET.SubElement(root,tag('Shapes'))
    count=0
    def shape(x,y,w,h):
        nonlocal count
        count+=1
        s=ET.SubElement(shapes,tag('Shape'),ID=str(count),NameU=f'Shape.{count}',Type='Shape')
        for n,v in [('PinX',(x+w/2)/72),('PinY',(842-y-h/2)/72),('Width',w/72),('Height',h/72),('LocPinX',w/144),('LocPinY',h/144),('FillPattern',0),('LinePattern',0)]:
            ET.SubElement(s,tag('Cell'),N=n,V=str(v))
        return s
    for drawing in page.get_drawings():
        r=drawing['rect']; s=shape(r.x0,r.y0,max(r.width,.01),max(r.height,.01))
        ET.SubElement(s,tag('Cell'),N='LinePattern',V='1')
        ET.SubElement(s,tag('Cell'),N='LineColor',V='#64748B')
        if drawing['fill']:
            s.find(tag('Cell')+"[@N='FillPattern']").set('V','1')
            ET.SubElement(s,tag('Cell'),N='FillForegnd',V='#'+''.join(f'{round(v*255):02X}' for v in drawing['fill']))
        section=ET.SubElement(s,tag('Section'),N='Geometry',IX='0')
        points=[]
        for item in drawing['items']:
            if item[0]=='re':
                q=item[1]; points=[q.tl,q.tr,q.br,q.bl,q.tl]
            elif item[0]=='l':
                if not points: points.append(item[1])
                points.append(item[2])
        for ix,p in enumerate(points):
            row=ET.SubElement(section,tag('Row'),T='MoveTo' if ix==0 else 'LineTo',IX=str(ix+1))
            ET.SubElement(row,tag('Cell'),N='X',V=str((p.x-r.x0)/72))
            ET.SubElement(row,tag('Cell'),N='Y',V=str((r.y1-p.y)/72))
    for block in page.get_text('dict')['blocks']:
        for line in block.get('lines',[]):
            for span in line['spans']:
                r=pdf.Rect(span['bbox']); s=shape(r.x0,r.y0-1,r.width+6,r.height+5)
                for n in ['LeftMargin','RightMargin','TopMargin','BottomMargin']:
                    ET.SubElement(s,tag('Cell'),N=n,V='0')
                sec=ET.SubElement(s,tag('Section'),N='Character'); row=ET.SubElement(sec,tag('Row'),IX='0')
                ET.SubElement(row,tag('Cell'),N='Font',V='Arial')
                ET.SubElement(row,tag('Cell'),N='Size',V=str(span['size']/72))
                ET.SubElement(s,tag('Text')).text=span['text']
    return ET.tostring(root,encoding='utf-8',xml_declaration=True)

def update_visio(path, supplements):
    with zipfile.ZipFile(path) as z: parts={n:z.read(n) for n in z.namelist()}
    pages=ET.fromstring(parts['visio/pages/pages.xml'])
    rels=ET.fromstring(parts['visio/pages/_rels/pages.xml.rels'])
    content=ET.fromstring(parts['[Content_Types].xml'])
    # Generated page names provide a stable boundary for subsequent refreshes.
    for p in list(pages):
        if p.get('Name','').startswith('Current coverage /'):
            rid=p.find('{'+NS+'}Rel').get('{'+REL+'}id')
            rel=next(r for r in rels if r.get('Id')==rid)
            name='visio/pages/'+rel.get('Target')
            parts.pop(name,None); rels.remove(rel); pages.remove(p)
            for item in list(content):
                if item.get('PartName')=='/'+name: content.remove(item)
    for name,data in list(parts.items()):
        if name.endswith('.xml'):
            parts[name]=pagination(current(data.decode('utf-8')),len(pages)+len(supplements)).encode('utf-8')
    for page in supplements:
        index=len(pages)+1; name=f'page{index}.xml'; rid=f'rId{index}'
        p=deepcopy(pages[0]); p.set('ID',str(index-1)); p.set('NameU',f'Page-{index}')
        p.set('Name',f'Current coverage / {index}'); p.find('{'+NS+'}Rel').set('{'+REL+'}id',rid)
        for cell in p.iter('{'+NS+'}Cell'):
            if cell.get('N')=='PageWidth': cell.set('V',str(1190/72))
            if cell.get('N')=='PageHeight': cell.set('V',str(842/72))
        pages.append(p)
        ET.SubElement(rels,'{'+PKG+'}Relationship',Id=rid,Type=REL+'/page',Target=name)
        ET.SubElement(content,'{http://schemas.openxmlformats.org/package/2006/content-types}Override',PartName='/visio/pages/'+name,ContentType='application/vnd.ms-visio.page+xml')
        parts['visio/pages/'+name]=native_page(page)
    parts['visio/pages/pages.xml']=ET.tostring(pages,encoding='utf-8',xml_declaration=True)
    parts['visio/pages/_rels/pages.xml.rels']=ET.tostring(rels,encoding='utf-8',xml_declaration=True)
    parts['[Content_Types].xml']=ET.tostring(content,encoding='utf-8',xml_declaration=True)
    result=io.BytesIO()
    with zipfile.ZipFile(result,'w',zipfile.ZIP_DEFLATED) as z:
        for name,data in parts.items(): z.writestr(name,data)
    path.write_bytes(result.getvalue())

def update_guide(supplements):
    path=DOCS/'PK_DTS_User_Guide.pptx'; prs=Presentation(path)
    while prs.slides and any(MARKER in s.text for s in prs.slides[-1].shapes if s.has_text_frame):
        slide_id=prs.slides._sldIdLst[-1]
        prs.part.drop_rel(slide_id.rId); prs.slides._sldIdLst.remove(slide_id)
    for slide in prs.slides:
        for shape in slide.shapes:
            if shape.has_text_frame:
                for paragraph in shape.text_frame.paragraphs:
                    for run in paragraph.runs: run.text=current(run.text)
    # Supplement text is editable, placed identically to its PDF counterpart.
    sx=prs.slide_width/1190; sy=prs.slide_height/842
    for page in supplements:
        slide=prs.slides.add_slide(prs.slide_layouts[6])
        for block in page.get_text('dict')['blocks']:
            for line in block.get('lines',[]):
                for span in line['spans']:
                    r=pdf.Rect(span['bbox'])
                    box_=slide.shapes.add_textbox(int(r.x0*sx),int((r.y0-2)*sy),int((r.width+15)*sx),int((r.height+9)*sy))
                    tf=box_.text_frame; tf.margin_left=tf.margin_right=tf.margin_top=tf.margin_bottom=0
                    run=tf.paragraphs[0].add_run(); run.text=span['text']; run.font.name='Arial'
                    run.font.size=Pt(span['size']*prs.slide_height/914400/ (842/72))
    prs.save(path)

def refresh():
    for base,kind in [('PK_DTS_DFD','dfd'),('PK_DTS_Flowchart','flow'),('PK_DTS_Process_Flow','process'),('PK_DTS_User_Guide','guide')]:
        total={'dfd':5,'flow':7,'process':8,'guide':64}[kind]
        path=DOCS/(base+'.pdf'); doc=clean_pdf(path,total); first=len(doc)
        if kind=='dfd':
            for title,rows in DFD: flow_page(doc,title,rows)
        elif kind=='flow':
            flow_page(doc,'Supporting operations / scoped decisions',[
             ('User opens notification / reminder','Selected entry','Check session and task ownership','Acknowledge entry only','Inbox receipt; open My Tasks to decide'),
             ('Authorized controller','Document or location/category + user','Check scope, active recipient and transfer conflicts','Valid selection <= 2,000','Apply assignment; otherwise show error'),
             ('Maintainer edits configuration','Draft steps / active approvers','Validate ordered route (1-30 steps)','Publish valid version','Future submissions use version; existing routes retained'),
             ('User selects Install app','Browser eligibility / secure context','Offer browser install prompt','Accepted installation','Remember installed state; use server for actions'),
             ('User changes theme','Light / dark selection','Save browser preference','Next render','Apply preference or device theme'),
             ('Installed backup scheduler','5 PM UTC+08:00 host trigger','Check readiness / acquire operation lock','Successful backup','Archive and run result; failed readiness reports error'),
            ])
        elif kind=='process':
            for offset in (0,5):
                text_page(doc,'Request outcomes / '+('1-5' if offset==0 else '6-9'),[key+' - '+value for key,value in list(REQUESTS.items())[offset:offset+5]])
            text_page(doc,'Supporting work and operating boundaries',[line for _,lines in NOTES for line in lines][:5]+[
                'Accounts, roles, places/categories, workflow drafts/publication, audit inspection and backup recovery follow Process Flow sheet 5 and User Guide sections 17-22.',
                'A scheduler is installed separately for automatic backups. Review readiness and a resulting archive; a documented schedule does not prove a completed backup.',
            ])
        else:
            for title,lines in NOTES: text_page(doc,title,lines)
            for offset in (0,5):
                text_page(doc,'Nine supported request actions / '+str(offset//5+1),[k+' - '+v for k,v in list(REQUESTS.items())[offset:offset+5]])
            for title,reference,routes in GROUPS:
                text_page(doc,'Coverage / '+title,[reference]+[' / '.join(routes[i:i+2]) for i in range(0,len(routes),2)])
        for number in range(first,len(doc)):
            box(doc[number],(1040,805,1150,830),f'Page {number+1} of {len(doc)}',10)
        supplements=[doc[i] for i in range(first,len(doc))]
        if kind=='guide': update_guide(supplements)
        else: update_visio(DOCS/(base+'.vsdx'),supplements)
        save_pdf(doc,path)

def verify():
    expected={base+ext for base in ['PK_DTS_DFD','PK_DTS_Flowchart','PK_DTS_Process_Flow'] for ext in ['.pdf','.vsdx']}
    expected.update({'PK_DTS_User_Guide.pdf','PK_DTS_User_Guide.pptx'})
    assert {p.name for p in DOCS.iterdir()}==expected
    assert {p.name for p in DOCS.parent.iterdir()}=={'system-documentation'}, 'Redundant docs remain'
    all_text=''
    for path in sorted(DOCS.glob('*.pdf')):
        with pdf.open(path) as doc:
            text='\n'.join(p.get_text() for p in doc); all_text+=text
            assert MARKER in text
            assert 'PROPOSED' not in text and 'PROPOSAL  ·  REVISION 02' not in text
            print(path.name, len(doc),'pages')
            for p in doc:
                assert len(p.get_text().strip())>30, f'Empty page in {path}'
                for block in p.get_text('dict')['blocks']:
                    for line in block.get('lines',[]):
                        for span in line['spans']:
                            assert p.rect.contains(pdf.Rect(span['bbox'])), f'Text outside page in {path}: {span["text"]}'
    for path in DOCS.glob('*.vsdx'):
        with zipfile.ZipFile(path) as z:
            assert z.testzip() is None
            for name in z.namelist():
                if name.endswith('.xml'): ET.fromstring(z.read(name))
            pages=ET.fromstring(z.read('visio/pages/pages.xml'))
            with pdf.open(path.with_suffix('.pdf')) as doc:
                assert len(pages)==len(doc)
                for index,page in enumerate(doc):
                    if MARKER not in page.get_text(): continue
                    native=ET.fromstring(z.read(f'visio/pages/page{index+1}.xml'))
                    native_text='\n'.join(''.join(t.itertext()) for t in native.iter('{'+NS+'}Text'))
                    assert page.get_text().split()==native_text.split(), f'Visio/PDF supplement mismatch: {path} page {index+1}'
            assert any(MARKER.encode() in z.read(n) for n in z.namelist() if n.startswith('visio/pages/page'))
            print(path.name,len(pages),'editable pages')
    prs=Presentation(DOCS/'PK_DTS_User_Guide.pptx')
    with pdf.open(DOCS/'PK_DTS_User_Guide.pdf') as doc:
        assert len(prs.slides)==len(doc)
        for i,page in enumerate(doc):
            if MARKER not in page.get_text(): continue
            native_text='\n'.join(s.text for s in prs.slides[i].shapes if s.has_text_frame)
            assert page.get_text().split()==native_text.split(), f'PowerPoint/PDF supplement mismatch on page {i+1}'
    ppt_text='\n'.join(s.text for slide in prs.slides for s in slide.shapes if s.has_text_frame)
    assert MARKER in ppt_text and 'PROPOSED' not in ppt_text
    for _,_,routes in GROUPS:
        for route in routes: assert route in all_text and route in ppt_text, route
    for key in REQUESTS: assert key in all_text and key in ppt_text, key
    print('Verified: exactly 8 deliverables, matching editable/PDF page counts, full route/request inventory.')

if __name__=='__main__':
    check_source()
    if '--verify' not in sys.argv: refresh()
    verify()
