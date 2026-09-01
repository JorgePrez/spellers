from flask import Flask, request, jsonify
from werkzeug.utils import secure_filename
from functools import wraps
import secrets
import os
import uuid

from spellcheck_core import analyze_file
from spellcheck_fix import correct_word_document
from spellcheck_mark import mark_document
from spellcheck_fast_detect import detect_fast
from spellcheck_hunspell import detect_fast_hunspell, hunspell_status
from spellcheck_mark_hs import mark_document_hs
from spellcheck_profile import mark_document_profiled

app = Flask(__name__)

BASE_DIR = os.path.dirname(__file__)
UPLOAD_FOLDER = os.path.join(BASE_DIR, "uploads")
TEMP_FOLDER = os.path.join(BASE_DIR, "temp")

ALLOWED_EXTENSIONS = {
    ".doc",
    ".docx",
    ".xls",
    ".xlsx",
    ".ppt",
    ".pptx",
    ".pdf",
}

WORD_EXTENSIONS = {
    ".doc",
    ".docx",
}

os.makedirs(UPLOAD_FOLDER, exist_ok=True)
os.makedirs(TEMP_FOLDER, exist_ok=True)

# Token configurado desde systemd
EXPECTED_TOKEN = os.environ.get("SPELLCHECK_BEARER_TOKEN", "")


def require_bearer_token(view_func):
    @wraps(view_func)
    def wrapper(*args, **kwargs):

        if not EXPECTED_TOKEN:
            return jsonify({
                "ok": False,
                "error": "Server authentication not configured"
            }), 500

        auth = request.headers.get("Authorization", "")

        if not auth.startswith("Bearer "):
            return jsonify({
                "ok": False,
                "error": "Missing Bearer token"
            }), 401

        token = auth[7:].strip()

        if not secrets.compare_digest(token, EXPECTED_TOKEN):
            return jsonify({
                "ok": False,
                "error": "Invalid Bearer token"
            }), 401

        return view_func(*args, **kwargs)

    return wrapper


def allowed_file(filename):
    _, ext = os.path.splitext(filename.lower())
    return ext in ALLOWED_EXTENSIONS


def allowed_word_file(filename):
    _, ext = os.path.splitext(filename.lower())
    return ext in WORD_EXTENSIONS


@app.route("/health", methods=["GET"])
def health():
    return jsonify({
        "ok": True,
        "message": "service up",
        "endpoints": [
            "/spellcheck",
            "/spellcheck/fix-word",
            "/spellcheck/mark",
            "/spellcheck/mark-llm",
            "/spellcheck/mark-llm-detect",
            "/spellcheck/mark-detect",
            "/spellcheck/fast-detect",
            "/spellcheck/fast-detect-hs",
            "/spellcheck/mark-llm-profile",
            "/spellcheck/mark-llm-profile-nosug",
            "/spellcheck/mark-llm-hs",
        ],
        "hunspell": hunspell_status(),
    })


@app.route("/spellcheck", methods=["POST"])
@require_bearer_token
def spellcheck():
    syllabus_uac_cronograma = request.form.get("syllabus_uac_cronograma", "").strip()

    if not syllabus_uac_cronograma:
        return jsonify({
            "ok": False,
            "error": "Se requiere el campo syllabus_uac_cronograma"
        }), 400

    if "file" not in request.files:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "No se recibió archivo"
        }), 400

    file = request.files["file"]

    if not file.filename:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Nombre de archivo vacío"
        }), 400

    if not allowed_file(file.filename):
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Formato no permitido",
            "allowed": sorted(list(ALLOWED_EXTENSIONS))
        }), 400

    original_name = secure_filename(file.filename)
    unique_name = f"{uuid.uuid4().hex}_{original_name}"
    temp_path = os.path.join(UPLOAD_FOLDER, unique_name)

    try:
        file.save(temp_path)
        result = analyze_file(temp_path)

        response = {
            "syllabus_uac_cronograma": syllabus_uac_cronograma
        }
        response.update(result)

        return jsonify(response), 200

    except Exception as e:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Error procesando archivo",
            "detail": str(e)
        }), 500

    finally:
        try:
            if os.path.exists(temp_path):
                os.remove(temp_path)
        except Exception:
            pass


