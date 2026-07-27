import json
import os
import re
import time
import unicodedata

import uno

UNO_URL = "uno:socket,host=127.0.0.1,port=2002;urp;StarOffice.ComponentContext"

WORD_RE = re.compile(r"[A-Za-zÝÉÝÓÚÜÑáéíóúüñ]+(?:'[A-Za-zÝÉÝÓÚÜÑáéíóúüñ]+)?")

ALLOWLIST = {
}

KNOWN_OK = {
}

IGNORE_EXTENSIONS = {
}


def make_prop(name, value):
    prop = uno.createUnoStruct("com.sun.star.beans.PropertyValue")
    prop.Name = name
    prop.Value = value
    return prop


def connect():
    local_ctx = uno.getComponentContext()
    resolver = local_ctx.ServiceManager.createInstanceWithContext(
        "com.sun.star.bridge.UnoUrlResolver",
        local_ctx
    )

    last_error = None
    for _ in range(30):
        try:
            return resolver.resolve(UNO_URL)
        except Exception as e:
            last_error = e
            time.sleep(1)

    raise RuntimeError(f"No se pudo conectar a LibreOffice UNO: {last_error}")


def make_locale(lang="es", country="GT"):
    loc = uno.createUnoStruct("com.sun.star.lang.Locale")
    loc.Language = lang
    loc.Country = country
    loc.Variant = ""
    return loc


def strip_accents(text):
    return "".join(
        c for c in unicodedata.normalize("NFD", text)
        if unicodedata.category(c) != "Mn"
    )


def normalize_text(text):
    return strip_accents(text.lower().strip())


def levenshtein(a, b):
    if a == b:
        return 0
    if len(a) == 0:
        return len(b)
    if len(b) == 0:
        return len(a)

    prev = list(range(len(b) + 1))
    for i, ca in enumerate(a, start=1):
        curr = [i]
        for j, cb in enumerate(b, start=1):
            cost = 0 if ca == cb else 1
            curr.append(min(
                curr[j - 1] + 1,
                prev[j] + 1,
                prev[j - 1] + cost
            ))
        prev = curr
    return prev[-1]


def is_only_tilde_error(original, suggestion):
    o = normalize_text(original)
    s = normalize_text(suggestion)
    return o == s and original.lower() != suggestion.lower()


def should_ignore(word):
    if not word:
        return True

    if word in ALLOWLIST:
        return True

    if word in KNOWN_OK:
        return True

    if word.lower() in IGNORE_EXTENSIONS:
        return True

    if word.isupper() and len(word) <= 8:
        return True

    if re.fullmatch(r"\d+([.,]\d+)*", word):
        return True

    if re.fullmatch(r"https?://\S+|www\.\S+|\S+@\S+", word.lower()):
        return True

    if re.fullmatch(r"[A-ZÝÉÝÓÚÜÑ]{2,}\d*", word):
        return True

    return False


def load_document(ctx, path):
    desktop = ctx.ServiceManager.createInstanceWithContext(
        "com.sun.star.frame.Desktop",
        ctx
    )

    abs_path = os.path.abspath(path)
    file_url = uno.systemPathToFileUrl(abs_path)

    props = (
        make_prop("Hidden", True),
        make_prop("ReadOnly", True),
    )

    return desktop.loadComponentFromURL(file_url, "_blank", 0, props)


def load_document_editable(ctx, path, file_ext=None):
    desktop = ctx.ServiceManager.createInstanceWithContext(
        "com.sun.star.frame.Desktop",
        ctx
    )

    abs_path = os.path.abspath(path)
    file_url = uno.systemPathToFileUrl(abs_path)

    ext = (file_ext or os.path.splitext(path)[1]).lower()
    props = [make_prop("Hidden", True)]

    if ext == ".pdf":
        props.append(make_prop("FilterName", "draw_pdf_import"))

    return desktop.loadComponentFromURL(file_url, "_blank", 0, tuple(props))


def _iter_impress_shapes(container):
    for i in range(container.getCount()):
        shape = container.getByIndex(i)
        yield shape
        try:
            if shape.supportsService("com.sun.star.drawing.GroupShape"):
                for child in _iter_impress_shapes(shape):
                    yield child
        except Exception:
            pass
        try:
            if hasattr(shape, "createEnumeration"):
                enum = shape.createEnumeration()
                while enum.hasMoreElements():
                    inner = enum.nextElement()
                    yield inner
                    try:
                        if inner.supportsService("com.sun.star.drawing.GroupShape"):
                            for child in _iter_impress_shapes(inner):
                                yield child
                    except Exception:
                        pass
        except Exception:
            pass


