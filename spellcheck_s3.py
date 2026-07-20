import json
import os
from pathlib import Path

DEFAULT_BUCKET = os.environ.get("SPELLCHECK_OUTPUT_BUCKET", "syllabus-compras")


def derive_correction_keys(s3_source_key):
    """
    Deriva las keys S3 del documento rev y del JSON de errores
    a partir de la key del archivo original.

    documento_1782331605_Foo.pptx -> rev_1782331605_Foo.pptx
    JSON -> rev_1782331605_Foo_errores.json
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
        rev_name = basename.replace("documento", "rev", 1)
    else:
        rev_name = f"rev_{basename}"

    rev_stem = Path(rev_name).stem
    json_name = f"{rev_stem}_errores.json"

    if folder:
        rev_key = f"{folder}/{rev_name}"
        json_key = f"{folder}/{json_name}"
    else:
        rev_key = rev_name
        json_key = json_name

    return {
        "correction_key": rev_key,
        "json_key": json_key,
        "correction_basename": rev_name,
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
