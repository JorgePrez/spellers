# Diccionario medico UA + spellcheck EC2  Indice

Documentacion para continuar el trabajo en otro entorno (VS Code con **UTF-8**).

| # | Documento | Contenido |
|---|-----------|-----------|
| 1 | [01_contexto_y_decisiones.md](01_contexto_y_decisiones.md) | Que hace el servicio, decisiones de producto |
| 2 | [02_arquitectura_ec2_libreoffice.md](02_arquitectura_ec2_libreoffice.md) | Servicios, rutas, locale es-GT |
| 3 | [03_exploracion_ec2_realizada.md](03_exploracion_ec2_realizada.md) | Comandos y hallazgos en la instancia |
| 4 | [04_diccionario_personalizado_hunspell.md](04_diccionario_personalizado_hunspell.md) | Formato .dic/.aff, extension OXT, unopkg |
| 5 | [05_problemas_encontrados_y_soluciones.md](05_problemas_encontrados_y_soluciones.md) | CRLF, contador, cp1252, copia manual vs unopkg |
| 6 | [06_instalacion_y_pruebas_ec2.md](06_instalacion_y_pruebas_ec2.md) | Pasos reproducibles y comandos de prueba |
| 7 | [07_archivos_en_repositorio.md](07_archivos_en_repositorio.md) | Que hay en `spellers-main/dictionaries/` |
| 8 | [08_checklist_rehacer_en_utf8.md](08_checklist_rehacer_en_utf8.md) | Lista para el nuevo chat / nueva carpeta |
| 9 | [09_plan_multi_facultad_ufm.md](09_plan_multi_facultad_ufm.md) | Plan: rendimiento, fuentes existentes, varias facultades UFM |
| 10 | [10_referencias_diccionarios_por_facultad.md](10_referencias_diccionarios_por_facultad.md) | Listado de diccionarios/glosarios por facultad |

**Estado al 24/07/2026:** Diccionario medicina **v2.3.0** (>=60 000 terminos; build ~125k). Ortografia prioritaria (`ortho_priority.py`): nunca se aceptan faltas/sin-tilde. Plan multi-facultad en `09_`. Referencias por facultad en `10_`.