@app.route("/spellcheck/fix-word", methods=["POST"])
@require_bearer_token
def spellcheck_fix_word():
    syllabus_uac_cronograma = request.form.get("syllabus_uac_cronograma", "").strip()

    if not syllabus_uac_cronograma:
        return jsonify({
            "ok": False,
            "error": "Se requiere el campo syllabus_uac_cronograma"
        }), 400

    if "file" not in request.files:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "No se recibió archivo"
        }), 400

    file = request.files["file"]

    if not file.filename:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Nombre de archivo vacío"
        }), 400

    if not allowed_word_file(file.filename):
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Este endpoint solo acepta Word (.doc, .docx)",
            "allowed": sorted(list(WORD_EXTENSIONS))
        }), 400

    original_name = secure_filename(file.filename)
    unique_name = f"{uuid.uuid4().hex}_{original_name}"
    temp_input_path = os.path.join(UPLOAD_FOLDER, unique_name)

    try:
        file.save(temp_input_path)

        result = correct_word_document(
            temp_input_path,
            output_dir=TEMP_FOLDER
        )

        response = {
            "syllabus_uac_cronograma": syllabus_uac_cronograma
        }
        response.update(result)

        return jsonify(response), 200

    except Exception as e:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Error corrigiendo archivo",
            "detail": str(e)
        }), 500

    finally:
        try:
            if os.path.exists(temp_input_path):
                os.remove(temp_input_path)
        except Exception:
            pass


@app.route("/spellcheck/mark", methods=["POST"])
@require_bearer_token
def spellcheck_mark():
    syllabus_uac_cronograma = request.form.get("syllabus_uac_cronograma", "").strip()
    s3_bucket = request.form.get("s3_bucket", "").strip() or os.environ.get(
        "SPELLCHECK_OUTPUT_BUCKET", "syllabus-compras"
    )
    s3_source_key = request.form.get("s3_source_key", "").strip()

    if not syllabus_uac_cronograma:
        return jsonify({
            "ok": False,
            "error": "Se requiere el campo syllabus_uac_cronograma"
        }), 400

    if not s3_source_key:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Se requiere el campo s3_source_key"
        }), 400

    if "file" not in request.files:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "No se recibio archivo"
        }), 400

    file = request.files["file"]

    if not file.filename:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Nombre de archivo vacio"
        }), 400

    if not allowed_file(file.filename):
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Formato no permitido",
            "allowed": sorted(list(ALLOWED_EXTENSIONS))
        }), 400

    original_name = secure_filename(file.filename)
    unique_name = f"{uuid.uuid4().hex}_{original_name}"
    temp_input_path = os.path.join(UPLOAD_FOLDER, unique_name)
    correction_local_path = None

    try:
        file.save(temp_input_path)

        result = mark_document(
            temp_input_path,
            output_dir=TEMP_FOLDER,
            s3_bucket=s3_bucket,
            s3_source_key=s3_source_key,
            metadata={
                "syllabus_uac_cronograma": syllabus_uac_cronograma,
            },
            llm_second_layer=False,
        )

        if result.get("archivo_rev"):
            correction_local_path = os.path.join(
                TEMP_FOLDER,
                result["archivo_rev"],
            )

        response = {
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
        }
        response.update(result)

        if not result.get("ok"):
            return jsonify(response), 422

        return jsonify(response), 200

    except Exception as e:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Error generando documento de correccion",
            "detail": str(e)
        }), 500

    finally:
        try:
            if os.path.exists(temp_input_path):
                os.remove(temp_input_path)
        except Exception:
            pass
        try:
            if correction_local_path and os.path.exists(correction_local_path):
                os.remove(correction_local_path)
        except Exception:
            pass


