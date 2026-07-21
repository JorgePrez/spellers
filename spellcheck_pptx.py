"""
Extraccion y marcado OOXML nativo para .pptx (tablas y shapes).

LibreOffice a veces no lee texto de tablas en presentaciones importadas
desde PowerPoint; este modulo lee el ZIP/XML directamente.
"""
import io
import re
import zipfile
from pathlib import Path
from xml.etree import ElementTree as ET

A_NS = "http://schemas.openxmlformats.org/drawingml/2006/main"
P_NS = "http://schemas.openxmlformats.org/presentationml/2006/main"
REL_NS = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"

A_T = f"{{{A_NS}}}t"
A_R = f"{{{A_NS}}}r"
A_RPR = f"{{{A_NS}}}rPr"
A_HIGHLIGHT = f"{{{A_NS}}}highlight"
A_SRGB = f"{{{A_NS}}}srgbClr"
A_TBL = f"{{{A_NS}}}tbl"
A_TC = f"{{{A_NS}}}tc"
A_TR = f"{{{A_NS}}}tr"

PPTX_HIGHLIGHT_RGB = "FFF9C4"


def _slide_paths_from_pptx(zf):
    """Orden de diapositivas segun presentation.xml."""
    ordered = []
    try:
        pres = ET.fromstring(zf.read("ppt/presentation.xml"))
        rels = ET.fromstring(zf.read("ppt/_rels/presentation.xml.rels"))
        rel_map = {
            rel.get("Id"): rel.get("Target")
            for rel in rels
            if rel.tag.endswith("Relationship")
        }
        sld_id_lst = pres.find(f"{{{P_NS}}}sldIdLst")
        if sld_id_lst is not None:
            for sld_id in sld_id_lst:
                rid = sld_id.get(f"{{{REL_NS}}}id")
                target = rel_map.get(rid, "")
                if target.startswith("/"):
                    target = target[1:]
                elif target and not target.startswith("ppt/"):
                    target = f"ppt/{target}"
                if target:
                    ordered.append(target)
    except Exception:
        pass

    if not ordered:
        ordered = sorted(
            name
            for name in zf.namelist()
            if name.startswith("ppt/slides/slide") and name.endswith(".xml")
        )
    return ordered


def _texts_from_slide_root(root):
    chunks = []
    for node in root.iter(A_T):
        if node.text and node.text.strip():
            chunks.append(node.text)
    return chunks


def _table_texts_from_slide_root(root):
    chunks = []
    for tbl in root.iter(A_TBL):
        for tr in tbl.findall(f".//{A_TR}"):
            row_parts = []
            for tc in tr.findall(A_TC):
                cell_text = "".join(
                    (node.text or "") for node in tc.iter(A_T)
                ).strip()
                if cell_text:
                    row_parts.append(cell_text)
            if row_parts:
                chunks.append(" ".join(row_parts))
    return chunks


def extract_pptx_text_by_slide(path):
    """
    Devuelve {indice_0: texto_diapositiva, ...} leyendo OOXML del .pptx.
    """
    path = Path(path)
    result = {}
    if not path.exists() or path.suffix.lower() != ".pptx":
        return result

    with zipfile.ZipFile(path, "r") as zf:
        for index, slide_path in enumerate(_slide_paths_from_pptx(zf)):
            try:
                root = ET.fromstring(zf.read(slide_path))
            except Exception:
                continue

            parts = _texts_from_slide_root(root)
            if not parts:
                parts = _table_texts_from_slide_root(root)
            text = "\n".join(parts).strip()
            if text:
                result[index] = text

    return result


def extract_pptx_all_text(path):
    by_slide = extract_pptx_text_by_slide(path)
    return "\n".join(by_slide.values())


def merge_text_sources(*parts):
    chunks = []
    seen = set()
    for part in parts:
        part = (part or "").strip()
        if not part or part in seen:
            continue
        seen.add(part)
        chunks.append(part)
    return "\n".join(chunks)


def _parent_map(root):
    parents = {}
    for parent in root.iter():
        for child in list(parent):
            parents[child] = parent
    return parents


def _word_pattern(word):
    return re.compile(r"\b" + re.escape(word) + r"\b", re.IGNORECASE)


def _ensure_run_highlight(run_elem):
    rpr = run_elem.find(A_RPR)
    if rpr is None:
        rpr = ET.Element(A_RPR)
        run_elem.insert(0, rpr)

    if rpr.find(A_HIGHLIGHT) is None:
        hl = ET.SubElement(rpr, A_HIGHLIGHT)
        srgb = ET.SubElement(hl, A_SRGB)
        srgb.set("val", PPTX_HIGHLIGHT_RGB)


def _highlight_slide_xml(xml_bytes, errores):
    if not errores:
        return xml_bytes, 0

    root = ET.fromstring(xml_bytes)
    parents = _parent_map(root)
    highlighted = 0

    for t_elem in root.iter(A_T):
        text = t_elem.text or ""
        if not text.strip():
            continue

        run_elem = parents.get(t_elem)
        if run_elem is None or run_elem.tag != A_R:
            continue

        for error in errores:
            word = error.get("palabra", "")
            if not word:
                continue
            if _word_pattern(word).search(text):
                _ensure_run_highlight(run_elem)
                highlighted += 1
                break

    if highlighted == 0:
        return xml_bytes, 0

    return ET.tostring(root, encoding="utf-8", xml_declaration=True), highlighted


def highlight_pptx_errors(path, errores):
    """
    Post-procesa un .pptx guardado y resalta en amarillo las palabras con error.
    Devuelve cantidad de runs marcados.
    """
    path = Path(path)
    if not path.exists() or path.suffix.lower() != ".pptx" or not errores:
        return 0

    total = 0
    buffer = io.BytesIO()

    with zipfile.ZipFile(path, "r") as zin, zipfile.ZipFile(buffer, "w", zipfile.ZIP_DEFLATED) as zout:
        for item in zin.infolist():
            data = zin.read(item.filename)
            if item.filename.startswith("ppt/slides/slide") and item.filename.endswith(".xml"):
                data, count = _highlight_slide_xml(data, errores)
                total += count
            zout.writestr(item, data)

    if total > 0:
        path.write_bytes(buffer.getvalue())

    return total
