"""Build the PK DCS Quick Guide: a short summary of the Step-by-Step User Guide.

Run (after capture_guide.py): python -X utf8 tools/documentation/build_quick_guide.py
Output:  tools/documentation/build/PK_DCS_Quick_Guide.pptx (a draft to review).
         With --publish it is written to docs/system-documentation/ and docs/.

It has the same look as the Step-by-Step User Guide: it uses that deck's template, layout helpers,
screenshots and checked step text (build_user_guide.py and user_guide_content.py). The introduction
is the operation briefing "Document Control System (DCS)", corrected to match how PK DCS works.
"""
from pathlib import Path
import json
import shutil
import sys

from pptx import Presentation
from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import MSO_ANCHOR, PP_ALIGN
from pptx.util import Pt

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
import build_user_guide as b  # noqa: E402
import user_guide_content as content  # noqa: E402

DRAFT = HERE / 'build' / 'PK_DCS_Quick_Guide.pptx'
PUBLISHED = [b.ROOT / 'docs/system-documentation/PK_DCS_Quick_Guide.pptx', b.ROOT / 'docs/PK_DCS_Quick_Guide.pptx']
GREEN_FILL, GREEN = 'E8F3EF', '1A685B'
EVERYONE = 'Everyone'

SECTIONS = {
    1: dict(title='Introduction', summary='What PK DCS is, why Operation needs it, and how documents are owned '
                                          'and controlled.'),
    2: dict(title='The four document types', summary='Hardcopy, softcopy, external and scanned documents: what '
                                                     'each one is and how it changes.'),
    3: dict(title='My Requests', summary='Every request you make, of every type, in one place: start it, submit '
                                         'it, follow it and correct it.'),
    4: dict(title='My Tasks', summary='Where requests are decided: review, approve, return or reject, and hand '
                                      'over paper documents.'),
}

LIFECYCLE = [
    ('Creation', 'A request or a direct entry creates the record. A softcopy gets its number from its '
                 'category pattern.'),
    ('Approval', 'Each request follows its approval route. Nothing changes until the last approval.'),
    ('Distribution', 'Online through access and assignment requests; on paper through a hardcopy transfer.'),
    ('Revision', 'A revision request replaces the file. The old file stays in the history as Superseded.'),
    ('Storage', 'The master copy stays in PK DCS, with a full encrypted backup every day.'),
]

PROBLEMS = [
    ('Filing is manual, so documents are easy to lose.',
     'Every document has a record in one register, with its file or its storage place and holder.'),
    ('When an audit comes, we rush to find documents.',
     'Search and filters find a document. The approval history and audit trail show who did what, and when.'),
    ('Staff use old versions, with wrong or outdated procedures.',
     'Only the current approved file can be downloaded. Older files are marked Superseded.'),
    ('When staff leave, their documents leave with them.',
     'Documents are assigned to people in PK DCS and can be reassigned. The files never leave the system.'),
]

GOAL = ('Main goal: every company document is created, approved, distributed and stored in a controlled way, '
        'and only the current version is available to users.')
OBJECTIVES = [
    ('Creation', 'Every document is registered with its category, a document number from the category pattern, '
                 'and an owner.'),
    ('Approval', 'A softcopy is released only after the requester\'s leader (Noted By), the Plant Manager and '
                 'the Document Control Officer approve it.'),
    ('Distribution', 'Documents reach the people concerned online (access or assignment) or on paper (a '
                     'hardcopy transfer with dispatch and accept).'),
    ('Revision', 'Every change goes through a revision request. The old file stays in the history, marked '
                 'Superseded.'),
    ('Storage', 'The master copy is kept only in PK DCS, with a daily encrypted backup and an audit trail.'),
    ('Control', 'The Document Control Officer controls all documents. Other users work only with the documents '
                'assigned or granted to them.'),
]

SUCCESS = [
    'Staff find any document in seconds with the **search bar**.',
    'Nobody works from an old version: only the **current file** can be downloaded.',
    'Audit-ready at any time: documents and their **approval history** are ready in minutes.',
    'Every document has a **clear owner**.',
    'No documents go missing when someone leaves: they are **reassigned**.',
]

