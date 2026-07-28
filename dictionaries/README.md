# Diccionarios UA por facultad / uso (es-GT)

Complementan el diccionario oficial `es_GT` de LibreOffice en el servicio **spellers-main** (Flask + UNO).  
No reemplazan `es_GT`: LibreOffice **fusiona** todos los Hunspell del locale `es-GT`.

**Regla de producto:** ortografía prioritaria en todos excepto `ang` (anglicismos intencionales de campus).  
Nunca whitelistear formas sin tilde si existe la acentuada (`tecnico` fuera si existe `técnico`).

**Actualizado:** 28/07/2026 (scrub faltas sin tilde + Title Case tilde en spellcheck).

---

## Inventario y cantidades

| Código | Carpeta | Id extensión LibreOffice | Versión | Lemas | Uso |
|--------|---------|--------------------------|---------|------:|-----|
| med | `dict-ua-med` | `org.ua.dictionaries.med-gt` | 2.4.0 | 147 895 | Medicina |
| odo | `dict-ua-odo` | `org.ua.dictionaries.odo-gt` | 1.2.0 | 56 345 | Odontología |
| der | `dict-ua-der` | `org.ua.dictionaries.der-gt` | 1.4.0 | 70 049 | Derecho |
| eco | `dict-ua-eco` | `org.ua.dictionaries.eco-gt` | 1.3.0 | 50 356 | Ciencias económicas |
| arq | `dict-ua-arq` | `org.ua.dictionaries.arq-gt` | 1.3.0 | 57 982 | Arquitectura |
| pol | `dict-ua-pol` | `org.ua.dictionaries.pol-gt` | 1.3.0 | 51 486 | Estudios políticos / RR.II. |
| psi | `dict-ua-psi` | `org.ua.dictionaries.psi-gt` | 1.3.0 | 50 160 | Psicología |
| uni | `dict-ua-uni` | `org.ua.dictionaries.uni-gt` | 1.3.0 | 44 871 | Términos universitarios (ES) |
| ang | `dict-ua-ang` | `org.ua.dictionaries.ang-gt` | 1.3.0 | 20 471 | Anglicismos universitarios |

**Total lemas UA (suma de paquetes):** **549 615**  
(Además del léxico base `es_GT` oficial ~56 666; no se suman aquí porque no son paquete UA.)

> Tras auditoría 28/07/2026 se eliminaron lemas sin tilde que blanqueaban faltas (`metodologica`, `exequatur`, …). Ver `analisis_dictionaries/17_auditoria_lemas_sin_tilde.md`.
> El conteo es la primera línea de cada `ua_<code>_GT.dic` (lemas únicos del paquete tras `ortho_priority` / `ortho_ang`).

---

## Qué hay en cada carpeta `dict-ua-<area>/`

| Archivo | Rol |
|---------|-----|
| `ua_<area>_GT.dic` | Léxico Hunspell (UTF-8, LF); línea 1 = conteo |
| `ua_<area>_GT.aff` | Afinidad; el install suele copiar `es_GT.aff` |
| `description.xml` | Id + **versión del paquete** (`<version value="…"/>`) |
| `dictionaries.xcu` | Registro en locale `es-GT` |
| `META-INF/manifest.xml` | Manifiesto OXT |
| `gen_all.py` | Regenera el `.dic` final |
| `gen_expand_*.py` / wave 2 | Expansión morph + harvest |
| `ortho_priority.py` | Filtro ortográfico (o `ortho_ang.py` en ang) |
| `verify_dic.py` | Valida conteo / duplicados |
| `install_dict_ua_<area>.sh` | Instala con `unopkg --shared` |
| `diagnose_dict_ec2.sh` | Prueba `isValid` en EC2 |
| `source/fp_seeds.txt` | Semillas de falsos positivos de syllabus (si aplica) |
| `source/external/expanded_*.txt` | Léxico expandido intermedio |

Motor compartido de expansión: `_shared/expand_engine.py`  
Script wave 2 (todas las áreas): `gen_wave2_expand_all.py`

---

## Instalar en el EC2

### Todos

```bash
cd /home/ec2-user/libreoffice_spellcheck/dictionaries
sed -i 's/\r$//' install_all_ua_dicts.sh
sudo bash install_all_ua_dicts.sh
```

### Uno solo (ejemplo Derecho)

```bash
cd /home/ec2-user/libreoffice_spellcheck/dictionaries/dict-ua-der
sed -i 's/\r$//' install_dict_ua_der.sh description.xml ua_der_GT.dic ua_der_GT.aff
sudo bash install_dict_ua_der.sh
```

### Verificar instalados

```bash
/opt/libreoffice25.8/program/unopkg list --shared | grep org.ua.dictionaries
```

Deben aparecer los 9 ids `*-gt`. Luego reinicia servicios si hace falta:

```bash
sudo systemctl restart libreoffice-uno.service
sleep 3
sudo systemctl restart spellcheck-flask.service
```

---

## Regenerar un área (desarrollo)

```bash
cd dictionaries/dict-ua-<area>
python gen_expand_<area>.py    # si existe
python gen_all.py
python verify_dic.py
```

O wave 2 global:

```bash
cd dictionaries
python gen_wave2_expand_all.py
```

---

## Documentación relacionada

| Doc | Contenido |
|-----|-----------|
| [`../analisis_dictionaries/11_guia_crear_y_actualizar_diccionarios_ua.md`](../analisis_dictionaries/11_guia_crear_y_actualizar_diccionarios_ua.md) | Cómo crear/actualizar un `dict-ua-*` |
| [`../analisis_dictionaries/10_referencias_diccionarios_por_facultad.md`](../analisis_dictionaries/10_referencias_diccionarios_por_facultad.md) | Fuentes/glosarios por facultad |
| [`../analisis_dictionaries/13_mitigacion_nombres_y_correos.md`](../analisis_dictionaries/13_mitigacion_nombres_y_correos.md) | Nombres propios y correos (filtros en Python) |
| [`../analisis_dictionaries/12_hunspell_vs_llm_enfoque_hibrido.md`](../analisis_dictionaries/12_hunspell_vs_llm_enfoque_hibrido.md) | Hunspell vs LLM |
| [`test_cronogramas/`](test_cronogramas/) | Word de prueba por facultad |

---

## Notas importantes

1. En `description.xml`, la cabecera XML debe ser `<?xml version="1.0"?>`. La versión del paquete va solo en `<version value="…"/>` (no mezclarlas).
2. Los scripts de install usan **`python3` del sistema** para preparar UTF-8; `unopkg` sigue siendo el de LibreOffice.
3. Falsos positivos de **dominio** → ampliar el `.dic` del área.  
   Falsos positivos de **nombres / emails** → filtros en `spellcheck_core.py` (no meter apellidos al diccionario).
4. `uni` = español académico (`pensum`, `crédito`, `rúbrica`…).  
   `ang` = préstamos EN de campus (`feedback`, `deadline`, `checks`, `think`, `tank`…).
5. Fuentes grandes (DPEJ, DPTM, ACODES, etc.) = consulta/marco; no volcado literal sin licencia.
