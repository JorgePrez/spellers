from flask import Flask, request, jsonify
from werkzeug.utils import secure_filename
from functools import wraps
import secrets
import os
import uuid

from spellcheck_core import analyze_file
from spellcheck_fix import correct_word_document

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
        "message": "service up"
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


if __name__ == "__main__":
    app.run(
        host="0.0.0.0",
        port=5000,
        debug=False,
        use_reloader=False
    )