def impress_query_text_table(shape):
    """Obtiene el objeto tipo XTextTable desde un shape de Impress/Draw."""
    if shape is None:
        return None

    for svc in (
        "com.sun.star.text.TextTable",
        "com.sun.star.table.TableShape",
        "com.sun.star.drawing.TableShape",
    ):
        try:
            if shape.supportsService(svc):
                return shape
        except Exception:
            pass

    for method_name in ("getTable", "getTextTable"):
        try:
            method = getattr(shape, method_name, None)
            if callable(method):
                table = method()
                if table is not None:
                    return table
        except Exception:
            pass

    for prop_name in ("Table", "TextTable"):
        try:
            table = shape.getPropertyValue(prop_name)
            if table is not None:
                return table
        except Exception:
            pass

    if hasattr(shape, "getCellByPosition") and (
        hasattr(shape, "getRowCount")
        or hasattr(shape, "getRows")
        or hasattr(shape, "getCellNames")
    ):
        return shape

    return None


def impress_shape_is_table(shape):
    if impress_query_text_table(shape) is not None:
        return True

    try:
        table_type = uno.getConstantByName("com.sun.star.drawing.ShapeType.TABLE")
        if getattr(shape, "ShapeType", None) == table_type:
            return True
    except Exception:
        pass

    rows, cols = impress_table_dimensions(shape)
    return rows > 0 and cols > 0


def impress_table_dimensions(table):
    if table is None:
        return 0, 0

    try:
        rows = int(table.getRowCount())
        cols = int(table.getColumnCount())
        if rows > 0 and cols > 0:
            return rows, cols
    except Exception:
        pass

    try:
        rows = table.getRows().getCount()
        cols = table.getColumns().getCount()
        if rows > 0 and cols > 0:
            return int(rows), int(cols)
    except Exception:
        pass

    return 0, 0


def impress_object_text(obj):
    """Extrae texto de un objeto UNO (celda, shape, Text)."""
    if obj is None:
        return ""

    try:
        text_prop = obj.getPropertyValue("Text")
        if text_prop is not None:
            value = impress_object_text(text_prop)
            if value:
                return value
    except Exception:
        pass

    text_sources = [obj]
    try:
        text_sources.append(obj.getText())
    except Exception:
        pass

    for src in text_sources:
        if src is None:
            continue

        try:
            if hasattr(src, "getString"):
                value = src.getString()
                if value and str(value).strip():
                    return str(value).strip()
        except Exception:
            pass

        try:
            cursor = src.createTextCursor()
            if cursor is not None:
                value = cursor.getString()
                if value and value.strip():
                    return value.strip()
        except Exception:
            pass

        try:
            enum = src.createEnumeration()
            parts = []
            while enum.hasMoreElements():
                portion = enum.nextElement()
                try:
                    if hasattr(portion, "getString"):
                        parts.append(portion.getString() or "")
                except Exception:
                    pass
            joined = "".join(parts).strip()
            if joined:
                return joined
        except Exception:
            pass

    return ""


def impress_table_cell_text(cell):
    """Texto visible en una celda de tabla Impress/Draw."""
    return impress_object_text(cell)


def impress_table_iterate_cells(table):
    """Itera celdas de una tabla Impress con varios metodos de acceso."""
    if table is None:
        return

    try:
        names = table.getCellNames()
        if names:
            for name in names:
                try:
                    yield table.getCellByName(name)
                except Exception:
                    pass
            return
    except Exception:
        pass

    rows, cols = impress_table_dimensions(table)
    for r in range(rows):
        for c in range(cols):
            cell = None
            for col, row in ((c, r), (r, c)):
                try:
                    cell = table.getCellByPosition(col, row)
                    break
                except Exception:
                    continue
            if cell is not None:
                yield cell


def impress_table_shape_texts(shape):
    texts = []
    table = impress_query_text_table(shape)

    if table is not None:
        for cell in impress_table_iterate_cells(table):
            value = impress_table_cell_text(cell)
            if value:
                texts.append(value)

    for obj in (table, shape):
        if obj is None:
            continue
        agg = impress_object_text(obj)
        if agg:
            texts.append(agg)

    seen = set()
    unique = []
    for text in texts:
        if text not in seen:
            seen.add(text)
            unique.append(text)
    return unique


