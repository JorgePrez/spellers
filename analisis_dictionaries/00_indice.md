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
| 13 | [13_mitigacion_nombres_y_correos.md](13_mitigacion_nombres_y_correos.md) | Ignorar nombres propios y correos/URLs |
| 14 | [14_endpoint_mark_llm_segunda_capa.md](14_endpoint_mark_llm_segunda_capa.md) | Endpoint `/spellcheck/mark-llm` (LO + Haiku) |
| 15 | [15_miu_comparar_mark_vs_mark_llm.md](15_miu_comparar_mark_vs_mark_llm.md) | MiU: comparar `/mark` vs `/mark-llm` |
| 16 | [16_trabajo_realizado_endpoint_mark_llm.md](16_trabajo_realizado_endpoint_mark_llm.md) | **Qué se hizo** para tener mark-llm (Flask + MiU + 404) |
| 17 | [17_auditoria_lemas_sin_tilde.md](17_auditoria_lemas_sin_tilde.md) | Auditoría: Algebra / lemas sin tilde + Title Case |
| 18 | [18_spellcheck_reutilizar_revision_previa.md](18_spellcheck_reutilizar_revision_previa.md) | Publicar: solo cronos nuevos/cambiados + modal con revisiones previas |
| 19 | [19_perfil_rendimiento_endocrino.md](19_perfil_rendimiento_endocrino.md) | Perfil tiempos mark-llm (Endocrino); cuello `ms_spell` |
| 20 | [20_comparacion_mark_llm_vs_mark_llm_hs.md](20_comparacion_mark_llm_vs_mark_llm_hs.md) | Cuello = `suggest`; LibreOffice UNO↔Hunspell; factor tiempo profile vs hs (~5×) |
| 21 | [21_endpoints_prueba_spellcheck.md](21_endpoints_prueba_spellcheck.md) | Catálogo endpoints de prueba + pasar MiU a mark-llm-hs |
| 22 | [../dictionaries/README.md](../dictionaries/README.md) | **Inventario de todos los dict-ua-*** |

**Estado al 10/08/2026 (FP syllabus BHI + wave 2):**
- Medicina **v2.5.0** (~147999; bioquímica celular BHI)
- Odontología **v1.2.0** (~56457)
- Derecho **v1.4.0** (~70137)
- Economía **v1.3.0** (~50376)
- Arquitectura **v1.3.0** (~58024)
- Política/RR.II. **v1.3.0** (~51554)
- Psicología **v1.3.0** (~50269)
- Universitario ES **v1.4.0** (~45147; hrs/pdf/meses)
- Anglicismos uni **v1.4.0** (~20489; Osmosis/pair/…)

Ortografía prioritaria (excepto `ang`). Guía en `11_`. Inventario en `dictionaries/README.md`.
