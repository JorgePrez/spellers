# Diccionario médico UA + spellcheck EC2 — Índice

Documentación para continuar el trabajo en otro entorno (VS Code con **UTF-8**).

| # | Documento | Contenido |
|---|-----------|-----------|
| 1 | [01_contexto_y_decisiones.md](01_contexto_y_decisiones.md) | Qué hace el servicio, decisiones de producto |
| 2 | [02_arquitectura_ec2_libreoffice.md](02_arquitectura_ec2_libreoffice.md) | Servicios, rutas, locale es-GT |
| 3 | [03_exploracion_ec2_realizada.md](03_exploracion_ec2_realizada.md) | Comandos y hallazgos en la instancia |
| 4 | [04_diccionario_personalizado_hunspell.md](04_diccionario_personalizado_hunspell.md) | Formato .dic/.aff, extensión OXT, unopkg |
| 5 | [05_problemas_encontrados_y_soluciones.md](05_problemas_encontrados_y_soluciones.md) | CRLF, contador, cp1252, copia manual vs unopkg |
| 6 | [06_instalacion_y_pruebas_ec2.md](06_instalacion_y_pruebas_ec2.md) | Pasos reproducibles y comandos de prueba |
| 7 | [07_archivos_en_repositorio.md](07_archivos_en_repositorio.md) | Qué hay en `spellers-main/dictionaries/` |
| 8 | [08_checklist_rehacer_en_utf8.md](08_checklist_rehacer_en_utf8.md) | Lista para el nuevo chat / nueva carpeta |
| 9 | [09_plan_multi_facultad_ufm.md](09_plan_multi_facultad_ufm.md) | Plan: rendimiento, fuentes existentes, varias facultades UFM |
| 10 | [10_referencias_diccionarios_por_facultad.md](10_referencias_diccionarios_por_facultad.md) | Listado de diccionarios/glosarios por facultad |
| 11 | [11_guia_crear_y_actualizar_diccionarios_ua.md](11_guia_crear_y_actualizar_diccionarios_ua.md) | **Cómo crear/actualizar** un `dict-ua-*` |
| 12 | [12_hunspell_vs_llm_enfoque_hibrido.md](12_hunspell_vs_llm_enfoque_hibrido.md) | Hunspell vs LLM, enfoque híbrido, hasta dónde ampliar |
| 13 | [../dictionaries/README.md](../dictionaries/README.md) | **Inventario de todos los dict-ua-*** |

**Estado al 27/07/2026 (wave 2 expansion):**
- Medicina **v2.4.0** (~147937)
- Odontología **v1.2.0** (~56457)
- Derecho **v1.4.0** (~70137)
- Economía **v1.3.0** (~50376)
- Arquitectura **v1.3.0** (~58024)
- Política/RR.II. **v1.3.0** (~51554)
- Psicología **v1.3.0** (~50269)
- Universitario ES **v1.3.0** (~44960)
- Anglicismos uni **v1.3.0** (~20473)

Ortografía prioritaria (excepto `ang`). Guía en `11_`. Inventario en `dictionaries/README.md`.
