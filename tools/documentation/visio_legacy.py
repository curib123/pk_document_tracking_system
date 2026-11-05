"""Convert the documentation's native VSDX parts to editable Visio 2003 XML.

DatadiagramML uses named cell elements grouped by ShapeSheet section; changing
the VSDX namespace alone does not produce a legacy drawing. See Microsoft's
Visio 2003 XML reference for FaceName, Char, Para, Geom and PageProps.
"""
import posixpath
import xml.etree.ElementTree as ET


VDX_NS = 'http://schemas.microsoft.com/visio/2003/core'
VSDX_NS = 'http://schemas.microsoft.com/office/visio/2012/main'
REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'
XML_NS = 'http://www.w3.org/XML/1998/namespace'

# Only cells produced by refresh_documents are translated. Fail on new cells
# rather than silently dropping editable content from the compatibility copy.
CELL_GROUPS = {
    'XForm': 'PinX PinY Width Height LocPinX LocPinY Angle FlipX FlipY ResizeMode',
    'Line': 'LineWeight LineColor LinePattern Rounding BeginArrow EndArrow LineCap BeginArrowSize EndArrowSize LineColorTrans',
    'Fill': 'FillForegnd FillBkgnd FillPattern ShdwForegnd ShdwBkgnd ShdwPattern FillForegndTrans FillBkgndTrans ShdwForegndTrans ShdwBkgndTrans',
    'StyleProp': 'EnableLineProps EnableFillProps EnableTextProps HideForApply',
    'PageProps': 'PageWidth PageHeight ShdwOffsetX ShdwOffsetY PageScale DrawingScale DrawingSizeType DrawingScaleType InhibitSnap UIVisibility ShdwType ShdwObliqueAngle ShdwScaleFactor',
    'TextBlock': 'LeftMargin RightMargin TopMargin BottomMargin VerticalAlign DefaultTabStop TextBkgnd TextDirection TextBkgndTrans',
    'PrintProps': 'PageLeftMargin PageRightMargin PageTopMargin PageBottomMargin ScaleX ScaleY PagesX PagesY CenterX CenterY OnPage PrintGrid PrintPageOrientation PaperKind PaperSource',
}
GROUP_FOR_CELL = {name: group for group, names in CELL_GROUPS.items() for name in names.split()}


def _name(element):
    return element.tag.rsplit('}', 1)[-1]


def _copy(element):
    node = ET.Element(_name(element), dict(element.attrib))
    node.text, node.tail = element.text, element.tail
    node.extend(_copy(child) for child in element)
    return node


def legacy_visio(parts, title):
    """Return a standalone VDX containing every native page, shape and text run."""
    tag = lambda name: '{'+VSDX_NS+'}'+name
    document = ET.fromstring(parts['visio/document.xml'])
    root = ET.Element('VisioDocument', {
        'xmlns': VDX_NS, 'version': '11.0', 'metric': '0',
        'DocLangID': '1033', '{'+XML_NS+'}space': 'preserve',
    })
    properties = ET.SubElement(root, 'DocumentProperties')
    ET.SubElement(properties, 'Title').text = title
    settings = document.find(tag('DocumentSettings'))
    if settings is not None:
        root.append(_copy(settings))
    colors = document.find(tag('Colors'))
    if colors is not None:
        root.append(_copy(colors))
    faces = ET.SubElement(root, 'FaceNames')
    font_ids = {}
    for face in document.findall(tag('FaceNames')+'/'+tag('FaceName')):
        name = face.attrib['NameU']
        font_ids[name] = str(len(font_ids))
        attributes = {key: value for key, value in face.attrib.items()
                      if key in {'UnicodeRanges', 'CharSets', 'Panos', 'Flags'}}
        if 'Panose' in face.attrib:
            attributes['Panos'] = face.attrib['Panose']
        ET.SubElement(faces, 'FaceName', ID=font_ids[name], Name=name, **attributes)

    def cell(parent, source):
        name, value = source.attrib['N'], source.get('V', '')
        if name == 'Font':
            value = font_ids[value]
        attributes = {target: source.attrib[key] for key, target in
                      [('U', 'Unit'), ('F', 'F'), ('E', 'Err')] if key in source.attrib}
        if name in {'PageWidth', 'PageHeight', 'PageScale', 'DrawingScale'}:
            attributes.setdefault('Unit', 'IN')
        ET.SubElement(parent, name, attributes).text = value

    def sheet(source):
        # IsCustomName flags were introduced after the 2003 core schema.
        node = ET.Element(_name(source), {key: value for key, value in source.attrib.items()
                          if key not in {'IsCustomName', 'IsCustomNameU'}})
        groups = {}
        for entry in source.findall(tag('Cell')):
            group = GROUP_FOR_CELL[entry.attrib['N']]
            if group not in groups:
                groups[group] = ET.SubElement(node, group)
            cell(groups[group], entry)
        for section in source.findall(tag('Section')):
            kind = section.attrib['N']
            if kind == 'Geometry':
                geometry = ET.SubElement(node, 'Geom', IX=section.get('IX', '0'))
                for entry in section.findall(tag('Cell')):
                    cell(geometry, entry)
                for row in section.findall(tag('Row')):
                    if row.get('T') not in {'MoveTo', 'LineTo'}:
                        raise ValueError('Unsupported legacy geometry: '+str(row.get('T')))
                    target = ET.SubElement(geometry, row.attrib['T'], IX=row.attrib['IX'])
                    for entry in row.findall(tag('Cell')):
                        cell(target, entry)
            elif kind in {'Character', 'Paragraph'}:
                for row in section.findall(tag('Row')):
                    target = ET.SubElement(node, 'Char' if kind == 'Character' else 'Para', IX=row.attrib['IX'])
                    for entry in row.findall(tag('Cell')):
                        cell(target, entry)
            else:
                raise ValueError('Unsupported legacy section: '+kind)
        text = source.find(tag('Text'))
        if text is not None:
            node.append(_copy(text))
        shapes = source.find(tag('Shapes'))
        if shapes is not None:
            ET.SubElement(node, 'Shapes').extend(sheet(child) for child in shapes)
        return node

    styles = ET.SubElement(root, 'StyleSheets')
    styles.extend(sheet(style) for style in document.findall(tag('StyleSheets')+'/'+tag('StyleSheet')))
    document_sheet = document.find(tag('DocumentSheet'))
    if document_sheet is not None:
        root.append(sheet(document_sheet))
    pages = ET.SubElement(root, 'Pages')
    relationships = ET.fromstring(parts['visio/pages/_rels/pages.xml.rels'])
    targets = {relationship.attrib['Id']: posixpath.normpath(posixpath.join(
        'visio/pages', relationship.attrib['Target'])) for relationship in relationships}
    for page in ET.fromstring(parts['visio/pages/pages.xml']):
        node = ET.SubElement(pages, 'Page', {key: value for key, value in page.attrib.items()
                             if key not in {'IsCustomName', 'IsCustomNameU'}})
        page_sheet = page.find(tag('PageSheet'))
        if page_sheet is not None:
            node.append(sheet(page_sheet))
        relationship_id = page.find(tag('Rel')).attrib['{'+REL_NS+'}id']
        contents = ET.fromstring(parts[targets[relationship_id]])
        source_shapes = contents.find(tag('Shapes'))
        if source_shapes is not None:
            ET.SubElement(node, 'Shapes').extend(sheet(shape) for shape in source_shapes)
    return ET.tostring(root, encoding='utf-8', xml_declaration=True)
