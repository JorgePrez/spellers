"""
Extraccion y marcado OOXML nativo para .pptx (tablas y shapes).

LibreOffice a veces no lee texto de tablas en presentaciones importadas
desde PowerPoint; este modulo lee el ZIP/XML directamente.

El resaltado OOXML se hace por edicion de texto (sin ElementTree.serialize)
para no romper namespaces ni corromper el .pptx.
"""
import io
import re
import unicodedata
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

PPTX_HIGHLIGHT_RGB = "FFFF99"
PPTX_HIGHLIGHT_FRAGMENT = (
    f'<a:highlight><a:srgbClr val="{PPTX_HIGHLIGHT_RGB}"/></a:highlight>'
)

# Orden OOXML de hijos en a:rPr (highlight va antes de uFillTx/latin).
_RPR_HIGHLIGHT_BEFORE = (
    "<a:uFillTx",
    "<a:uFill",
    "<a:uLnTx",
    "<a:uLn",
    "<a:latin",
    "<a:ea",
    "<a:cs",
    "<a:sym",
    "<a:hlinkClick",
    "<a:hlinkMouseOver",
)

_RUN_RE = re.compile(r"<a:r\b[^>]*>.*?</a:r>", re.DOTALL | re.IGNORECASE)


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


def _normalize_for_match(text):
    return unicodedata.normalize("NFC", text or "")


def _word_pattern(word):
    word = _normalize_for_match(word)
    return re.compile(r"\b" + re.escape(word) + r"\b", re.IGNORECASE)


def _run_text(run_xml):
    match = re.search(r"<a:t>(.*?)</a:t>", run_xml, re.DOTALL | re.IGNORECASE)
    return match.group(1) if match else ""


def _build_run(rpr, text):
    return f"<a:r>{rpr}<a:t>{text}</a:t></a:r>"


def _run_has_highlight(run_xml):
    if "<a:highlight" not in run_xml:
        return False
    rpr = _extract_rpr(run_xml)
    lower = rpr.lower()
    hl_pos = lower.find("<a:highlight")
    for anchor in _RPR_HIGHLIGHT_BEFORE:
        pos = lower.find(anchor.lower())
        if pos != -1 and hl_pos > pos:
            return False
    return True


def _strip_highlight_from_rpr(rpr_xml):
    return re.sub(
        r"<a:highlight>.*?</a:highlight>",
        "",
        rpr_xml,
        flags=re.DOTALL | re.IGNORECASE,
    )


def _normalize_slide_highlights(text):
    """Corrige a:highlight fuera de orden en todos los a:rPr de la diapositiva."""

    def fix_rpr(match):
        return _fix_highlight_position(match.group(0))

    return re.sub(
        r"<a:rPr[^>]*>.*?</a:rPr>",
        fix_rpr,
        text,
        flags=re.DOTALL | re.IGNORECASE,
    )


def _extract_rpr(run_xml):
    match = re.search(
        r"(<a:rPr[^>]*>.*?</a:rPr>|<a:rPr[^>]*/>)",
        run_xml,
        re.DOTALL | re.IGNORECASE,
    )
    return match.group(1) if match else "<a:rPr/>"


def _rpr_with_highlight(rpr_xml):
    if "<a:highlight" in rpr_xml:
        return _fix_highlight_position(rpr_xml)

    if rpr_xml.rstrip().endswith("/>"):
        return rpr_xml[:-2] + f">{PPTX_HIGHLIGHT_FRAGMENT}</a:rPr>"

    return _insert_highlight_before_anchors(rpr_xml)


def _fix_highlight_position(rpr_xml):
    """Mueve o reemplaza a:highlight en la posicion OOXML correcta."""
    if "<a:highlight" not in rpr_xml:
        return rpr_xml
    without = re.sub(
        r"<a:highlight>.*?</a:highlight>",
        "",
        rpr_xml,
        flags=re.DOTALL | re.IGNORECASE,
    )
    return _insert_highlight_before_anchors(without)


def _insert_highlight_before_anchors(rpr_xml, highlight=None):
    highlight = highlight or PPTX_HIGHLIGHT_FRAGMENT
    lower = rpr_xml.lower()
    insert_at = len(rpr_xml) - len("</a:rPr>")
    for anchor in _RPR_HIGHLIGHT_BEFORE:
        pos = lower.find(anchor.lower())
        if pos != -1 and pos < insert_at:
            insert_at = pos
            break
    else:
        return rpr_xml.replace("</a:rPr>", f"{highlight}</a:rPr>", 1)

    return rpr_xml[:insert_at] + highlight + rpr_xml[insert_at:]


def _highlight_word_in_run(run_xml, word):
    """Resalta la primera ocurrencia no marcada de word dentro de un <a:r>."""
    if _run_has_highlight(run_xml):
        return run_xml, False

    t_match = re.search(r"<a:t>(.*?)</a:t>", run_xml, re.DOTALL | re.IGNORECASE)
    if not t_match:
        return run_xml, False

    content = t_match.group(1)
    match = _word_pattern(word).search(_normalize_for_match(content))
    if not match:
        return run_xml, False

    before = content[: match.start()]
    mid = content[match.start() : match.end()]
    after = content[match.end() :]

    rpr = _extract_rpr(run_xml)
    if "<a:highlight" in rpr:
        rpr = _strip_highlight_from_rpr(rpr)
    rpr_hl = _rpr_with_highlight(rpr)

    parts = []
    if before:
        parts.append(_build_run(rpr, before))
    parts.append(_build_run(rpr_hl, mid))
    if after:
        parts.append(_build_run(rpr, after))

    return "".join(parts), True