OWNERSHIP = [
    'Every document has **one owner**: the assigned user of a softcopy, or the Current Holder of a hardcopy.',
    'Staff see only the documents **assigned or granted** to them.',
    'Anyone else asks with **Request Document Access**. The Plant Manager and the Document Control Officer '
    'approve it, and access ends on its expiry date.',
    'The **Document Control Officer** controls access to, and release of, all documents.',
]

DOCUMENT_TYPES = dict(
    header=['Type', 'What it is', 'How it is added', 'How it changes'],
    widths=[118, 270, 220, 280], size=11.5,
    rows=[
        ['Hardcopy', 'A paper document, tracked by its storage place (Area › Specific › Location) and its '
                     'Current Holder. No file is attached.',
         'Document Control registers it directly.',
         'A transfer request moves it (dispatch, then accept). A disposal request disposes of it. Retention '
         'reminders show when it is due for review.'],
        ['Softcopy', 'A controlled electronic document with a number, revision level and approved file.',
         'Create Softcopy Document request, or Document Control directly.',
         'Revise Softcopy Document request. An editable template can be requested to prepare the revision.'],
        ['External', 'A document received from outside the company, kept as a scanned backup copy in its own '
                     'categories.',
         'Create External Document request, or Document Control directly.',
         'Revise External Document request. A disposal request disposes of it.'],
        ['Scanned', 'A scanned record in an approved folder, with optional retention dates.',
         'Upload Scanned Document request.',
         'Update or delete it with a scanned request; Create Scanned Category adds a folder. Document Control '
         'approves each one.'],
    ])

REQUEST_ACTIONS = dict(
    header=['Request Action', 'Use it to', 'Approved by (default route)'],
    widths=[232, 330, 326], size=10.5,
    rows=[
        ['**Create Softcopy Document**', 'Propose a new controlled document with its file.',
         'Noted By (your leader) › Plant Manager › Document Control Officer'],
        ['**Revise Softcopy Document**', 'Replace the file of an Active softcopy.',
         'Noted By (your leader) › Plant Manager › Document Control Officer'],
        ['**Request Template for Revision**', 'Get an editable Word or Excel copy to prepare a revision.',
         'Document Control Officer'],
        ['**Create** or **Revise External Document**', 'Register or revise a document from outside the company.',
         'Plant Manager › Document Control Officer'],
        ['**Upload**, **Update** or **Delete Scanned Document**, **Create Scanned Category**',
         'Keep scanned records and their folders up to date.', 'Document Control Officer'],
        ['**Transfer Hardcopy Document**', 'Move a paper document to another place and person.',
         'Plant Manager › Document Control Officer › your confirmation; then dispatch and accept'],
        ['**Dispose Document**', 'Dispose of a hardcopy or external document.',
         'Plant Manager › Document Control Officer › your confirmation'],
        ['**Request Document Access**', 'Open a softcopy you cannot see yet, until an expiry date.',
         'Plant Manager › Document Control Officer › your confirmation'],
        ['**Request Document Assignment**', 'Become the assigned person for a softcopy.',
         'Plant Manager › Document Control Officer › your leader'],
    ])

CLOSING = ('PK DCS puts the documents we already have in one controlled place, so Operation works faster and '
           'is audit-ready at any time.')


# ---------------------------------------------------------------------------- content from the full guide
def guide_task(task_id, size=None):
    task = next(task for section in content.SECTIONS for task in section['tasks'] if task['id'] == task_id)
    return dict(task, size=size) if size else task


def guide_step(task_id, number, title=None):
    """One checked step of the full guide, shown here as a single-step task."""
    task = guide_task(task_id)
    step = dict(task['steps'][number - 1])
    step.setdefault('who', task['who'])
    return dict(title=title or step['title'], who=step['who'], steps=[step])


