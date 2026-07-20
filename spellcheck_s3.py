import json
import os
from pathlib import Path

DEFAULT_BUCKET = os.environ.get("SPELLCHECK_OUTPUT_BUCKET", "syllabus-compras")


def derive_correction_keys(s3_source_key):
    """
    Deriva las keys S3 del documento de correccion y del JSON de errores
    a partir de la key del archivo original.

    documento_1782331605_Foo.pptx -> correccion_1782331605_Foo.pptx
    JSON -> correccion_1782331605_Foo_errores.json
    """
    key = (s3_source_key or "").strip().lstrip("/")
    if not key:
        raise ValueError("s3_source_key vacio")

    folder = str(Path(key).parent).replace("\\", "/")
    if folder == ".":
        folder = ""

    basename = Path(key).name
    stem = Path(key).stem
    suffix = Path(key).suffix

    if "documento" in basename:
        correction_name = basename.replace("documento", "correccion", 1)
    else:
        correction_name = f"correccion_{basename}"

    correction_stem = Path(correction_name).stem
    json_name = f"{correction_stem}_errores.json"

    if folder:
        correction_key = f"{folder}/{correction_name}"
        json_key = f"{folder}/{json_name}"
    else:
        correction_key = correction_name
        json_key = json_name

    return {
        "correction_key": correction_key,
        "json_key": json_key,
        "correction_basename": correction_name,
        "original_basename": basename,
    }


def upload_file_to_s3(local_path, bucket, key):
    import boto3

    s3 = boto3.client("s3")
    s3.upload_file(str(local_path), bucket, key)

    return {
        "s3_bucket": bucket,
        "s3_key": key,
        "s3_uri": f"s3://{bucket}/{key}",
    }


def upload_json_to_s3(data_dict, bucket, key):
    import boto3

    body = json.dumps(data_dict, ensure_ascii=False, indent=2).encode("utf-8")
    s3 = boto3.client("s3")
    s3.put_object(
        Bucket=bucket,
        Key=key,
        Body=body,
        ContentType="application/json; charset=utf-8",
    )

    return {
        "s3_bucket": bucket,
        "s3_key": key,
        "s3_uri": f"s3://{bucket}/{key}",
    }
