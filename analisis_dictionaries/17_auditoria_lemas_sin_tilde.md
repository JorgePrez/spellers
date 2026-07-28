# Auditoría ortográfica: no whitelistear faltas (p.ej. Algebra)

**Fecha:** 28/07/2026  
**Relacionado:** regla de producto en `dictionaries/README.md`, `ortho_priority.py`, `spellcheck_core.py`.

## Hallazgo sobre «Algebra»

`Algebra` / `algebra` **no estaban** en ningún `ua_*_GT.dic`.  
`álgebra` sí está en `es_GT` / `es_ES`.

La razón de que **Algebra** (Title Case) pasara como correcta era:

```text
looks_like_proper_name("Algebra") → True
→ should_ignore → no se reportaba
```

Hunspell sí la marcaría; el filtro de nombres propios la ocultaba.

## Cambios hechos

### 1. `spellcheck_core.py` (causa de Algebra)

- Title Case **sigue** omitiendo nombres/apellidos con faltas “generales”.
- **Sí se reportan** faltas **solo de tilde** en Title Case (`Algebra` → `Álgebra`, `Analisis` → `Análisis`).
- Así no se blanquean errores ortográficos académicos en títulos de syllabus.

### 2. Diccionarios UA (residuos reales)

Se reforzó `dictionaries/_shared/ortho_priority.py` y se sincronizó a todos los `dict-ua-*` (excepto `ang`, que usa `ortho_ang`):

- Expansión segura de `/G` `/GS` en adjetivos (`metodológico` → también prohíbe `metodologica`).
- Sufijos siempre malos sin tilde: `-cion`, `-logica`, `-logico`, etc. (sin `-ciones` genérico, para no tumbar `elecciones`).
- Lemas exactos bloqueados: `algebra`, `exequatur`, `analisis`.
- `-able`/`-ible` **ya no** se tratan como inglés (son productivos en español).

Script: `dictionaries/_shared/scrub_missing_tildes.py`  
(restaura desde `source/ua_*_lexicon_full.txt` y elimina solo faltas de tilde).

Ejemplos eliminados: `metodologica`, `cientifica`, `patologica`, `exequatur` (+ morfología basura), `estrategica`, `dogmatica`, etc.

Derecho: semilla `exequatur` → **`exequátur`** (RAE); `gen_all.py` vuelve a filtrar **después** de `fp_seeds`.

### 3. Conteos tras scrub (28/07/2026)

| Paquete | Antes (aprox.) | Después |
|---------|---------------:|--------:|
| med | 147937 | 147895 |
| odo | 56457 | 56345 |
| der | 70137 | 70049 |
| eco | 50376 | 50356 |
| arq | 58024 | 57982 |
| pol | 51554 | 51486 |
| psi | 50269 | 50160 |
| uni | 44960 | 44871 |
| ang | 20473 | 20471 |

## Despliegue EC2

1. Subir `spellcheck_core.py` y reiniciar Flask.
2. Subir los `ua_*_GT.dic` (y `ortho_priority.py` / install scripts si aplica).
3. Reinstalar paquetes:

```bash
cd /home/ec2-user/libreoffice_spellcheck/dictionaries
sudo bash install_all_ua_dicts.sh
sudo systemctl restart libreoffice-uno.service
sleep 3
sudo systemctl restart spellcheck-flask.service
```

## Prueba rápida

Texto: `Algebra lineal y Analisis de datos; Perez presente; casacion.`

Esperado:

- Marca `Algebra`, `Analisis`, `casacion` (tildes / ortografía).
- No marca `Perez` como ortografía general (sigue Title Case), **salvo** si la sugerencia es solo tilde (`Pérez`) — en ese caso **sí** marca.