# ---------------------------------------------------------------------------- slides
def cover(total):
    slide = b.prs.slides.add_slide(b.layouts['GUIDE_COVER'])
    b.write(slide, 72, 72, 520, 16, 'PEANUT KISSES  ·  DOCUMENT CONTROL SYSTEM', 11, b.PINK, bold=True, letter=3,
            fit=False)
    b.write(slide, 72, 148, 520, 82, 'PK DCS', 56, 'FFFFFF', bold=True, fit=False)
    b.write(slide, 72, 282, 480, 60, 'For Operation: filing and making documents. The four document types, '
                                     'My Requests and My Tasks in one short guide.', 17, 'F6D9E0')
    x = 72
    for label in ['Document types', 'My Requests', 'My Tasks']:
        w = b.width_of(label, 13, True) + 34
        b.box(slide, x, 380, w, 30, None, b.PINK, MSO_SHAPE.ROUNDED_RECTANGLE, 0.5, 1)
        b.write(slide, x, 380, w, 30, label, 13, 'FFFFFF', bold=True, align=PP_ALIGN.CENTER,
                anchor=MSO_ANCHOR.MIDDLE, fit=False)
        x += w + 12
    b.write(slide, 72, 470, 480, 16, f'Matches the system as of {b.EDITION}  ·  {total} slides', 11, 'E7C4CD',
            fit=False)
    for sid, left, top, width in [('req-register', 585, 92, 360), ('task-register', 625, 262, 325)]:
        shot = b.manifest[sid]
        height = width * shot['height'] / shot['width']
        picture = slide.shapes.add_picture(str(b.SHOTS / shot['file']), Pt(left), Pt(top), Pt(width), Pt(height))
        picture.line.color.rgb = b.rgb('FFFFFF')
        picture.line.width = Pt(1.5)


def chevron(slide, x, y):
    b.box(slide, x, y, 10, 16, b.PINK, shape=MSO_SHAPE.CHEVRON)


def lifecycle():
    slide = b.content_slide()
    b.eyebrow(slide, 'Introduction')
    b.title(slide, 'What is PK DCS?')
    b.write(slide, 36, 96, 888, 20, 'PK DCS controls every company document from start to finish, in one online '
                                    'system.', 13, b.GREY)
    w, gap = 160, 22
    for index, (heading, text) in enumerate(LIFECYCLE):
        x = 36 + index * (w + gap)
        b.box(slide, x, 140, w, 232, b.PALE, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.05)
        b.number(slide, x + 16, 156, index + 1, 26)
        b.write(slide, x + 16, 196, w - 32, 20, heading, 14, b.RUBY, bold=True)
        b.write(slide, x + 16, 224, w - 32, 140, text, 12.5, b.INK)
        if index < len(LIFECYCLE) - 1:
            chevron(slide, x + w + 6, 248)
    b.note_box(slide, 36, 492, 888, 'good', 'All documents are in one place: the hardcopy, softcopy, external and '
                                            'scanned registers of PK DCS.')
    b.pill(slide, EVERYONE)


def problems():
    slide = b.content_slide()
    b.eyebrow(slide, 'Introduction')
    b.title(slide, 'Why do we need PK DCS?')
    b.write(slide, 36, 104, 404, 14, 'Today in Operation', 9.5, b.GREY, bold=True, caps=True, letter=2, fit=False)
    b.write(slide, 520, 104, 404, 14, 'With PK DCS', 9.5, GREEN, bold=True, caps=True, letter=2, fit=False)
    for index, (before, after) in enumerate(PROBLEMS):
        y = 126 + index * 92
        b.box(slide, 36, y, 404, 82, b.PALE, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.08)
        b.write(slide, 52, y, 372, 82, before, 12.5, b.INK, anchor=MSO_ANCHOR.MIDDLE)
        b.box(slide, 458, y + 31, 44, 20, b.RUBY, shape=MSO_SHAPE.RIGHT_ARROW)
        b.box(slide, 520, y, 404, 82, GREEN_FILL, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.08)
        b.write(slide, 536, y, 372, 82, after, 12.5, b.INK, anchor=MSO_ANCHOR.MIDDLE)
    b.pill(slide, EVERYONE)