@app.route("/spellcheck/mark-llm", methods=["POST"])
@require_bearer_token
def spellcheck_mark_llm():
    """
    Igual que /spellcheck/mark, pero con segunda capa Bedrock (Haiku):
    LibreOffice detecta candidatos -> LLM confirma si es falta ortografica real
    -> solo se marcan los confirmados.
    """
    syllabus_uac_cronograma = request.form.get("syllabus_uac_cronograma", "").strip()
    s3_bucket = request.form.get("s3_bucket", "").strip() or os.environ.get(
        "SPELLCHECK_OUTPUT_BUCKET", "syllabus-compras"
    )
    s3_source_key = request.form.get("s3_source_key", "").strip()

    if not syllabus_uac_cronograma:
        return jsonify({
            "ok": False,
            "error": "Se requiere el campo syllabus_uac_cronograma"
        }), 400

    if not s3_source_key:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Se requiere el campo s3_source_key"
        }), 400

    if "file" not in request.files:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "No se recibio archivo"
        }), 400

    file = request.files["file"]

    if not file.filename:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Nombre de archivo vacio"
        }), 400

    if not allowed_file(file.filename):
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Formato no permitido",
            "allowed": sorted(list(ALLOWED_EXTENSIONS))
        }), 400

    original_name = secure_filename(file.filename)
    unique_name = f"{uuid.uuid4().hex}_{original_name}"
    temp_input_path = os.path.join(UPLOAD_FOLDER, unique_name)
    correction_local_path = None

    try:
        file.save(temp_input_path)

        result = mark_document(
            temp_input_path,
            output_dir=TEMP_FOLDER,
            s3_bucket=s3_bucket,
            s3_source_key=s3_source_key,
            metadata={
                "syllabus_uac_cronograma": syllabus_uac_cronograma,
            },
            llm_second_layer=True,
        )

        if result.get("archivo_rev"):
            correction_local_path = os.path.join(
                TEMP_FOLDER,
                result["archivo_rev"],
            )

        response = {
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
        }
        response.update(result)

        if not result.get("ok"):
            return jsonify(response), 422

        return jsonify(response), 200

    except Exception as e:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Error generando documento de correccion (capa LLM)",
            "detail": str(e)
        }), 500

    finally:
        try:
            if os.path.exists(temp_input_path):
                os.remove(temp_input_path)
        except Exception:
            pass
        try:
            if correction_local_path and os.path.exists(correction_local_path):
                os.remove(correction_local_path)
        except Exception:
            pass


@app.route("/spellcheck/mark-llm-detect", methods=["POST"])
@require_bearer_token
def spellcheck_mark_llm_detect():
    """
    Igual que /spellcheck/mark-llm (LO + Haiku), pero NO marca el documento
    ni sube rev_*. Solo detecta y devuelve errores en JSON (para pruebas).
    Mismos campos multipart que mark-llm.
    """
    return _spellcheck_detect_response(llm_second_layer=True)


@app.route("/spellcheck/mark-detect", methods=["POST"])
@require_bearer_token
def spellcheck_mark_detect():
    """
    Solo LibreOffice: abre, extrae texto y spellcheck. Sin LLM, sin marcar rev_*.
    Mismos campos multipart que mark-llm (para comparar tiempos).
    """
    return _spellcheck_detect_response(llm_second_layer=False)


def _spellcheck_detect_response(*, llm_second_layer):
    syllabus_uac_cronograma = request.form.get("syllabus_uac_cronograma", "").strip()
    s3_bucket = request.form.get("s3_bucket", "").strip() or os.environ.get(
        "SPELLCHECK_OUTPUT_BUCKET", "syllabus-compras"
    )
    s3_source_key = request.form.get("s3_source_key", "").strip()

    if not syllabus_uac_cronograma:
        return jsonify({
            "ok": False,
            "error": "Se requiere el campo syllabus_uac_cronograma"
        }), 400

    if not s3_source_key:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Se requiere el campo s3_source_key"
        }), 400

    if "file" not in request.files:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "No se recibio archivo"
        }), 400

    file = request.files["file"]

    if not file.filename:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Nombre de archivo vacio"
        }), 400

    if not allowed_file(file.filename):
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Formato no permitido",
            "allowed": sorted(list(ALLOWED_EXTENSIONS))
        }), 400

    original_name = secure_filename(file.filename)
    unique_name = f"{uuid.uuid4().hex}_{original_name}"
    temp_input_path = os.path.join(UPLOAD_FOLDER, unique_name)

    try:
        file.save(temp_input_path)

        result = mark_document(
            temp_input_path,
            output_dir=TEMP_FOLDER,
            s3_bucket=s3_bucket,
            s3_source_key=s3_source_key,
            metadata={
                "syllabus_uac_cronograma": syllabus_uac_cronograma,
            },
            llm_second_layer=llm_second_layer,
            annotate=False,
        )

        response = {
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
        }
        response.update(result)

        if not result.get("ok"):
            return jsonify(response), 422

        return jsonify(response), 200

    except Exception as e:
        capa = "LO+LLM" if llm_second_layer else "solo LO"
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": f"Error detectando ortografia ({capa}, sin marcar)",
            "detail": str(e)
        }), 500

    finally:
        try:
            if os.path.exists(temp_input_path):
                os.remove(temp_input_path)
        except Exception:
            pass