def _highlight_all_in_run(run_xml, word):
    """Resalta todas las ocurrencias de word dentro del mismo <a:r>."""
    changed = False
    while True:
        new_xml, did = _highlight_word_in_run(run_xml, word)
        if not did:
            break
        run_xml = new_xml
        changed = True
    return run_xml, changed


def _highlight_split_word_pass(text, word):
    """
    Resalta palabras partidas en varios <a:r> contiguos (ej. Introducci + on).
    """
    matches = list(_RUN_RE.finditer(text))
    if not matches:
        return text, 0

    highlighted = 0
    skip_until = -1
    parts = []
    last_end = 0

    for index, match in enumerate(matches):
        if index < skip_until:
            continue

        run_xml = match.group(0)
        if _run_has_highlight(run_xml):
            continue

        combined = _normalize_for_match(_run_text(run_xml))
        group = [index]
        for next_index in range(index + 1, min(index + 6, len(matches))):
            next_xml = matches[next_index].group(0)
            if _run_has_highlight(next_xml):
                break
            combined += _normalize_for_match(_run_text(next_xml))
            group.append(next_index)
            if _word_pattern(word).search(combined):
                break
        else:
            continue

        if not _word_pattern(word).search(combined):
            continue

        pat = _word_pattern(word)
        m = pat.search(combined)
        start, end = m.start(), m.end()

        offset = 0
        rebuilt = []
        for group_index in group:
            current_xml = matches[group_index].group(0)
            current_text = _run_text(current_xml)
            current_len = len(_normalize_for_match(current_text))
            run_start = offset
            run_end = offset + current_len

            if end <= run_start or start >= run_end:
                rebuilt.append(current_xml)
            elif start <= run_start and end >= run_end:
                rpr = _extract_rpr(current_xml)
                rebuilt.append(_build_run(_rpr_with_highlight(rpr), current_text))
                highlighted += 1
            elif start >= run_start and end <= run_end:
                local_start = start - run_start
                local_end = end - run_start
                before = current_text[:local_start]
                mid = current_text[local_start:local_end]
                after = current_text[local_end:]
                rpr = _extract_rpr(current_xml)
                rpr_hl = _rpr_with_highlight(rpr)
                if before:
                    rebuilt.append(_build_run(rpr, before))
                rebuilt.append(_build_run(rpr_hl, mid))
                if after:
                    rebuilt.append(_build_run(rpr, after))
                highlighted += 1
            else:
                rebuilt.append(current_xml)

            offset = run_end

        replacement = "".join(rebuilt)
        parts.append(text[last_end : match.start()])
        parts.append(replacement)
        last_end = matches[group[-1]].end()
        skip_until = group[-1] + 1

    if not parts:
        return text, 0

    parts.append(text[last_end:])
    return "".join(parts), highlighted


def _highlight_word_pass(text, word):
    """Una pasada: resalta word en cada <a:r> y en palabras partidas entre runs."""
    highlighted = 0

    def process_run(match):
        nonlocal highlighted
        run_xml = match.group(0)
        new_xml, changed = _highlight_all_in_run(run_xml, word)
        if changed:
            highlighted += 1
        return new_xml

    new_text = _RUN_RE.sub(process_run, text)
    new_text, split_hits = _highlight_split_word_pass(new_text, word)
    highlighted += split_hits
    return new_text, highlighted


def _collect_error_words(errores):
    words = []
    seen = set()
    for error in errores or []:
        word = (error.get("palabra") or "").strip()
        if not word:
            continue
        key = _normalize_for_match(word).lower()
        if key in seen:
            continue
        seen.add(key)
        words.append(word)
    return words


def _highlight_slide_xml(xml_bytes, errores):
    try:
        original = xml_bytes.decode("utf-8")
    except UnicodeDecodeError:
        return xml_bytes, 0, False

    text = _normalize_slide_highlights(original)
    highlighted = 0

    for word in _collect_error_words(errores):
        while True:
            new_text, count = _highlight_word_pass(text, word)
            if count == 0:
                break
            text = new_text
            highlighted += count

    if text == original:
        return xml_bytes, highlighted, False

    return text.encode("utf-8"), highlighted, True


def highlight_pptx_errors(path, errores):
    """
    Post-procesa un .pptx guardado y resalta en amarillo las palabras con error.
    Devuelve cantidad de runs marcados.
    """
    path = Path(path)
    if not path.exists() or path.suffix.lower() != ".pptx" or not errores:
        return 0

    total = 0
    changed_any = False
    buffer = io.BytesIO()

    with zipfile.ZipFile(path, "r") as zin, zipfile.ZipFile(
        buffer, "w", compression=zipfile.ZIP_DEFLATED
    ) as zout:
        for item in zin.infolist():
            data = zin.read(item.filename)
            if item.filename.startswith("ppt/slides/slide") and item.filename.endswith(".xml"):
                data, count, changed = _highlight_slide_xml(data, errores)
                total += count
                changed_any = changed_any or changed
            zinfo = zipfile.ZipInfo(
                filename=item.filename,
                date_time=item.date_time,
            )
            zinfo.compress_type = item.compress_type
            zinfo.external_attr = item.external_attr
            zinfo.create_system = item.create_system
            zinfo.flag_bits = item.flag_bits
            zout.writestr(zinfo, data)

    if changed_any:
        path.write_bytes(buffer.getvalue())

    return total
