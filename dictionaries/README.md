# Diccionarios UA por facultad / uso (es-GT)

Complementan `es_GT` en LibreOffice (servicio `spellers-main`).  
**Ortografia prioritaria** en todos excepto `ang` (anglicismos intencionales).

| Codigo | Carpeta | Id extension | Version | Lemas (aprox.) | Uso |
|--------|---------|--------------|---------|----------------|-----|
| med | `dict-ua-med` | `org.ua.dictionaries.med-gt` | 2.3.0 | ~125455 | Medicina |
| odo | `dict-ua-odo` | `org.ua.dictionaries.odo-gt` | 1.1.0 | ~47198 | Odontologia |
| der | `dict-ua-der` | `org.ua.dictionaries.der-gt` | 1.2.0 | ~59181 | Derecho |
| eco | `dict-ua-eco` | `org.ua.dictionaries.eco-gt` | 1.1.0 | ~40065 | Ciencias Economicas |
| arq | `dict-ua-arq` | `org.ua.dictionaries.arq-gt` | 1.1.0 | ~46761 | Arquitectura |
| pol | `dict-ua-pol` | `org.ua.dictionaries.pol-gt` | 1.1.0 | ~40380 | Estudios Politicos / RR.II. |
| psi | `dict-ua-psi` | `org.ua.dictionaries.psi-gt` | 1.1.0 | ~38236 | Psicologia |
| uni | `dict-ua-uni` | `org.ua.dictionaries.uni-gt` | 1.1.0 | ~35210 | Terminos universitarios (ES) |
| ang | `dict-ua-ang` | `org.ua.dictionaries.ang-gt` | 1.1.0 | ~12499 | Anglicismos universitarios comunes |

Guia operativa: `analisis_dictionaries/11_guia_crear_y_actualizar_diccionarios_ua.md`  
Referencias por facultad: `analisis_dictionaries/10_referencias_diccionarios_por_facultad.md`

## Instalar todos (ya en el EC2)

Sube la carpeta `dictionaries/` actualizada y ejecuta:

```bash
cd /home/ec2-user/libreoffice_spellcheck/dictionaries
sed -i 's/\r$//' install_all_ua_dicts.sh
sudo bash install_all_ua_dicts.sh
```

O uno por uno:

```bash
cd /home/ec2-user/libreoffice_spellcheck/dictionaries/dict-ua-<area>
sed -i 's/\r$//' install_dict_ua_<area>.sh description.xml ua_<area>_GT.dic ua_<area>_GT.aff
sudo bash install_dict_ua_<area>.sh
```

## Verificar

```bash
/opt/libreoffice25.8/program/unopkg list --shared | grep org.ua.dictionaries
```

LibreOffice fusiona todos en locale `es-GT`.

## Notas

- En `description.xml`, la declaracion XML debe ser `<?xml version="1.0"?>`; la version del paquete va en `<version value="..."/>`.
- Los scripts de instalacion usan `python3` del sistema para la preparacion UTF-8; `unopkg` sigue siendo el de LibreOffice.
- Expansion masiva (v1.1+/1.2): morph + harvest `es_GT`/`es_ES` + seeds; siempre `ortho_priority` (excepto `ang`).
- `uni`: vocabulario academico en espanol (credito, pensum, semestre, rubrica, ...).
- `ang`: prestamos ingleses usados en campus (`feedback`, `deadline`, `paper`, `marketing`, `B2B`, ...).
- Fuentes grandes (DPEJ, DPTM, etc.) = consulta/marco; no volcado literal.
- Regenerar un area: `python gen_expand_<area>.py && python gen_all.py && python verify_dic.py`
