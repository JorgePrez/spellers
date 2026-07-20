import json
import os
import re
import time
import unicodedata

import uno

UNO_URL = "uno:socket,host=127.0.0.1,port=2002;urp;StarOffice.ComponentContext"

WORD_RE = re.compile(r"[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:'[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)?")

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

    if re.fullmatch(r"[A-ZÁÉÍÓÚÜÑ]{2,}\d*", word):
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
            for s in range(page.getCount()):
                shape = page.getByIndex(s)
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
    except Exception:
        pass

    return "\n".join(chunks)


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