@app.route("/spellcheck/fast-detect", methods=["POST"])
@require_bearer_token
def spellcheck_fast_detect():
    """
    Deteccion rapida para pruebas de rendimiento:
    - Extrae texto SIN abrir el archivo en Calc/Writer (openpyxl / OOXML / pymupdf).
    - Usa el SpellChecker UNO (mismos diccionarios es-GT + UA).
    - No marca ni sube rev_*.
    - Form opcional: usar_llm=1 para filtrar con Haiku.

    Mismos campos base que mark-llm (file + syllabus_uac_cronograma + s3_*).
    Formatos: .xlsx .docx .pptx .pdf  (no .xls/.doc/.ppt legacy).
    """
    syllabus_uac_cronograma = request.form.get("syllabus_uac_cronograma", "").strip()
    # s3_* se aceptan para compatibilidad con n8n; no se usan en este endpoint
    _ = request.form.get("s3_bucket", "").strip()
    _ = request.form.get("s3_source_key", "").strip()
    usar_llm_raw = (request.form.get("usar_llm") or "").strip().lower()
    usar_llm = usar_llm_raw in {"1", "true", "yes", "si", "sí"}

    if not syllabus_uac_cronograma:
        return jsonify({
            "ok": False,
            "error": "Se requiere el campo syllabus_uac_cronograma"
        }), 400

    if "file" not in request.files:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "No se recibio archivo"
        }), 400

    file = request.files["file"]

    if not file.filename:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Nombre de archivo vacio"
        }), 400

    _, ext = os.path.splitext(file.filename.lower())
    if ext not in {".xlsx", ".docx", ".pptx", ".pdf"}:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "fast-detect solo soporta .xlsx .docx .pptx .pdf",
            "allowed": [".xlsx", ".docx", ".pptx", ".pdf"],
        }), 400

    original_name = secure_filename(file.filename)
    unique_name = f"{uuid.uuid4().hex}_{original_name}"
    temp_input_path = os.path.join(UPLOAD_FOLDER, unique_name)

    try:
        file.save(temp_input_path)
        result = detect_fast(temp_input_path, llm_second_layer=usar_llm)
        response = {
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
        }
        response.update(result)
        if not result.get("ok"):
            return jsonify(response), 422
        return jsonify(response), 200

    except Exception as e:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Error en fast-detect",
            "detail": str(e),
        }), 500

    finally:
        try:
            if os.path.exists(temp_input_path):
                os.remove(temp_input_path)
        except Exception:
            pass


@app.route("/spellcheck/fast-detect-hs", methods=["POST"])
@require_bearer_token
def spellcheck_fast_detect_hs():
    """
    Prueba Hunspell nativo (spylls) + extraccion openpyxl/OOXML.
    NO usa SpellChecker UNO. NO marca ni sube rev_*.
    Form: usar_llm=1 (opcional). Sugerencias OFF por defecto;
    con_sugerencias=1 para activarlas (lento con spylls).
    """
    syllabus_uac_cronograma = request.form.get("syllabus_uac_cronograma", "").strip()
    _ = request.form.get("s3_bucket", "").strip()
    _ = request.form.get("s3_source_key", "").strip()
    usar_llm_raw = (request.form.get("usar_llm") or "").strip().lower()
    usar_llm = usar_llm_raw in {"1", "true", "yes", "si", "sí"}
    # Por defecto SIN sugerencias (spylls.suggest es muy lento).
    # Para pedirlas: con_sugerencias=1
    con_sug_raw = (request.form.get("con_sugerencias") or "").strip().lower()
    con_sugerencias = con_sug_raw in {"1", "true", "yes", "si", "sí"}
    sin_sug_raw = (request.form.get("sin_sugerencias") or "").strip().lower()
    if sin_sug_raw in {"1", "true", "yes", "si", "sí"}:
        con_sugerencias = False

    if not syllabus_uac_cronograma:
        return jsonify({
            "ok": False,
            "error": "Se requiere el campo syllabus_uac_cronograma"
        }), 400

    if "file" not in request.files:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "No se recibio archivo"
        }), 400

    file = request.files["file"]
    if not file.filename:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Nombre de archivo vacio"
        }), 400

    _, ext = os.path.splitext(file.filename.lower())
    if ext not in {".xlsx", ".docx", ".pptx", ".pdf"}:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "fast-detect-hs solo soporta .xlsx .docx .pptx .pdf",
            "allowed": [".xlsx", ".docx", ".pptx", ".pdf"],
        }), 400

    original_name = secure_filename(file.filename)
    unique_name = f"{uuid.uuid4().hex}_{original_name}"
    temp_input_path = os.path.join(UPLOAD_FOLDER, unique_name)

    try:
        file.save(temp_input_path)
        result = detect_fast_hunspell(
            temp_input_path,
            llm_second_layer=usar_llm,
            with_suggestions=con_sugerencias,
        )
        response = {"syllabus_uac_cronograma": syllabus_uac_cronograma}
        response.update(result)
        if not result.get("ok"):
            return jsonify(response), 422
        return jsonify(response), 200

    except Exception as e:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Error en fast-detect-hs",
            "detail": str(e),
        }), 500

    finally:
        try:
            if os.path.exists(temp_input_path):
                os.remove(temp_input_path)
        except Exception:
            pass


