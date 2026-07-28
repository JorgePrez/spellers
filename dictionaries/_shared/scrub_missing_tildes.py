# -*- coding: utf-8 -*-
"""Restore ua_*_GT.dic from lexicon_full and drop only missing-tilde Spanish errors.

Does NOT use looks_english() — Spanish has many legitimate -able/-ible forms.

Usage (from dictionaries/):
  python _shared/scrub_missing_tildes.py
"""
from __future__ import annotations

import importlib
import shutil
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SHARED_ORTHO = Path(__file__).resolve().parent / "ortho_priority.py"

sys.path.insert(0, str(Path(__file__).resolve().parent))
from ortho_priority import (  # noqa: E402
    LETTER,
    has_diacritic,
    hunspell_lemma,
)


def sync_ortho_copies() -> None:
    for pkg in sorted(ROOT.glob("dict-ua-*")):
        if pkg.name == "dict-ua-ang":
            continue
        dst = pkg / "ortho_priority.py"
        shutil.copy2(SHARED_ORTHO, dst)
        print(f"synced ortho -> {dst.relative_to(ROOT)}")


def load_words_from_lexicon(pkg: Path) -> set[str]:
    lex = next(pkg.glob("source/ua_*_lexicon_full.txt"), None)
    if not lex:
        dic = next(pkg.glob("ua_*_GT.dic"), None)
        if not dic:
            return set()
        lex = dic
    words: set[str] = set()
    for i, line in enumerate(lex.read_text(encoding="utf-8", errors="replace").splitlines()):
        if i == 0 and line.strip().isdigit():
            continue
        w, _ = hunspell_lemma(line)
        if w and LETTER.fullmatch(w):
            words.add(w)
    return words


def filter_tilde_only(pkg_ortho, words: set[str]) -> tuple[set[str], list[str]]:
    """Drop missing-tilde forms; keep everything else (including -able Spanish)."""
    ref = pkg_ortho.load_reference_lemmas()
    forbidden = pkg_ortho.accented_keys(ref) | pkg_ortho.accented_keys(words)

    kept: set[str] = set()
    dropped: list[str] = []
    for w in words:
        if not has_diacritic(w):
            key = pkg_ortho.deaccent(w).casefold()
            if key in forbidden or pkg_ortho.is_always_bad_unaccented(w):
                dropped.append(w)
                continue
        kept.add(w)
    return kept, sorted(set(dropped), key=str.casefold)


def write_dic(dic_path: Path, words: set[str]) -> int:
    body = sorted(words, key=lambda s: (s.casefold(), s))
    dic_path.write_text(
        "\n".join([str(len(body))] + body) + "\n",
        encoding="utf-8",
        newline="\n",
    )
    return len(body)


def patch_der_exequatur_sources() -> None:
    der = ROOT / "dict-ua-der"
    fp = der / "source" / "fp_seeds.txt"
    if fp.exists():
        lines = []
        seen = False
        for line in fp.read_text(encoding="utf-8").splitlines():
            if line.strip().casefold() in {"exequatur", "exequátur"}:
                if not seen:
                    lines.append("exequátur")
                    seen = True
            else:
                lines.append(line)
        if not seen:
            lines.append("exequátur")
        fp.write_text("\n".join(lines) + "\n", encoding="utf-8", newline="\n")
        print("patched fp_seeds.txt -> exequátur")

    for rel in (
        "gen_all.py",
        "gen_lexicon.py",
        "gen_lexicon_expand.py",
        "gen_morph.py",
        "gen_expand_der.py",
    ):
        p = der / rel
        if not p.exists():
            continue
        text = p.read_text(encoding="utf-8")
        new = text.replace('"exequatur"', '"exequátur"').replace("'exequatur'", "'exequátur'")
        if new != text:
            p.write_text(new, encoding="utf-8", newline="\n")
            print(f"patched {rel}")


def scrub_package(pkg: Path) -> tuple[int, int, list[str]]:
    dic = next(pkg.glob("ua_*_GT.dic"), None)
    if not dic:
        return 0, 0, []

    words = load_words_from_lexicon(pkg)
    before = len(words)

    sys.path.insert(0, str(pkg))
    import ortho_priority as pkg_ortho

    importlib.reload(pkg_ortho)
    pkg_ortho.EXT = pkg / "source" / "external"

    kept, dropped = filter_tilde_only(pkg_ortho, words)

    if pkg.name == "dict-ua-der":
        # Remove junk morph from unaccented stem; keep accented exequátur
        cleaned = set()
        for w in kept:
            low = w.casefold()
            if low.startswith("exequatur") and not has_diacritic(w):
                dropped.append(w)
                continue
            cleaned.add(w)
        if not any(x.casefold() == "exequátur" for x in cleaned):
            cleaned.add("exequátur")
        kept = cleaned
        dropped = sorted(set(dropped), key=str.casefold)

    after = write_dic(dic, kept)

    # Keep lexicon_full in sync with scrubbed dic (same lemmas)
    lex = next(pkg.glob("source/ua_*_lexicon_full.txt"), None)
    if lex:
        write_dic(lex, kept)

    return before, after, dropped


def main() -> int:
    sync_ortho_copies()
    patch_der_exequatur_sources()

    report: list[str] = []
    for pkg in sorted(ROOT.glob("dict-ua-*")):
        before, after, dropped = scrub_package(pkg)
        if before == 0:
            continue
        msg = f"{pkg.name}: {before} -> {after} (dropped {before - after})"
        print(msg)
        report.append(msg)
        if dropped:
            print(f"  sample: {', '.join(dropped[:30])}")
            if len(dropped) > 30:
                print(f"  ... +{len(dropped) - 30} more")

    out = ROOT / "_shared" / "scrub_missing_tildes_report.txt"
    out.write_text("\n".join(report) + "\n", encoding="utf-8", newline="\n")
    print(f"report: {out}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
