# Mitigación de falsos positivos estructurales (nombres y correos)

**Fecha:** 27/07/2026  
**Código:** `spellcheck_core.py` (`should_ignore`, `scrub_non_lexical`, `find_unique_errors`)

## Problema

1. **Correos:** `juan.perez@ufm.edu.gt` se tokenizaba como `juan`, `perez`, `ufm`, `edu`, `gt`. El filtro `@` nunca veía el correo completo.
2. **Nombres/apellidos:** en Title Case (`Perez`, `Marroquin`, `Ana`) Hunspell los marca como error aunque no sean “faltas” del syllabus.

## Mitigación (no es ALLOWLIST de dominio)

| Caso | Qué hace el código |
|------|--------------------|
| Correo / `mailto:` / URL | Se **borra del texto** antes de tokenizar (`scrub_non_lexical`) |
| Title Case (`Perez`, `García`) | Se **ignora** como nombre propio probable |
| `O'Connor` / `McDonald` | Se ignora (patrones de apellido) |
| Siglas MAYÚSCULAS (2–12 letras) | Se ignora (`TSE`, `PAES`, `UFM`) |

## Qué sigue marcándose

- Faltas en **minúsculas**: `casacion`, `tecnico`, ` amelogenesis` sin tilde.
- Términos de dominio mal escritos en minúsculas.
- Nombres escritos todo en minúsculas (`marroquin`) **sí pueden marcarse** (no hay señal de nombre propio).

## Relación con diccionarios UA

Los `dict-ua-*` cubren **léxico de facultad**.  
Nombres y correos son **ruido estructural** → se filtran en Python, no metiendo apellidos al `.dic`.

## Despliegue EC2

Tras actualizar `spellcheck_core.py`:

```bash
sudo systemctl restart spellcheck-flask.service
# (UNO no requiere reinicio para este cambio)
```

## Prueba rápida

Texto con `Contacto: Ana Perez (ana.perez@ufm.edu.gt) y casacion`  
Esperado: marca `casacion`; no marca `Ana`, `Perez`, ni fragmentos del correo.