def objectives():
    slide = b.content_slide()
    b.eyebrow(slide, 'Introduction')
    b.title(slide, 'Goal and objectives')
    b.write(slide, 36, 96, 888, 36, GOAL, 13, b.GREY)
    for index, (heading, text) in enumerate(OBJECTIVES):
        x, y = 36 + (index % 3) * 300, 146 + (index // 3) * 174
        b.box(slide, x, y, 288, 162, b.PALE, shape=MSO_SHAPE.ROUNDED_RECTANGLE, radius=0.05)
        b.number(slide, x + 16, y + 16, index + 1, 22)
        b.write(slide, x + 48, y + 18, 224, 20, heading, 13.5, b.RUBY, bold=True)
        b.write(slide, x + 16, y + 52, 256, 100, text, 12.5, b.INK)
    b.pill(slide, EVERYONE)


def success():
    slide = b.content_slide()
    b.eyebrow(slide, 'Introduction')
    b.title(slide, 'What success looks like')
    y = 112
    for item in SUCCESS:
        b.box(slide, 36, y, 24, 24, GREEN, shape=MSO_SHAPE.OVAL)
        b.write(slide, 36, y, 24, 24, '✓', 13, 'FFFFFF', bold=True, align=PP_ALIGN.CENTER,
                anchor=MSO_ANCHOR.MIDDLE, fit=False)
        b.write(slide, 72, y + 2, 360, 52, item, 13, b.INK)
        y += 74
    b.place_screen(slide, 'list-tools', [], area=(456, 112, 468, 380))
    b.pill(slide, EVERYONE)


def ownership():
    slide = b.content_slide()
    b.eyebrow(slide, 'Introduction')
    b.title(slide, 'Who owns what documents')
    b.write(slide, 36, 104, 400, 14, 'Document ownership rules', 9.5, b.GREY, bold=True, caps=True, letter=2,
            fit=False)
    y = 130
    for index, item in enumerate(OWNERSHIP, 1):
        h = b.text_height(item, 12.5, 372)
        b.number(slide, 36, y, index, 20)
        b.write(slide, 66, y + 1, 372, h + 1, item, 12.5, b.INK)
        y += h + 20
    b.place_screen(slide, 'req-access', [], area=(470, 100, 454, 392))
    b.pill(slide, EVERYONE)


def table_slide(section_title, heading, intro, spec, who, box=None):
    slide = b.content_slide()
    b.eyebrow(slide, section_title)
    b.title(slide, heading)
    b.write(slide, 36, 96, 888, 20, intro, 13, b.GREY)
    b.table(slide, 36, 128, 888, spec['header'], spec['rows'], spec['widths'], spec['size'])
    if box:
        b.note_box(slide, 36, 492, 888, *box)
    b.pill(slide, who)


def closing():
    slide = b.prs.slides.add_slide(b.layouts['GUIDE_COVER'])
    b.slide_number(slide)
    b.write(slide, 72, 72, 520, 16, 'CLOSING', 11, b.PINK, bold=True, letter=3, fit=False)
    b.write(slide, 72, 112, 600, 110, 'One place for\nevery document', 46, 'FFFFFF', bold=True, fit=False)
    b.write(slide, 72, 250, 560, 60, CLOSING, 17, 'F6D9E0')
    for index, (heading, text) in enumerate([
            ('Every task, screen by screen', 'The Step-by-Step User Guide covers each task in this summary, '
                                             'and more.'),
            ('Questions', 'Ask the Document Control Officer, or the Administrator for your account.')]):
        x = 72 + index * 300
        b.box(slide, x, 352, 284, 100, None, b.PINK, MSO_SHAPE.ROUNDED_RECTANGLE, 0.08, 1)
        b.write(slide, x + 16, 366, 252, 18, heading, 13, b.PINK, bold=True)
        b.write(slide, x + 16, 392, 252, 52, text, 12, 'FFFFFF')


# ---------------------------------------------------------------------------- plan and build
def retitle_footer(layout):
    """The shared template's footer says Step-by-Step User Guide; this deck is the Quick Guide."""
    runs = [run for shape in layout.shapes if shape.has_text_frame
            for paragraph in shape.text_frame.paragraphs for run in paragraph.runs
            if 'Step-by-Step User Guide' in run.text]
    assert len(runs) == 1, 'Template footer not found'
    runs[0].text = runs[0].text.replace('Step-by-Step User Guide', 'Quick Guide')


def plan():
    """(kind, key, payload) in slide order; section slides list the slides that follow them."""
    finding = guide_step('lists', 1, 'Find a document')
    return [
        ('cover', None, None),
        ('section', 1, ['lifecycle', 'problems', 'objectives', 'success', 'ownership']),
        ('custom', 'lifecycle', lifecycle),
        ('custom', 'problems', problems),
        ('custom', 'objectives', objectives),
        ('custom', 'success', success),
        ('custom', 'ownership', ownership),
        ('section', 2, ['types', 'finding']),
        ('table', 'types', ('The four document types', 'The four document types',
                            'PK DCS keeps four kinds of documents, each in its own register.', DOCUMENT_TYPES,
                            EVERYONE, ('keep', 'You see only the registers and documents your role allows. Use '
                                               'Request Document Access for a softcopy you cannot open.'))),
        ('step', 'finding', finding),
        ('section', 3, ['requests', 'actions', 'start', 'making', 'follow', 'returned', 'statuses']),
        ('step', 'requests', guide_step('req-overview', 1, 'My Requests: all your requests')),
        ('table', 'actions', ('My Requests', 'Request actions',
                              'Choose one of these in New Request. You see only the actions your role allows.',
                              REQUEST_ACTIONS, content.REQUESTER, None)),
        ('step', 'start', guide_step('req-start', 1, 'Start, save and submit a request')),
        ('step', 'making', guide_step('req-softcopy-create', 1, 'Making a document: request a new softcopy')),
        ('step', 'follow', guide_step('req-follow', 1, 'Follow a request')),
        ('step', 'returned', guide_step('req-returned', 1, 'Correct a returned request')),
        ('reference', 'statuses', guide_task('statuses', 11.5)),
        ('section', 4, ['tasks', 'decide', 'return', 'final', 'routes']),
        ('step', 'tasks', guide_step('approve', 1, 'My Tasks: requests waiting for you')),
        ('step', 'decide', guide_step('approve', 2, 'Review and decide')),
        ('step', 'return', guide_step('approve', 4, 'Give a reason to return or reject')),
        ('step', 'final', guide_step('final-file', 1, 'Document Control uploads the final file')),
        ('reference', 'routes', guide_task('routes', 11)),
        ('closing', None, None),
    ]


def main():
    b.manifest = json.loads((b.SHOTS / 'manifest.json').read_text(encoding='utf-8'))
    b.problems, b.pending_links = [], []
    order = plan()
    numbers = {key: position for position, (kind, key, _) in enumerate(order, 1) if kind not in ('section',)}
    b.target_numbers = numbers
    b.prs = Presentation(str(b.TEMPLATE))
    b.layouts = {layout.name: layout for layout in b.prs.slide_layouts}
    retitle_footer(b.layouts['GUIDE_CONTENT'])
    titles, slides = {}, {}
    for kind, key, payload in order:
        if kind == 'step':
            titles[key] = payload['title']
        elif kind == 'table':
            titles[key] = payload[1]
        elif kind == 'reference':
            titles[key] = payload['title']
        elif kind == 'custom':
            titles[key] = {'lifecycle': 'What is PK DCS?', 'problems': 'Why do we need PK DCS?',
                           'objectives': 'Goal and objectives', 'success': 'What success looks like',
                           'ownership': 'Who owns what documents'}[key]
    current = None
    for kind, key, payload in order:
        if kind == 'cover':
            cover(len(order))
        elif kind == 'section':
            current = SECTIONS[key]
            section = dict(current, tasks=[dict(title=titles[k], first=k) for k in payload])
            b.build_section(section, key)
        elif kind == 'custom':
            payload()
        elif kind == 'table':
            table_slide(*payload)
        elif kind == 'step':
            b.build_step(current, payload, payload['steps'][0], 1)
        elif kind == 'reference':
            b.build_reference(current, payload)
        elif kind == 'closing':
            closing()
        if key in numbers and kind != 'cover':
            slides[key] = b.prs.slides[-1]
    for shape, target in b.pending_links:
        shape.click_action.target_slide = slides[target]
    assert len(b.prs.slides) == len(order), (len(b.prs.slides), len(order))
    if b.problems:
        raise SystemExit('\n'.join(['Cannot build the Quick Guide:'] + b.problems))
    b.prs.core_properties.title = 'PK DCS Quick Guide'
    b.prs.core_properties.subject = 'Summary of the PK Document Control System user guide, ' + b.EDITION
    b.prs.core_properties.author = 'PK Document Control System'
    outputs = PUBLISHED if '--publish' in sys.argv[1:] else [DRAFT]
    outputs[0].parent.mkdir(parents=True, exist_ok=True)
    pending = outputs[0].with_suffix('.pptx.pending')
    b.prs.save(str(pending))
    for output in outputs:
        shutil.copyfile(pending, output)
    pending.unlink()
    print(f'Saved {len(b.prs.slides)} slides to ' + ', '.join(str(o.relative_to(b.ROOT)) for o in outputs))


if __name__ == '__main__':
    main()