def _extract_impress_shape_text(shape):
    chunks = impress_table_shape_texts(shape)
    if chunks:
        return chunks

    try:
        value = shape.getString()
        if value:
            chunks.append(value)
    except Exception:
        try:
            value = shape.getText().getString()
            if value:
                chunks.append(value)
        except Exception:
            pass

    return chunks


def extract_impress_shape_text_chunks(shape):
    """Fragmentos de texto de un shape Impress (incluye tablas)."""
    return _extract_impress_shape_text(shape)


def extract_text(doc):
    chunks = []

    try:
        text = doc.getText().getString()
        if text:
            chunks.append(text)
    except Exception:
        pass

    try:
        if doc.supportsService("com.sun.star.sheet.SpreadsheetDocument"):
            sheets = doc.getSheets()
            for i in range(sheets.getCount()):
                sheet = sheets.getByIndex(i)
                cursor = sheet.createCursor()
                cursor.gotoStartOfUsedArea(False)
                cursor.gotoEndOfUsedArea(True)
                addr = cursor.getRangeAddress()

                for row in range(addr.StartRow, addr.EndRow + 1):
                    for col in range(addr.StartColumn, addr.EndColumn + 1):
                        cell = sheet.getCellByPosition(col, row)
                        value = cell.getString()
                        if value:
                            chunks.append(value)
    except Exception:
        pass

    try:
        pages = doc.getDrawPages()
        for p in range(pages.getCount()):
            page = pages.getByIndex(p)
            for shape in _iter_impress_shapes(page):
                chunks.extend(_extract_impress_shape_text(shape))
    except Exception:
        pass

    return "\n".join(chunks)


def detect_lo_document_family(doc):
    """Clasifica el documento abierto en LibreOffice."""
    if doc.supportsService("com.sun.star.sheet.SpreadsheetDocument"):
        return "calc"
    if doc.supportsService("com.sun.star.presentation.PresentationDocument"):
        return "impress"
    if doc.supportsService("com.sun.star.drawing.DrawingDocument"):
        return "draw"
    if doc.supportsService("com.sun.star.text.TextDocument"):
        return "writer"
    return "unknown"


def get_suggestions(spell, word, locale):
    try:
        alt = spell.spell(word, locale, ())
        return list(alt.getAlternatives()) if alt else []
    except Exception:
        return []


def classify_word(word, suggestions_es):
    for sug in suggestions_es:
        if is_only_tilde_error(word, sug):
            return "tilde"

    norm_word = normalize_text(word)
    best_es = None

    for sug in suggestions_es:
        dist = levenshtein(norm_word, normalize_text(sug))
        if best_es is None or dist < best_es:
            best_es = dist

    if best_es is not None and best_es <= 2:
        return "ortografia"

    return None


def check_word(word, spell, locale_es):
    if should_ignore(word):
        return None
    
    if len(word) < 3:
        return None

    try:
        valid_es = spell.isValid(word, locale_es, ())
    except Exception:
        valid_es = False

    if valid_es:
        return None

    suggestions_es = get_suggestions(spell, word, locale_es)

    error_type = classify_word(word, suggestions_es)
    if error_type not in ("tilde", "ortografia"):
        return None

    suggestions = []
    seen = set()
    for s in suggestions_es:
        key = s.lower()
        if key not in seen:
            seen.add(key)
            suggestions.append(s)

    return {
        "palabra": word,
        "tipo": error_type,
        "sugerencias": suggestions[:5],
    }


def find_unique_errors(text, spell, locale_es):
    errors = []
    seen = set()

    for word in WORD_RE.findall(text):
        key = word.lower()
        if key in seen:
            continue
        seen.add(key)

        result = check_word(word, spell, locale_es)
        if result is not None:
            errors.append(result)

    return errors


def analyze_file(path):
    ctx = connect()
    smgr = ctx.ServiceManager

    spell = smgr.createInstanceWithContext(
        "com.sun.star.linguistic2.SpellChecker",
        ctx
    )

    locale_es = make_locale("es", "GT")

    doc = None
    try:
        doc = load_document(ctx, path)
        text = extract_text(doc)

        if not text.strip():
            return {
                "ok": False,
                "archivo": os.path.basename(path),
                "error": "No se pudo extraer texto del archivo"
            }

        errors = find_unique_errors(text, spell, locale_es)

        return {
            "ok": True,
            "archivo": os.path.basename(path),
            "tiene_errores": len(errors) > 0,
            "total_errores": len(errors),
            "errores": errors,
        }

    finally:
        if doc is not None:
            try:
                doc.close(True)
            except Exception:
                try:
                    doc.dispose()
                except Exception:
                    pass
