"""
Extraccion y marcado OOXML nativo para .pptx (tablas y shapes).

LibreOffice a veces no lee texto de tablas en presentaciones importadas
desde PowerPoint; este modulo lee el ZIP/XML directamente.

El resaltado OOXML se hace por edicion de texto (sin ElementTree.serialize)
para no romper namespaces ni corromper el .pptx.
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
A_TBL = f"{{{A_NS}}}tbl"
A_TC = f"{{{A_NS}}}tc"
A_TR = f"{{{A_NS}}}tr"

PPTX_HIGHLIGHT_RGB = "FFF9C4"
PPTX_HIGHLIGHT_FRAGMENT = (
    f'<a:highlight><a:srgbClr val="{PPTX_HIGHLIGHT_RGB}"/></a:highlight>'
)


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


def _word_pattern(word):
    return re.compile(r"\b" + re.escape(word) + r"\b", re.IGNORECASE)


def _run_has_highlight(run_xml):
    return "<a:highlight" in run_xml


def _extract_rpr(run_xml):
    match = re.search(
        r"(<a:rPr[^>]*>.*?</a:rPr>|<a:rPr[^>]*/>)",
        run_xml,
        re.DOTALL | re.IGNORECASE,
    )
    return match.group(1) if match else "<a:rPr/>"


def _rpr_with_highlight(rpr_xml):
    if rpr_xml.rstrip().endswith("/>"):
        return rpr_xml[:-2] + f">{PPTX_HIGHLIGHT_FRAGMENT}</a:rPr>"
    return rpr_xml.replace(
        "</a:rPr>", f"{PPTX_HIGHLIGHT_FRAGMENT}</a:rPr>", 1
    )


def _highlight_word_in_run(run_xml, word):
    """Resalta una palabra dentro de <a:t>, partiendo el run si hace falta."""
    if _run_has_highlight(run_xml):
        return run_xml, False

    t_match = re.search(r"<a:t>(.*?)</a:t>", run_xml, re.DOTALL | re.IGNORECASE)
    if not t_match:
        return run_xml, False

    content = t_match.group(1)
    match = _word_pattern(word).search(content)
    if not match:
        return run_xml, False

    before = content[: match.start()]
    mid = content[match.start() : match.end()]
    after = content[match.end() :]

    rpr = _extract_rpr(run_xml)
    rpr_hl = _rpr_with_highlight(rpr)

    parts = []
    if before:
        parts.append(f"<a:r>{rpr}<a:t>{before}</a:t></a:r>")
    parts.append(f"<a:r>{rpr_hl}<a:t>{mid}</a:t></a:r>")
    if after:
        parts.append(f"<a:r>{rpr}<a:t>{after}</a:t></a:r>")

    return "".join(parts), True


def _highlight_slide_xml(xml_bytes, errores):
    if not errores:
        return xml_bytes, 0

    try:
        text = xml_bytes.decode("utf-8")
    except UnicodeDecodeError:
        return xml_bytes, 0

    highlighted = 0
    words = [e.get("palabra", "") for e in errores if e.get("palabra")]
    if not words:
        return xml_bytes, 0

    def process_run(match):
        nonlocal highlighted
        run_xml = match.group(0)
        changed = False
        for word in words:
            new_xml, did = _highlight_word_in_run(run_xml, word)
            if did:
                run_xml = new_xml
                changed = True
        if changed:
            highlighted += 1
        return run_xml

    new_text = re.sub(
        r"<a:r\b[^>]*>.*?</a:r>",
        process_run,
        text,
        flags=re.DOTALL | re.IGNORECASE,
    )

    if highlighted == 0:
        return xml_bytes, 0

    return new_text.encode("utf-8"), highlighted


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

    with zipfile.ZipFile(path, "r") as zin, zipfile.ZipFile(
        buffer, "w", compression=zipfile.ZIP_DEFLATED
    ) as zout:
        for item in zin.infolist():
            data = zin.read(item.filename)
            if item.filename.startswith("ppt/slides/slide") and item.filename.endswith(".xml"):
                data, count = _highlight_slide_xml(data, errores)
                total += count
            zinfo = zipfile.ZipInfo(
                filename=item.filename,
                date_time=item.date_time,
            )
            zinfo.compress_type = item.compress_type
            zinfo.external_attr = item.external_attr
            zinfo.create_system = item.create_system
            zinfo.flag_bits = item.flag_bits
            zout.writestr(zinfo, data)

    if total > 0:
        path.write_bytes(buffer.getvalue())

    return total