@app.route("/spellcheck/mark-llm-hs", methods=["POST"])
@require_bearer_token
def spellcheck_mark_llm_hs():
    """
    Deteccion Hunspell nativo (spylls, sin sugerencias) + LLM;
    LibreOffice SOLO para marcar errores y subir rev_*.
    Mismos campos multipart que mark-llm.
    """
    syllabus_uac_cronograma = request.form.get("syllabus_uac_cronograma", "").strip()
    s3_bucket = request.form.get("s3_bucket", "").strip() or os.environ.get(
        "SPELLCHECK_OUTPUT_BUCKET", "syllabus-compras"
    )
    s3_source_key = request.form.get("s3_source_key", "").strip()

    if not syllabus_uac_cronograma:
        return jsonify({
            "ok": False,
            "error": "Se requiere el campo syllabus_uac_cronograma"
        }), 400

    if not s3_source_key:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Se requiere el campo s3_source_key"
        }), 400

    if "file" not in request.files:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "No se recibio archivo"
        }), 400

    file = request.files["file"]

    if not file.filename:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Nombre de archivo vacio"
        }), 400

    if not allowed_file(file.filename):
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Formato no permitido",
            "allowed": sorted(list(ALLOWED_EXTENSIONS))
        }), 400

    original_name = secure_filename(file.filename)
    unique_name = f"{uuid.uuid4().hex}_{original_name}"
    temp_input_path = os.path.join(UPLOAD_FOLDER, unique_name)
    correction_local_path = None

    try:
        file.save(temp_input_path)
        result = mark_document_hs(
            temp_input_path,
            output_dir=TEMP_FOLDER,
            s3_bucket=s3_bucket,
            s3_source_key=s3_source_key,
            metadata={
                "syllabus_uac_cronograma": syllabus_uac_cronograma,
            },
            llm_second_layer=True,
        )

        if result.get("archivo_rev"):
            correction_local_path = os.path.join(
                TEMP_FOLDER,
                result["archivo_rev"],
            )

        response = {
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
        }
        response.update(result)

        if not result.get("ok"):
            return jsonify(response), 422
        return jsonify(response), 200

    except Exception as e:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Error en mark-llm-hs",
            "detail": str(e),
        }), 500

    finally:
        try:
            if os.path.exists(temp_input_path):
                os.remove(temp_input_path)
        except Exception:
            pass
        try:
            if correction_local_path and os.path.exists(correction_local_path):
                os.remove(correction_local_path)
        except Exception:
            pass


@app.route("/spellcheck/mark-llm-profile", methods=["POST"])
@require_bearer_token
def spellcheck_mark_llm_profile():
    """
    Flujo completo como mark-llm (LO + LLM + marcar + S3) con tiempos por fase.
    Mismos campos multipart. Respuesta incluye timings_ms.
    No aplica trampas demo CRONOGRAMABH.
    """
    syllabus_uac_cronograma = request.form.get("syllabus_uac_cronograma", "").strip()
    s3_bucket = request.form.get("s3_bucket", "").strip() or os.environ.get(
        "SPELLCHECK_OUTPUT_BUCKET", "syllabus-compras"
    )
    s3_source_key = request.form.get("s3_source_key", "").strip()

    if not syllabus_uac_cronograma:
        return jsonify({
            "ok": False,
            "error": "Se requiere el campo syllabus_uac_cronograma"
        }), 400

    if not s3_source_key:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Se requiere el campo s3_source_key"
        }), 400

    if "file" not in request.files:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "No se recibio archivo"
        }), 400

    file = request.files["file"]

    if not file.filename:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Nombre de archivo vacio"
        }), 400

    if not allowed_file(file.filename):
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Formato no permitido",
            "allowed": sorted(list(ALLOWED_EXTENSIONS))
        }), 400

    original_name = secure_filename(file.filename)
    unique_name = f"{uuid.uuid4().hex}_{original_name}"
    temp_input_path = os.path.join(UPLOAD_FOLDER, unique_name)
    correction_local_path = None

    try:
        file.save(temp_input_path)
        result = mark_document_profiled(
            temp_input_path,
            output_dir=TEMP_FOLDER,
            s3_bucket=s3_bucket,
            s3_source_key=s3_source_key,
            metadata={
                "syllabus_uac_cronograma": syllabus_uac_cronograma,
            },
            llm_second_layer=True,
            also_time_native_extract=True,
        )

        if result.get("archivo_rev"):
            correction_local_path = os.path.join(
                TEMP_FOLDER,
                result["archivo_rev"],
            )

        response = {
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
        }
        response.update(result)

        if not result.get("ok"):
            return jsonify(response), 422
        return jsonify(response), 200

    except Exception as e:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Error en mark-llm-profile",
            "detail": str(e),
        }), 500

    finally:
        try:
            if os.path.exists(temp_input_path):
                os.remove(temp_input_path)
        except Exception:
            pass
        try:
            if correction_local_path and os.path.exists(correction_local_path):
                os.remove(correction_local_path)
        except Exception:
            pass


@app.route("/spellcheck/mark-llm-profile-nosug", methods=["POST"])
@require_bearer_token
def spellcheck_mark_llm_profile_nosug():
    """
    Igual que mark-llm-profile (LO + LLM + marcar + S3 + timings),
    pero SIN pedir sugerencias al SpellChecker UNO (solo isValid).

    Sirve para medir si ms_spell ~194s venia de suggest() o de isValid().
    Mismos campos multipart que mark-llm-profile.
    """
    syllabus_uac_cronograma = request.form.get("syllabus_uac_cronograma", "").strip()
    s3_bucket = request.form.get("s3_bucket", "").strip() or os.environ.get(
        "SPELLCHECK_OUTPUT_BUCKET", "syllabus-compras"
    )
    s3_source_key = request.form.get("s3_source_key", "").strip()

    if not syllabus_uac_cronograma:
        return jsonify({
            "ok": False,
            "error": "Se requiere el campo syllabus_uac_cronograma"
        }), 400

    if not s3_source_key:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Se requiere el campo s3_source_key"
        }), 400

    if "file" not in request.files:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "No se recibio archivo"
        }), 400

    file = request.files["file"]

    if not file.filename:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Nombre de archivo vacio"
        }), 400

    if not allowed_file(file.filename):
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Formato no permitido",
            "allowed": sorted(list(ALLOWED_EXTENSIONS))
        }), 400

    original_name = secure_filename(file.filename)
    unique_name = f"{uuid.uuid4().hex}_{original_name}"
    temp_input_path = os.path.join(UPLOAD_FOLDER, unique_name)
    correction_local_path = None

    try:
        file.save(temp_input_path)
        result = mark_document_profiled(
            temp_input_path,
            output_dir=TEMP_FOLDER,
            s3_bucket=s3_bucket,
            s3_source_key=s3_source_key,
            metadata={
                "syllabus_uac_cronograma": syllabus_uac_cronograma,
            },
            llm_second_layer=True,
            also_time_native_extract=True,
            use_suggestion_filter=False,
        )

        if result.get("archivo_rev"):
            correction_local_path = os.path.join(
                TEMP_FOLDER,
                result["archivo_rev"],
            )

        response = {
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
        }
        response.update(result)

        if not result.get("ok"):
            return jsonify(response), 422
        return jsonify(response), 200

    except Exception as e:
        return jsonify({
            "ok": False,
            "syllabus_uac_cronograma": syllabus_uac_cronograma,
            "error": "Error en mark-llm-profile-nosug",
            "detail": str(e),
        }), 500

    finally:
        try:
            if os.path.exists(temp_input_path):
                os.remove(temp_input_path)
        except Exception:
            pass
        try:
            if correction_local_path and os.path.exists(correction_local_path):
                os.remove(correction_local_path)
        except Exception:
            pass


if __name__ == "__main__":
    app.run(
        host="0.0.0.0",
        port=5000,
        debug=False,
        use_reloader=False
    )
